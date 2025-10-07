<?php
// pages/taxe.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
require __DIR__ . '/../includes/db.php'; // MySQLi $conn

$user_id    = $_SESSION['user_id'];
$success    = '';
$error      = '';
$uploadDir  = __DIR__ . '/../uploads/';

// helper pentru bind_param cu referințe
function refValues(array &$arr) {
    $refs = [];
    foreach ($arr as $key => &$val) {
        $refs[$key] = &$val;
    }
    return $refs;
}

// 1) PROCESS POST: add_tax, edit_tax, delete_tax
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'delete_tax') {
        $tax_id = intval($_POST['tax_id'] ?? 0);
        if ($tax_id) {
            $stmt = $conn->prepare("
                DELETE FROM taxes
                 WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param('ii', $tax_id, $user_id);
            if ($stmt->execute()) {
                $success = 'Taxă ștearsă cu succes.';
            } else {
                $error = 'Eroare la ștergere: ' . $stmt->error;
            }
            $stmt->close();
        }

    } elseif (in_array($formType, ['add_tax','edit_tax'], true)) {
        $tax_id           = intval($_POST['tax_id'] ?? 0);
        $vehicle_id       = intval($_POST['vehicle_id'] ?? 0);
        $raw_type         = trim($_POST['tax_type'] ?? '');
        $tax_type         = $raw_type === 'Altele'
                            ? trim($_POST['tax_type_other'] ?? '')
                            : $raw_type;
        $amount           = ($_POST['amount'] ?? '') !== '' ? floatval($_POST['amount']) : null;
        $date_paid        = $_POST['date_paid'] ?? null;
        $due_date         = $_POST['due_date']  ?? null;
        $notes            = trim($_POST['notes'] ?? '') ?: null;
        $add_to_documents = isset($_POST['add_to_documents']) ? 1 : 0;

        if (!$vehicle_id || !$tax_type || $amount === null || !$date_paid) {
            $error = 'Completează vehicul, tip taxă, sumă și data plății.';
        } else {
            $docType = $tax_type;

            // 1) Inserare taxă
    $stmt = $conn->prepare("
      INSERT INTO taxes
        (user_id, vehicle_id, tax_type, amount, date_paid, due_date, notes, photo_path, add_to_documents)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
      "iissssssi",
      $_SESSION['user_id'],
      $vehicle_id,
      $tax_type,
      $amount,
      $date_paid,
      $due_date,
      $notes,
      $photo_path,
      $add_to_documents
    );
    if ($stmt->execute()) {
        $taxId   = $conn->insert_id;
        $success = "Taxa a fost adăugată cu succes!";

        // 2) Creează notificarea aferentă taxei
        $noteText = "Taxa „{$tax_type}” expiră la {$due_date}.";
        $stmt2 = $conn->prepare("
          INSERT INTO notifications
            (user_id, vehicle_id, type, trigger_date, note, source, tax_id)
          VALUES (?, ?, 'date', ?, ?, 'tax', ?)
        ");
        $stmt2->bind_param(
          "iissi",
          $_SESSION['user_id'],
          $vehicle_id,
          $due_date,
          $noteText,
          $taxId
        );
        $stmt2->execute();
        $stmt2->close();

    } else {
        $error = "Eroare la adăugare taxă: " . $stmt->error;
    }
    $stmt->close();
}

            // upload imagini dacă e cazul
            if (!$error && $add_to_documents && !empty($_FILES['images']['name'][0])) {
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                foreach ($_FILES['images']['name'] as $i => $name) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp  = $_FILES['images']['tmp_name'][$i];
                        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $safe = uniqid() . ".$ext";
                        $dest = $uploadDir . $safe;
                        if (move_uploaded_file($tmp, $dest)) {
                            $relPath = "uploads/$safe";
                            $stmtDoc = $conn->prepare("
                                INSERT INTO documents
                                  (user_id, vehicle_id, tax_id, type, file_path, note, uploaded_at, expires_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                            ");
                            $stmtDoc->bind_param(
                                'iiissss',
                                $user_id,
                                $vehicle_id,
                                $currentTaxId,
                                $docType,
                                $relPath,
                                $notes,
                                $due_date
                            );
                            $stmtDoc->execute();
                            $stmtDoc->close();
                        }
                    }
                }
            }
        }
    }


// 2) FETCH VEHICLES
$stmt = $conn->prepare("
    SELECT id, brand, model, year
      FROM vehicles
     WHERE user_id = ?
     ORDER BY brand, model
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($vid, $vbrand, $vmodel, $vyear);
$vehicles = [];
while ($stmt->fetch()) {
    $vehicles[] = ['id'=>$vid, 'brand'=>$vbrand, 'model'=>$vmodel, 'year'=>$vyear];
}
$stmt->free_result();
$stmt->close();

// 3) TOTAL PER VEHICUL
$stmt = $conn->prepare("
    SELECT vehicle_id, COALESCE(SUM(amount),0)
      FROM taxes
     WHERE user_id = ?
     GROUP BY vehicle_id
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($tvId, $tvTotal);
$vehicleCosts = [];
while ($stmt->fetch()) {
    $vehicleCosts[$tvId] = $tvTotal;
}
$stmt->free_result();
$stmt->close();

// 4) FILTERS
$filterVehicle = isset($_GET['filter_vehicle']) && $_GET['filter_vehicle'] !== ''
    ? intval($_GET['filter_vehicle'])
    : null;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// 5) FETCH TAXES FILTERED
$sql    = "
    SELECT t.id, t.vehicle_id, t.tax_type, t.amount, t.date_paid,
           t.due_date, t.notes, t.add_to_documents,
           v.brand, v.model, v.year
      FROM taxes t
 LEFT JOIN vehicles v ON v.id = t.vehicle_id
     WHERE t.user_id = ?
";
$params = [$user_id];
$types  = 'i';

if ($filterVehicle) {
    $sql      .= " AND t.vehicle_id = ?";
    $types    .= 'i';
    $params[]  = $filterVehicle;
}
if ($dateFrom) {
    $sql      .= " AND t.date_paid >= ?";
    $types    .= 's';
    $params[]  = $dateFrom;
}
if ($dateTo) {
    $sql      .= " AND t.date_paid <= ?";
    $types    .= 's';
    $params[]  = $dateTo;
}
$sql .= " ORDER BY t.date_paid DESC";

$stmt = $conn->prepare($sql);
array_unshift($params, $types);
call_user_func_array([$stmt, 'bind_param'], refValues($params));
$stmt->execute();
$stmt->store_result();

// pregătire bind_result
$cols = ['id','vehicle_id','tax_type','amount','date_paid','due_date','notes','add_to_documents','brand','model','year'];
foreach ($cols as $c) {
    $$c = null;
    $bindCols[] = &$$c;
}
call_user_func_array([$stmt, 'bind_result'], $bindCols);

$taxes = [];
while ($stmt->fetch()) {
    $taxes[] = [
        'id'=>$id,
        'vehicle_id'=>$vehicle_id,
        'tax_type'=>$tax_type,
        'amount'=>$amount,
        'date_paid'=>$date_paid,
        'due_date'=>$due_date,
        'notes'=>$notes,
        'add_to_documents'=>$add_to_documents,
        'brand'=>$brand,
        'model'=>$model,
        'year'=>$year,
    ];
}
$stmt->free_result();
$stmt->close();

// 6) TOTAL ALL
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
      FROM taxes
     WHERE user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($totalTax);
$stmt->fetch();
$stmt->free_result();
$stmt->close();

// 7) FETCH DOCUMENTS PER TAX
$stmt = $conn->prepare("
    SELECT tax_id, file_path
      FROM documents
     WHERE user_id = ? AND tax_id IS NOT NULL
     ORDER BY uploaded_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($dTaxId, $dPath);
$docsByTax = [];
while ($stmt->fetch()) {
    $docsByTax[$dTaxId][] = $dPath;
}
$stmt->free_result();
$stmt->close();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="tax-container">

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="tax-header">
    <h2>Taxe Auto</h2>
  </div>

  <div class="card card-total mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <span>Total general taxe:</span>
      <span class="value"><?= number_format($totalTax, 2) ?> RON</span>
    </div>
  </div>

  <div class="vehicle-cards mb-4">
    <?php foreach ($vehicles as $v): ?>
      <div class="vehicle-card">
        <h6><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})") ?></h6>
        <div class="amount"><?= number_format($vehicleCosts[$v['id']] ?? 0, 2) ?> RON</div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center mb-4">
    <button id="addTaxBtn" class="btn btn-success" data-toggle="modal" data-target="#taxModal">
      <i class="fas fa-plus"></i> Adaugă Taxă
    </button>
  </div>

  <form method="get" class="filter-panel form-inline justify-content-center mb-4">
    <div class="form-group mr-3">
      <label>Vehicul:</label>
      <select name="filter_vehicle" class="form-control ml-2">
        <option value="">Toate</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id']?>" <?= ($filterVehicle === $v['id'])?'selected':''?>>
            <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group mr-3">
      <label>De la:</label>
      <input type="date" name="date_from" class="form-control ml-2" value="<?= htmlspecialchars($dateFrom)?>">
    </div>
    <div class="form-group mr-3">
      <label>Până la:</label>
      <input type="date" name="date_to" class="form-control ml-2" value="<?= htmlspecialchars($dateTo)?>">
    </div>
    <button class="btn btn-outline-primary">Filtrează</button>
  </form>

  <div class="tax-table mb-5">
    <table class="table table-striped table-bordered">
      <thead class="thead-dark">
        <tr>
          <th>Data plată</th>
          <th>Vehicul</th>
          <th>Tip taxă</th>
          <th>Suma</th>
          <th>Scadență</th>
          <th>Note</th>
          <th>Documente</th>
          <th>Acțiuni</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($taxes): foreach ($taxes as $t): ?>
          <tr
            data-id="<?= $t['id']?>"
            data-vehicle-id="<?= $t['vehicle_id']?>"
            data-type="<?= htmlspecialchars($t['tax_type'],ENT_QUOTES)?>"
            data-amount="<?= $t['amount']?>"
            data-date-paid="<?= htmlspecialchars($t['date_paid'])?>"
            data-due-date="<?= htmlspecialchars($t['due_date'])?>"
            data-notes="<?= htmlspecialchars($t['notes'],ENT_QUOTES)?>"
            data-add="<?= $t['add_to_documents']?>">
            <td><?= htmlspecialchars($t['date_paid'])?></td>
            <td><?= htmlspecialchars("{$t['brand']} {$t['model']} ({$t['year']})")?></td>
            <td><?= htmlspecialchars($t['tax_type'])?></td>
            <td><?= number_format($t['amount'],2)?></td>
            <td><?= $t['due_date']?:'—'?></td>
            <td><?= htmlspecialchars($t['notes']?:'—')?></td>
            <td>
              <?php if (!empty($docsByTax[$t['id']])): foreach ($docsByTax[$t['id']] as $p): ?>
                <a href="/Licenta/<?= htmlspecialchars($p)?>" target="_blank">Vezi</a><br>
              <?php endforeach; else: ?>&mdash;<?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-primary editTaxBtn">
                <i class="fas fa-edit"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Sigur ștergi?');">
                <input type="hidden" name="form_type" value="delete_tax">
                <input type="hidden" name="tax_id"    value="<?= $t['id']?>">
                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center">Nu există taxe înregistrate.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal Add/Edit Taxă -->
<div class="modal fade" id="taxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="taxForm" method="post" action="taxe.php" class="modal-content" enctype="multipart/form-data">
      <input type="hidden" name="form_type" id="taxFormType" value="add_tax">
      <input type="hidden" name="tax_id"    id="taxId">
      <div class="modal-header">
        <h5 class="modal-title" id="taxModalLabel">Adaugă Taxă</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Vehicul</label>
            <select name="vehicle_id" id="taxVehicleSelect" class="form-control" required>
              <option value="">— Selectează —</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id']?>"><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Tip taxă</label>
            <select name="tax_type" id="taxTypeSelect" class="form-control" required>
              <option value="">— Alege —</option>
              <option>RCA</option><option>CASCO</option><option>ITP</option>
              <option>RAR</option><option>Rovinietă</option>
              <option>Taxă poluare</option><option>Taxă drum</option>
              <option>Parcare</option><option>Altele</option>
            </select>
          </div>
          <div class="form-group col-md-4" id="otherTaxTypeGroup" style="display:none;">
            <label>Specificați tipul</label>
            <input type="text" name="tax_type_other" id="taxTypeOtherInput" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Suma (RON)</label>
            <input type="number" step="0.01" name="amount" id="amountInput" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Data plată</label>
            <input type="date" name="date_paid" id="datePaidInput" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Scadență</label>
            <input type="date" name="due_date" id="dueDateInput" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Note</label>
          <textarea name="notes" id="notesInput" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Imagini</label>
          <input type="file" name="images[]" id="imagesInput" class="form-control-file" accept="image/*" multiple>
        </div>
        <div class="form-group form-check">
          <input type="checkbox" name="add_to_documents" id="addToDocsInput" class="form-check-input" value="1">
          <label class="form-check-label" for="addToDocsInput">Adaugă în Documente</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="submit" class="btn btn-primary" id="taxSubmitBtn">Salvează</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/scripts.js"></script>
