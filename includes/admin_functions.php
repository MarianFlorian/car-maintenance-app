<?php
// includes/admin_functions.php

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /");
    exit();
}
require __DIR__ . '/db.php';

// 1) Interval de date (preset vs manual)
if (isset($_GET['preset'])) {
    $preset = $_GET['preset'];
} elseif (!isset($_GET['manual'])) {
    $preset = 'all';
} else {
    $preset = null;
}
$today = date('Y-m-d');
switch ($preset) {
    case '7':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        $date_to   = $today;
        break;
    case '30':
        $date_from = date('Y-m-d', strtotime('-30 days'));
        $date_to   = $today;
        break;
    case 'month':
        $date_from = date('Y-m-01');
        $date_to   = $today;
        break;
    case 'prev_month':
        $date_from = date('Y-m-01', strtotime('first day of last month'));
        $date_to   = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'all':
        $date_from = '1970-01-01';
        $date_to   = $today;
        break;
    default:
        $date_from = $_GET['date_from'] ?? $today;
        $date_to   = $_GET['date_to']   ?? $today;
        break;
}
$GLOBALS['date_from'] = $date_from;
$GLOBALS['date_to']   = $date_to;

// 2) KPI-uri generale
function getTotalUsers($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
}
function getActiveUsers30d($conn) {
    $df = $GLOBALS['date_from'];
    $dt = $GLOBALS['date_to'];
    return (int)$conn->query("
        SELECT COUNT(DISTINCT user_id) FROM fuelings
         WHERE date BETWEEN '$df' AND '$dt'
    ")->fetch_row()[0];
}
function getTotalVehicles($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM vehicles")->fetch_row()[0];
}
function getAvgVehiclesPerUser($conn) {
    $v = getTotalVehicles($conn);
    $u = getTotalUsers($conn);
    return $u ? round($v/$u,2) : 0;
}
function getTotalFuelings($conn) {
    $st = $conn->prepare("SELECT COUNT(*) FROM fuelings WHERE date BETWEEN ? AND ?");
    $st->bind_param("ss", $GLOBALS['date_from'], $GLOBALS['date_to']);
    $st->execute();
    $c = $st->get_result()->fetch_row()[0];
    $st->close();
    return (int)$c;
}
function getTotalServices($conn) {
    $st = $conn->prepare("SELECT COUNT(*) FROM services WHERE date BETWEEN ? AND ?");
    $st->bind_param("ss", $GLOBALS['date_from'], $GLOBALS['date_to']);
    $st->execute();
    $c = $st->get_result()->fetch_row()[0];
    $st->close();
    return (int)$c;
}
function getTotalTaxes($conn) {
    $st = $conn->prepare("SELECT COUNT(*) FROM taxes WHERE date_paid BETWEEN ? AND ?");
    $st->bind_param("ss", $GLOBALS['date_from'], $GLOBALS['date_to']);
    $st->execute();
    $c = $st->get_result()->fetch_row()[0];
    $st->close();
    return (int)$c;
}

// 3) Trend lunar (ultimele 12 luni)
function getMonthlyCounts($conn, $table, $dateCol) {
    $stmt = $conn->prepare("
      SELECT DATE_FORMAT($dateCol,'%Y-%m') AS m, COUNT(*) AS c
        FROM $table
       WHERE $dateCol BETWEEN ? AND ?
       GROUP BY m
       ORDER BY m DESC
       LIMIT 12
    ");
    $stmt->bind_param("ss", $GLOBALS['date_from'], $GLOBALS['date_to']);
    $stmt->execute();
    $res = $stmt->get_result();
    $labels = $data = [];
    while ($r = $res->fetch_assoc()) {
        $labels[] = $r['m'];
        $data[]   = (int)$r['c'];
    }
    $stmt->close();
    return ['labels'=>array_reverse($labels), 'data'=>array_reverse($data)];
}
function getMonthlyAvg($conn, $table, $col, $dateCol) {
    $stmt = $conn->prepare("
      SELECT DATE_FORMAT($dateCol,'%Y-%m') AS m, ROUND(AVG($col),2) AS a
        FROM $table
       WHERE $dateCol BETWEEN ? AND ?
       GROUP BY m
       ORDER BY m DESC
       LIMIT 12
    ");
    $stmt->bind_param("ss", $GLOBALS['date_from'], $GLOBALS['date_to']);
    $stmt->execute();
    $res = $stmt->get_result();
    $labels = $data = [];
    while ($r = $res->fetch_assoc()) {
        $labels[] = $r['m'];
        $data[]   = (float)$r['a'];
    }
    $stmt->close();
    return ['labels'=>array_reverse($labels), 'data'=>array_reverse($data)];
}

// 4) Distribuție top-N
function getDistribution($conn, $table, $col, $limit=5) {
    // alegem coloana de dată corectă
    if ($table === 'vehicles') {
        $dateCol = 'created_at';
    } elseif ($table === 'taxes') {
        $dateCol = 'date_paid';
    } else {
        $dateCol = 'date';
    }
    $stmt = $conn->prepare("
      SELECT COALESCE($col,'Nedefinit') AS label, COUNT(*) AS c
        FROM $table
       WHERE $dateCol BETWEEN ? AND ?
       GROUP BY $col
       ORDER BY c DESC
       LIMIT ?
    ");
    $stmt->bind_param("ssi", $GLOBALS['date_from'], $GLOBALS['date_to'], $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $labels = $data = [];
    while ($r = $res->fetch_assoc()) {
        $labels[] = $r['label'];
        $data[]   = (int)$r['c'];
    }
    $stmt->close();
    return ['labels'=>$labels, 'data'=>$data];
}

// 5) Top vehicule după număr de servicii
function getVehicleServiceCount($conn, $limit=5) {
    $stmt = $conn->prepare("
      SELECT CONCAT(v.brand,' ',v.model) AS veh, COUNT(s.id) AS c
        FROM services s
        JOIN vehicles v ON s.vehicle_id=v.id
       WHERE s.date BETWEEN ? AND ?
       GROUP BY s.vehicle_id
       ORDER BY c DESC
       LIMIT ?
    ");
    $stmt->bind_param("ssi", $GLOBALS['date_from'], $GLOBALS['date_to'], $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 6) Top servicii după cost
function getTopExpensiveServices($conn, $limit=5) {
    $stmt = $conn->prepare("
      SELECT CONCAT(v.brand,' ',v.model) AS veh, s.date, s.service_center, s.cost
        FROM services s
        JOIN vehicles v ON s.vehicle_id=v.id
       WHERE s.date BETWEEN ? AND ?
       ORDER BY s.cost DESC
       LIMIT ?
    ");
    $stmt->bind_param("ssi", $GLOBALS['date_from'], $GLOBALS['date_to'], $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 7) Top vehicule după consum (L/100km)
function getVehicleConsumption($conn, $limit=5) {
    $stmt = $conn->prepare("
      SELECT CONCAT(v.brand,' ',v.model) AS veh,
             ROUND(SUM(f.liters)*100/(MAX(f.km)-MIN(f.km)),2) AS consumption
        FROM fuelings f
        JOIN vehicles v ON f.vehicle_id=v.id
       WHERE f.date BETWEEN ? AND ?
       GROUP BY f.vehicle_id
      HAVING MAX(f.km)-MIN(f.km)>0
       ORDER BY consumption DESC
       LIMIT ?
    ");
    $stmt->bind_param("ssi", $GLOBALS['date_from'], $GLOBALS['date_to'], $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

// 8) Activitate recentă (ultime 10)
function getLastRows($conn, $table, $cols, $limit=10) {
    $colList = implode(',', $cols);
    $res = $conn->query("
      SELECT $colList
        FROM $table
       ORDER BY created_at DESC
       LIMIT $limit
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

