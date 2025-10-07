<?php
// pages/combustibil_logic.php

// 1) Session & autentificare
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// 2) Conexiune DB
require __DIR__ . '/../includes/db.php';

$success = "";
$error   = "";

// 3) PROCESS POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'add_fueling') {
        $vehicle_id  = intval($_POST['vehicle_id']);
        $date        = $_POST['date'];
        $time        = $_POST['time'];
        $km          = intval($_POST['km']);
        $fuel_type   = trim($_POST['fuel_type']);
        $price_per_l = floatval($_POST['price_per_l']);
        $total_cost  = floatval($_POST['total_cost']);
        $liters      = floatval($_POST['liters']);
        $gas_station = trim($_POST['gas_station']);
        $full_tank   = isset($_POST['full_tank']) ? 1 : 0;

        if (!$vehicle_id || !$date || !$time || !$km || !$fuel_type
            || !$price_per_l || !$total_cost || !$liters || !$gas_station) {
            $error = "Toate câmpurile sunt obligatorii.";
        } else {
            $stmt = $conn->prepare("
              INSERT INTO fuelings
                (user_id, vehicle_id, date, time, km, fuel_type, price_per_l, total_cost, liters, gas_station, full_tank)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
              "iissisdddsi",
              $_SESSION['user_id'],
              $vehicle_id,
              $date,
              $time,
              $km,
              $fuel_type,
              $price_per_l,
              $total_cost,
              $liters,
              $gas_station,
              $full_tank
            );
            if ($stmt->execute()) {
                $success = "Realimentare adăugată cu succes!";

                // --- Notificări pe prag de kilometri ---
                $new_km = $km;
                // găsesc notificările active tip km sau both cu prag <= noul km
                $stmt2 = $conn->prepare("
                  SELECT id, trigger_km, note
                    FROM notifications
                   WHERE vehicle_id = ?
                     AND user_id    = ?
                     AND is_active  = 1
                     AND type IN ('km','both')
                     AND trigger_km <= ?
                ");
                $stmt2->bind_param("iii", $vehicle_id, $_SESSION['user_id'], $new_km);
                $stmt2->execute();
                $toNotify = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt2->close();

                // dacă există notificări de trimis, preiau email-ul utilizatorului
                if (count($toNotify) > 0) {
                    $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                    $uStmt->bind_param("i", $_SESSION['user_id']);
                    $uStmt->execute();
                    $uStmt->bind_result($user_email);
                    $uStmt->fetch();
                    $uStmt->close();

                    foreach ($toNotify as $n) {
                        // trimit email de notificare
                        mail(
                          $user_email,
                          "Ai atins pragul de {$n['trigger_km']} km",
                          $n['note']
                        );
                        // marchez notificarea ca inactivă
                        $upd = $conn->prepare("UPDATE notifications SET is_active = 0 WHERE id = ?");
                        $upd->bind_param("i", $n['id']);
                        $upd->execute();
                        $upd->close();
                    }
                }
                // --- Sfârșit notificări km ---
            } else {
                $error = "Eroare la adăugare: " . $conn->error;
            }
            $stmt->close();
        }

    } elseif ($formType === 'edit_fueling') {
        $fuel_id     = intval($_POST['fuel_id']);
        $vehicle_id  = intval($_POST['vehicle_id']);
        $date        = $_POST['date'];
        $time        = $_POST['time'];
        $km          = intval($_POST['km']);
        $fuel_type   = trim($_POST['fuel_type']);
        $price_per_l = floatval($_POST['price_per_l']);
        $total_cost  = floatval($_POST['total_cost']);
        $liters      = floatval($_POST['liters']);
        $gas_station = trim($_POST['gas_station']);
        $full_tank   = isset($_POST['full_tank']) ? 1 : 0;

        if (!$vehicle_id || !$date || !$time || !$km || !$fuel_type
            || !$price_per_l || !$total_cost || !$liters || !$gas_station) {
            $error = "Toate câmpurile sunt obligatorii.";
        } else {
            $stmt = $conn->prepare("
              UPDATE fuelings SET
                vehicle_id = ?, date = ?, time = ?, km = ?, fuel_type = ?,
                price_per_l = ?, total_cost = ?, liters = ?, gas_station = ?, full_tank = ?
              WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param(
              "issisdddsiii",
              $vehicle_id,
              $date,
              $time,
              $km,
              $fuel_type,
              $price_per_l,
              $total_cost,
              $liters,
              $gas_station,
              $full_tank,
              $fuel_id,
              $_SESSION['user_id']
            );
            if ($stmt->execute()) {
                $success = "Realimentare actualizată cu succes!";
            } else {
                $error = "Eroare la actualizare: " . $conn->error;
            }
            $stmt->close();
        }

    } elseif ($formType === 'delete_fueling') {
        $fuel_id = intval($_POST['fuel_id']);
        $stmt = $conn->prepare("DELETE FROM fuelings WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $fuel_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = "Realimentare ștearsă cu succes!";
        } else {
            $error = "Eroare la ștergere: " . $conn->error;
        }
        $stmt->close();
    }
}

