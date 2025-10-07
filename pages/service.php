<?php
// pages/service.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
require __DIR__ . '/../includes/db.php'; // expune $conn (MySQLi)

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// 1) PROCESS POST: add, edit, delete service + notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    // DELETE SERVICE
    if ($formType === 'delete_service') {
        $svcId = intval($_POST['service_id'] ?? 0);
        if ($svcId) {
            // delete service
            $stmt = $conn->prepare("DELETE FROM services WHERE id=? AND user_id=?");
            $stmt->bind_param('ii', $svcId, $user_id);
            if ($stmt->execute()) {
                $success = 'Intervenție service ștearsă cu succes.';
                // delete related notifications
                $stmtN = $conn->prepare("
                  DELETE FROM notifications
                   WHERE user_id=? AND service_id=? AND source='service'
                ");
                $stmtN->bind_param('ii', $user_id, $svcId);
                $stmtN->execute();
                $stmtN->close();
            } else {
                $error = 'Eroare la ștergere: ' . $stmt->error;
            }
            $stmt->close();
        }

    // ADD or EDIT SERVICE
    } elseif (in_array($formType, ['add_service','edit_service'], true)) {
        $svcId          = intval($_POST['service_id'] ?? 0);
        $vehicle_id     = intval($_POST['vehicle_id'] ?? 0);
        $date           = $_POST['date'] ?: null;
        $km             = ($_POST['km'] ?? '') !== '' ? intval($_POST['km']) : null;
        $description    = trim($_POST['description'] ?? '') ?: null;
        $cost           = ($_POST['cost'] ?? '') !== '' ? floatval($_POST['cost']) : null;
        $service_center = trim($_POST['service_center'] ?? '') ?: null;

        if (!$vehicle_id || !$date) {
            $error = 'Alege un vehicul și o dată.';
        } else {
            // INSERT or UPDATE service
            if ($formType === 'add_service') {
                $time = date('H:i:s');
                $stmt = $conn->prepare("
                  INSERT INTO services
                    (user_id, vehicle_id, date, time, km, description, cost, service_center)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'iissisds',
                    $user_id,
                    $vehicle_id,
                    $date,
                    $time,
                    $km,
                    $description,
                    $cost,
                    $service_center
                );
                $msgOk  = 'Intervenție adăugată cu succes!';
                $msgErr = 'Eroare la adăugare: ';
            } else {
                $stmt = $conn->prepare("
                  UPDATE services SET
                    vehicle_id     = ?,
                    date           = ?,
                    km             = ?,
                    description    = ?,
                    cost           = ?,
                    service_center = ?
                  WHERE id = ? AND user_id = ?
                ");
                $stmt->bind_param(
                    'isisdssi',
                    $vehicle_id,
                    $date,
                    $km,
                    $description,
                    $cost,
                    $service_center,
                    $svcId,
                    $user_id
                );
                $msgOk  = 'Intervenție actualizată cu succes!';
                $msgErr = 'Eroare la actualizare: ';
            }

            if ($stmt->execute()) {
                $success = $msgOk;
                $currentId = $formType==='add_service' ? $stmt->insert_id : $svcId;
            } else {
                $error = $msgErr . $stmt->error;
            }
            $stmt->close();

            // PROCESS NOTIFICATION SETTINGS
            // first, delete old service notifications on edit
            if ($formType === 'edit_service') {
                $delN = $conn->prepare("
                  DELETE FROM notifications
                   WHERE user_id=? AND service_id=? AND source='service'
                ");
                $delN->bind_param('ii', $user_id, $currentId);
                $delN->execute();
                $delN->close();
            }

            // gather user inputs
            $notif_date = $_POST['notif_date'] ?: null;
            $notif_km   = ($_POST['notif_km'] ?? '') !== '' ? intval($_POST['notif_km']) : null;
            $notif_note = trim($_POST['notif_note'] ?? '') ?: null;

            // insert new notification if any
            if ($notif_date || $notif_km !== null) {
                $type = $notif_date && $notif_km!==null
                      ? 'both'
                      : ($notif_date ? 'date' : 'km');

                $ins = $conn->prepare("
                  INSERT INTO notifications
                    (user_id, vehicle_id, service_id, type, trigger_date, trigger_km, note, source)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'service')
                ");
                $ins->bind_param(
                    'iiissis',
                    $user_id,
                    $vehicle_id,
                    $currentId,
                    $type,
                    $notif_date,
                    $notif_km,
                    $notif_note
                );
                $ins->execute();
                $ins->close();
            }
        }
    }
}

// 2) FETCH vehicles for dropdown & card totals
$stmt = $conn->prepare("
  SELECT id, brand, model, year
    FROM vehicles
   WHERE user_id=?
   ORDER BY brand, model
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
  SELECT vehicle_id, COALESCE(SUM(cost),0) AS total
    FROM services
   WHERE user_id=?
   GROUP BY vehicle_id
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$vehicleCosts = [];
foreach ($res as $r) {
    $vehicleCosts[$r['vehicle_id']] = $r['total'];
}
$stmt->close();

// 3) FETCH filters
$filterVehicle = isset($_GET['filter_vehicle']) && $_GET['filter_vehicle']!=='' 
               ? intval($_GET['filter_vehicle'])
               : null;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// 4) FETCH services list
$sql    = "
  SELECT s.*, v.brand, v.model, v.year
    FROM services s
LEFT JOIN vehicles v ON v.id=s.vehicle_id
   WHERE s.user_id=?
";
$params = [$user_id];
$types  = 'i';
if ($filterVehicle) {
    $sql      .= " AND s.vehicle_id=?";
    $params[]  = $filterVehicle;
    $types    .= 'i';
}
if ($dateFrom) {
    $sql      .= " AND s.date>=?";
    $params[]  = $dateFrom;
    $types    .= 's';
}
if ($dateTo) {
    $sql      .= " AND s.date<=?";
    $params[]  = $dateTo;
    $types    .= 's';
}
$sql .= " ORDER BY s.date DESC";

$stmt = $conn->prepare($sql);
$bind = [$types];
foreach ($params as $i => $v) $bind[] = &$params[$i];
call_user_func_array([$stmt,'bind_param'], $bind);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5) TOTAL service cost
$stmt = $conn->prepare("
  SELECT COALESCE(SUM(cost),0) AS total_cost
    FROM services
   WHERE user_id=?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$totalCost = $stmt->get_result()->fetch_assoc()['total_cost'];
$stmt->close();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="service-container">

  <!-- Page Header -->
  <div class="service-header">
    <h2>Intervenții Service</h2>
  </div>

  <!-- Feedback Alerts -->
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Total General -->
  <div class="total-card">
    <div class="card-body">
      <span>Total cheltuit:</span>
      <span class="value"><?= number_format($totalCost,2) ?> RON</span>
    </div>
  </div>

  <!-- Total per Vehicul -->
  <div class="vehicle-cards">
    <?php foreach ($vehicles as $v): ?>
      <?php $amt = $vehicleCosts[$v['id']] ?? 0; ?>
      <div class="vehicle-card">
        <h6><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})") ?></h6>
        <div class="amount"><?= number_format($amt,2) ?> RON</div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Add Service Button -->
  <div class="text-center mb-4">
    <button id="addServiceBtn"
            class="btn btn-success"
            data-toggle="modal"
            data-target="#serviceModal">
      <i class="fas fa-plus"></i> Adaugă Service
    </button>
  </div>

  <!-- Filter Panel -->
  <form method="get" class="filter-panel form-inline justify-content-center">
    <div class="form-group mb-2">
      <label for="filter_vehicle">Vehicul:</label>
      <select id="filter_vehicle" name="filter_vehicle" class="form-control mx-2">
        <option value="">Toate</option>
        <?php foreach($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>" <?= $filterVehicle===$v['id']?'selected':''?>>
            <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})") ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group mb-2">
      <label for="date_from">De la:</label>
      <input type="date" id="date_from" name="date_from" class="form-control mx-2" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="form-group mb-2">
      <label for="date_to">Până la:</label>
      <input type="date" id="date_to" name="date_to" class="form-control mx-2" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn-outline-primary mb-2 mx-2">Filtrează</button>
  </form>

  <!-- Services Table -->
  <div class="table-responsive service-table mb-5">
  <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Data</th>
          <th>Vehicul</th>
          <th>KM</th>
          <th>Cost (RON)</th>
          <th>Centrul Service</th>
          <th>Descriere</th>
          <th>Acțiuni</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($services): ?>
          <?php foreach ($services as $s): ?>
            <tr
               data-id="<?= $s['id'] ?>"
                data-vehicle-id="<?= $s['vehicle_id'] ?>"
                data-date="<?= htmlspecialchars($s['date']) ?>"
                data-km="<?= htmlspecialchars($s['km']) ?>"
                data-cost="<?= htmlspecialchars($s['cost']) ?>"
                data-center="<?= htmlspecialchars($s['service_center'],ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($s['description'],ENT_QUOTES) ?>"
                data-notif-date="<?= htmlspecialchars($s['trigger_date']   ?? '') ?>"
                data-notif-km="<?= htmlspecialchars($s['trigger_km']      ?? '') ?>"
                data-notif-note="<?= htmlspecialchars($s['note']          ?? '',ENT_QUOTES) ?>"
