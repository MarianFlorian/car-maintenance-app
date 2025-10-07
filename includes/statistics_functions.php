<?php
// statistics_functions.php
// 1) Sesiune + autentificare
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}
require __DIR__ . '/db.php';
$uid = $_SESSION['user_id'];

// 2) Determinăm preset vs manual
// Dacă ai în GET un preset, îl folosim.
// Dacă nu ai nici preset nici manual (prima încărcare), preset='all'.
// Altfel (manual=1), ignorăm preset.
if (isset($_GET['preset'])) {
    $preset = $_GET['preset'];
} elseif (!isset($_GET['manual'])) {
    $preset = 'all';
} else {
    $preset = null;  // manual
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
    $date_from = date('Y-m-01');      // 1 al lunii curente
    $date_to   = $today;
    break;
  case 'prev_month':
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to   = date('Y-m-t', strtotime('last day of last month'));
    break;
  case 'all':
    $date_from = '1970-01-01';        // din toate timpurile
    $date_to   = $today;
    break;
  default:
    // manual: ia exact din input (sau azi dacă nu e setat)
    $date_from = $_GET['date_from'] ?? $today;
    $date_to   = $_GET['date_to']   ?? $today;
}

$days = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400 + 1);

// 3) Încarcă vehicule
$stmt = $conn->prepare("SELECT id, brand, model FROM vehicles WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$vehicles = [];
while ($r = $res->fetch_assoc()) {
    $vehicles[$r['id']] = $r['brand'] . ' ' . $r['model'];
}
$stmt->close();

// 4) Vehicule selectate + fallback dacă nu există niciun vehicul
if (count($vehicles) === 0) {
    // nu ai niciun vehicul, punem IN (0) ca să fie SQL valid
    $vehList = '0';
    $selected = [];
} else {
    // preluăm din GET sau, implicit, toate vehiculele
    $selected = $_GET['veh'] ?? array_keys($vehicles);
    if (!is_array($selected)) {
        $selected = [$selected];
    }
    // transformăm în int și eliminăm duplicate
    $selected = array_values(array_unique(array_map('intval', $selected)));
    // dacă array-ul e gol după curățare, luăm tot
    if (count($selected) === 0) {
        $selected = array_keys($vehicles);
    }
    $vehList = implode(',', $selected);
}

// 5) Helper generic sumă
function sumMulti($conn, $table, $col, $dateCol, $from, $to, $uid, $vehList) {
    $sql = "SELECT COALESCE(SUM($col),0) FROM $table
            WHERE user_id=? AND vehicle_id IN ($vehList)
              AND $dateCol BETWEEN ? AND ?";
    $st = $conn->prepare($sql);
    $st->bind_param("iss", $uid, $from, $to);
    $st->execute();
    $v = $st->get_result()->fetch_row()[0];
    $st->close();
    return (float)$v;
}
?>