// 4) READ FILTERS
$filterVehicle = !empty($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : null;
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to']   ?? '';

// 5) FETCH VEHICLES
$stmt = $conn->prepare("SELECT id, brand, model, year FROM vehicles WHERE user_id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6) BUILD WHERE CLAUSE
$where  = "f.user_id = ?";
$params = [ $_SESSION['user_id'] ];
$types  = "i";
if ($filterVehicle) {
    $where    .= " AND f.vehicle_id = ?";
    $types    .= "i";
    $params[] = $filterVehicle;
}
if ($date_from) {
    $where    .= " AND f.date >= ?";
    $types    .= "s";
    $params[] = $date_from;
}
if ($date_to) {
    $where    .= " AND f.date <= ?";
    $types    .= "s";
    $params[] = $date_to;
}

// 7) FETCH FILTERED FUELINGS
$sql = "
  SELECT f.*, v.brand, v.model, v.year
    FROM fuelings f
    LEFT JOIN vehicles v ON f.vehicle_id = v.id
   WHERE $where
   ORDER BY f.date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$fuelings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 8) FETCH TOTAL COST
$sqlCost = "SELECT COALESCE(SUM(total_cost), 0) AS total_cost FROM fuelings f WHERE $where";
$stmt = $conn->prepare($sqlCost);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalCostOverall = $stmt->get_result()->fetch_assoc()['total_cost'];
$stmt->close();

// 9) CALCULATE ACCURATE AVERAGE CONSUMPTION (per full-tank segments)
$averageConsumption = 0.0;
$totalWeighted      = 0.0;
$totalKmAll         = 0.0;

// 9.1) Preiau toate alimentările filtrate, ordonate
$sqlAll = "
  SELECT vehicle_id, km, liters, full_tank
    FROM fuelings f
   WHERE $where
   ORDER BY vehicle_id, date, time
";
$stmt = $conn->prepare($sqlAll);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 9.2) Grupuiesc după vehicul
$byVeh = [];
foreach ($all as $r) {
    $byVeh[$r['vehicle_id']][] = $r;
}

// 9.3) Pentru fiecare vehicul, aplic algoritmul de segmentare
foreach ($byVeh as $fills) {
    $prevFullKm = null;
    $accLiters  = 0.0;

    foreach ($fills as $f) {
        $km     = (float)$f['km'];
        $liters = (float)$f['liters'];

        if ($f['full_tank']) {
            if ($prevFullKm !== null && $km > $prevFullKm) {
                $dist    = $km - $prevFullKm;
                $consSeg = ($accLiters / $dist) * 100;
                $totalWeighted += $consSeg * $dist;
                $totalKmAll    += $dist;
            }
            $prevFullKm = $km;
            $accLiters  = 0.0;
        }
        $accLiters += $liters;
    }
}

// 9.4) Rezultatul
if ($totalKmAll > 0) {
    $averageConsumption = round($totalWeighted / $totalKmAll, 2);
}