>             
              <td><?= htmlspecialchars($s['date']) ?></td>
              <td><?= htmlspecialchars("{$s['brand']} {$s['model']} ({$s['year']})") ?></td>
              <td><?= $s['km']!==null ? number_format($s['km']).' km' : '—' ?></td>
              <td><?= $s['cost']!==null ? number_format($s['cost'],2) : '—' ?></td>
              <td><?= htmlspecialchars($s['service_center']?:'—') ?></td>
              <td><?= htmlspecialchars($s['description']   ?: '—') ?></td>
              <td>
                <button class="btn btn-sm btn-primary editServiceBtn">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="post" class="d-inline" onsubmit="return confirm('Ștergi această intervenție?');">
                  <input type="hidden" name="form_type"    value="delete_service">
                  <input type="hidden" name="service_id"   value="<?= $s['id'] ?>">
                  <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center">Nu există intervenții.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div><!-- /.service-container -->

<!-- Modal Add/Edit Service -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="serviceForm" method="post" action="service.php" class="modal-content">
      <input type="hidden" name="form_type"   id="formType"    value="add_service">
      <input type="hidden" name="service_id"  id="serviceId"   value="">
      <div class="modal-header">
        <h5 class="modal-title" id="serviceModalLabel">Adaugă Service</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Vehicul</label>
            <select name="vehicle_id" id="vehicleSelect" class="form-control" required>
              <option value="">— Selectează —</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>"><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})") ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Data</label>
            <input type="date" name="date" id="dateInput" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Kilometri</label>
            <input type="number" name="km" id="kmInput" class="form-control" placeholder="Ex: 50000">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Cost (RON)</label>
            <input type="number" step="0.01" name="cost" id="costInput" class="form-control" placeholder="Ex: 250.00">
          </div>
          <div class="form-group col-md-6">
            <label>Centrul Service</label>
            <input type="text" name="service_center" id="centerInput" class="form-control" placeholder="Ex: AutoXYZ">
          </div>
        </div>
        <div class="form-group">
          <label>Descriere</label>
          <textarea name="description" id="descInput" class="form-control" rows="2"></textarea>
        </div>
        <hr>
        <h5>Notificare Service</h5>
        <div class="form-group">
          <label>După dată (opțional)</label>
          <input type="date" name="notif_date" id="notifDate" class="form-control">
        </div>
        <div class="form-group">
          <label>După km (opțional)</label>
          <input type="number" name="notif_km" id="notifKm" class="form-control" placeholder="Ex: 55000">
        </div>
        <div class="form-group">
          <label>Notă notificare (opțional)</label>
          <textarea name="notif_note" id="notifNote" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">Adaugă Service</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="../assets/js/scripts.js"></script>