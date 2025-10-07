<?php
// pages/notifications.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}
require __DIR__ . '/../includes/db.php';

// Set timezone
date_default_timezone_set('Europe/Bucharest');

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// 1) HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form_type'] ?? '';
    if (in_array($form, ['add_notification', 'edit_notification'])) {
        $vid        = intval($_POST['vehicle_id'] ?? 0);
        $dateCond   = $_POST['trigger_date'] ?: null;
        $kmCond     = $_POST['trigger_km'] !== '' ? intval($_POST['trigger_km']) : null;
        $note       = trim($_POST['note'] ?? '');

        if (!$vid || ($dateCond === null && $kmCond === null)) {
            $error = 'Alege vehicul și cel puțin o condiție (dată sau km).';
        } else {
            $type = $dateCond !== null && $kmCond !== null ? 'both' :
                    ($dateCond !== null ? 'date' : 'km');

            if ($form === 'add_notification') {
                $stmt = $conn->prepare(
                    'INSERT INTO notifications
                     (user_id, vehicle_id, type, trigger_date, trigger_km, note, source, service_id)
                     VALUES (?, ?, ?, ?, ?, ?, "manual", NULL)'
                );
                $stmt->bind_param('iissis', $user_id, $vid, $type, $dateCond, $kmCond, $note);
                $msg = 'Notificarea creată!';
            } else {
                $nid = intval($_POST['id']);
                $stmt = $conn->prepare(
                    'UPDATE notifications
                     SET vehicle_id=?, type=?, trigger_date=?, trigger_km=?, note=?
                     WHERE id=? AND user_id=?'
                );
                $stmt->bind_param('issisii', $vid, $type, $dateCond, $kmCond, $note, $nid, $user_id);
                $msg = 'Notificarea actualizată!';
            }

            if ($stmt->execute()) {
                $success = $msg;
            } else {
                $error = 'Eroare: ' . $stmt->error;
            }
            $stmt->close();
        }

    } elseif ($form === 'delete_notification') {
        $nid  = intval($_POST['id']);
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $nid, $user_id);
        if ($stmt->execute()) {
            $success = 'Notificarea a fost ștearsă.';
        } else {
            $error = 'Eroare: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// 2) FETCH VEHICLES FOR FILTER
$stmt = $conn->prepare('SELECT id, brand, model, year FROM vehicles WHERE user_id=?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$filterVeh = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

// 3) FETCH NOTIFICATIONS + COMPUTE current_km
$sql = "
  SELECT 
    n.*,
    v.brand, v.model, v.year,
    d.type   AS doc_type,
    d.expires_at AS doc_expires,
    (SELECT MAX(f.km) FROM fuelings f 
       WHERE f.vehicle_id = n.vehicle_id AND f.user_id = n.user_id
    ) AS current_km
  FROM notifications n
  LEFT JOIN vehicles  v ON v.id = n.vehicle_id
  LEFT JOIN documents d ON d.id = n.document_id
 WHERE n.user_id = ? 
   AND n.is_active = 1
";
if ($filterVeh) {
    $sql .= " AND n.vehicle_id = ?";
}
$sql .= " ORDER BY n.created_at DESC";

$stmt = $conn->prepare($sql);
if ($filterVeh) {
    $stmt->bind_param('ii', $user_id, $filterVeh);
} else {
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4) DETERMINE STATUS AND CSS
$now      = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
$today    = $now->format('Y-m-d');
$tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');

foreach ($notifs as &$n) {
    // date-based status
    if (!empty($n['trigger_date'])) {
        if ($n['trigger_date'] < $today)      $n['dateStatus'] = 'expired';
        elseif ($n['trigger_date'] === $today)    $n['dateStatus'] = 'today';
        elseif ($n['trigger_date'] === $tomorrow) $n['dateStatus'] = 'tomorrow';
        else                                 $n['dateStatus'] = 'upcoming';
    } else {
        $n['dateStatus'] = 'upcoming';
    }
    // km-based status
    if (in_array($n['type'], ['km','both'])) {
        $cur = $n['current_km'] ?? 0;
        $n['kmStatus'] = $n['trigger_km'] <= $cur ? 'expired' : 'upcoming';
    } else {
        $n['kmStatus'] = null;
    }
    // assemble CSS classes
    $classes = [];
    $map = [
        'expired'  => 'border-danger text-danger',
        'today'    => 'border-warning text-warning',
        'tomorrow' => 'border-info text-info',
        'upcoming' => ''
    ];
    if ($n['dateStatus'] !== 'upcoming') $classes[] = $map[$n['dateStatus']];
    if ($n['kmStatus'] === 'expired')    $classes[] = $map['expired'];
    $n['statusClass'] = implode(' ', $classes);
}
unset($n);

// 5) GROUP BY SOURCE
$groups = ['tax'=>[], 'service'=>[], 'document'=>[], 'manual'=>[]];
foreach ($notifs as $n) {
    if (!empty($n['service_id']))       $groups['service'][]  = $n;
    elseif ($n['source']==='tax')       $groups['tax'][]      = $n;
    elseif ($n['source']==='document')  $groups['document'][] = $n;
    else                                $groups['manual'][]   = $n;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="col-12 col-md-9 py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="fas fa-bell text-primary"></i> Notificări</h2>
      <button class="btn btn-success" data-toggle="modal" data-target="#notifModal">
        <i class="fas fa-plus"></i> Adaugă notificare
      </button>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FILTER -->
    <form method="get" class="form-inline mb-4">
      <label class="mr-2">Vehicul:</label>
      <select name="vehicle_id" class="form-control mr-2">
        <option value="0">Toate</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>" <?= $filterVeh===$v['id']?'selected':''?>>
            <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
          </option>
        <?php endforeach;?>
      </select>
      <button class="btn btn-secondary">Aplică filtrul</button>
    </form>

    <!-- LIST -->
    <?php foreach ($groups as $cat=>$items): ?>
      <h4 class="mt-4 text-capitalize"><?= $cat==='manual'?'Manuale':ucfirst($cat) ?></h4>
      <?php if (count($items)): ?>
        <?php foreach ($items as $n): ?>
          <div class="card mb-2 shadow-sm <?= $n['statusClass'] ?>">
            <div class="card-body d-flex justify-content-between align-items-center">
              <div>
                <strong>
                  <?= $n['type']==='both'?'Dată & Km':($n['type']==='date'?'Dată':'Kilometri') ?>
                </strong>
                <?php if ($n['dateStatus']!=='upcoming'): ?>
                  <span class="badge badge-pill badge-<?=
                    $n['dateStatus']==='expired'?'danger':($n['dateStatus']==='today'?'warning':'info')
                  ?> ml-1"><?= ucfirst($n['dateStatus']) ?></span>
                <?php endif;?>
                <?php if ($n['kmStatus']==='expired'): ?>
                  <span class="badge badge-pill badge-danger ml-1">
                    Prag km atins (<?= htmlspecialchars($n['current_km']) ?> km)
                  </span>
                <?php endif;?>
                <div class="small text-muted">
                  <?= $n['trigger_date']?htmlspecialchars($n['trigger_date']):'' ?>
                  <?= $n['trigger_km']!==null?' / '.htmlspecialchars($n['trigger_km'].' km'):'' ?>
                </div>
                <?php if (!empty($n['note'])): ?>
                  <div class="mt-1"><?= nl2br(htmlspecialchars($n['note'])) ?></div>
                <?php endif;?>
              </div>
              <div class="text-right">
                <span class="badge badge-info mb-2"><?= htmlspecialchars("{$n['brand']} {$n['model']} ({$n['year']})") ?></span>
                <div class="small text-muted"><?= date('Y-m-d H:i',strtotime($n['created_at'])) ?></div>
                <div class="mt-2 btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary editBtn"
                          data-id="<?= $n['id'] ?>"
                          data-vehicle_id="<?= $n['vehicle_id'] ?>"
                          data-trigger_date="<?= htmlspecialchars($n['trigger_date'],ENT_QUOTES) ?>"
                          data-trigger_km="<?= $n['trigger_km'] ?>"
                          data-note="<?= htmlspecialchars($n['note'],ENT_QUOTES) ?>">
                    <i class="fas fa-edit"></i>
                  </button>
                  <form method="post" style="display:inline" onsubmit="return confirm('Ștergi notificarea?')">
                    <input type="hidden" name="form_type" value="delete_notification">
                    <input type="hidden" name="id" value="<?= $n['id']?>">
                    <button class="btn btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach;?>
      <?php else:?>
        <p class="text-muted">Nicio notificare în această categorie.</p>
      <?php endif;?>
    <?php endforeach;?>
  </div>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="notifications.php" class="modal-content" id="notifForm">
      <input type="hidden" name="form_type" id="formType" value="add_notification">
      <input type="hidden" name="id" id="notifId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="notifModalLabel">Adaugă notificare</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="notifVehicle">Vehicul</label>
          <select name="vehicle_id" id="notifVehicle" class="form-control" required>
            <option value="">— Selectează —</option>
            <?php foreach($vehicles as $v): ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group col-sm-6">
            <label for="triggerDate">Data</label>
            <input type="date" name="trigger_date" id="triggerDate" class="form-control">
          </div>
          <div class="form-group col-sm-6">
            <label for="triggerKm">Kilometri</label>
            <input type="number" name="trigger_km" id="triggerKm" class="form-control" min="0">
          </div>
        </div>
        <div class="form-group">
          <label for="notifNote">Descriere</label>
          <textarea name="note" id="notifNote" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-primary">Salvează</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// Reset modal for add
$('#notifModal').on('show.bs.modal', function (e) {
  $('#formType').val('add_notification');
  $('#notifForm')[0].reset();
  $('#notifModalLabel').text('Adaugă notificare');
});
// Populate modal for edit
$('.editBtn').click(function(){
  var btn = $(this);
  $('#formType').val('edit_notification');
  $('#notifId').val(btn.data('id'));
  $('#notifVehicle').val(btn.data('vehicle_id'));
  $('#triggerDate').val(btn.data('trigger_date'));
  $('#triggerKm').val(btn.data('trigger_km'));
  $('#notifNote').val(btn.data('note'));
  $('#notifModalLabel').text('Editează notificare');
  $('#notifModal').modal('show');
});
</script>

<style>
h4 { margin-top:2rem; color:#007bff; }
.card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.modal-dialog { max-width:500px; }
@media (max-width:576px) {
  .form-inline .form-control { width:100%; margin-bottom:0.5rem; }
}
</style>
