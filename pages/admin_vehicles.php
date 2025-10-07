<?php
// pages/admin_vehicles.php
$requireAdmin = true;
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

$success = '';
$error   = '';

// 0) Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    if ($ft === 'add_vehicle') {
        $user_id = intval($_POST['user_id']);
        $brand   = trim($_POST['brand']);
        $model   = trim($_POST['model']);
        $year    = intval($_POST['year']);

        if (!$user_id || $brand === '' || $model === '' || $year <= 0) {
            $error = "Toate câmpurile sunt obligatorii şi User ID trebuie valid.";
        } else {
            $stmt = $conn->prepare("
              INSERT INTO vehicles (user_id, brand, model, year)
              VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issi", $user_id, $brand, $model, $year);
            if ($stmt->execute()) {
                $success = "Vehicul adăugat cu succes.";
            } else {
                $error = "Eroare la adăugare: " . $stmt->error;
            }
            $stmt->close();
        }

    } elseif ($ft === 'edit_vehicle') {
        $veh_id = intval($_POST['vehicle_id']);
        $brand  = trim($_POST['brand']);
        $model  = trim($_POST['model']);
        $year   = intval($_POST['year']);

        if (!$veh_id || $brand === '' || $model === '' || $year <= 0) {
            $error = "Toate câmpurile sunt obligatorii.";
        } else {
            $stmt = $conn->prepare("
              UPDATE vehicles
                 SET brand=?, model=?, year=?
               WHERE id=?
            ");
            $stmt->bind_param("ssii", $brand, $model, $year, $veh_id);
            if ($stmt->execute()) {
                $success = "Vehicul actualizat cu succes.";
            } else {
                $error = "Eroare la actualizare: " . $stmt->error;
            }
            $stmt->close();
        }

    } elseif ($ft === 'delete_vehicle') {
        $veh_id = intval($_POST['vehicle_id']);
        $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=?");
        $stmt->bind_param("i", $veh_id);
        if ($stmt->execute()) {
            $success = "Vehicul şters cu succes.";
        } else {
            if (strpos($stmt->error, 'foreign key') !== false) {
                $error = "Nu poţi şterge acest vehicul, există date dependente.";
            } else {
                $error = "Eroare la ştergere: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// 1) Fetch vehicles + owner email
$sql = "
  SELECT
    v.id         AS vehicle_id,
    v.brand, v.model, v.year,
    u.id         AS user_id,
    u.email
  FROM vehicles v
  JOIN users u ON u.id = v.user_id
  ORDER BY v.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2) Fetch all users for datalist
$usersStmt = $conn->query("SELECT id, email FROM users ORDER BY email");
$allUsers   = $usersStmt->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Gestionare Vehicule</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N"
    crossorigin="anonymous"
  >
  <link
    href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
    rel="stylesheet"
  >
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid mt-4">
  <div class="row">
    <div class="col-lg-9">
      <h2 class="mb-4">Gestionare Vehicule</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Controls: Add button, date range, and search -->
      <div class="mb-3 d-flex justify-content-between align-items-center">
        <!-- Add Vehicul -->
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addVehicleModal">
          <i class="fas fa-plus"></i> Adaugă Vehicul
        </button>



        <!-- Search bar on the right -->
        <input type="text"
               id="tableSearch"
               class="form-control w-25"
               placeholder="Caută ID, brand, model, email">
      </div>

      <!-- Vehicles table -->
      <table id="vehiclesTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>ID Vehicul</th>
            <th>User ID</th>
            <th>Email Utilizator</th>
            <th>Brand</th>
            <th>Model</th>
            <th>An</th>
            <th>Acțiuni</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($vehicles as $v): ?>
          <tr
            data-vid="<?= $v['vehicle_id'] ?>"
            data-userid="<?= $v['user_id'] ?>"
            data-brand="<?= htmlspecialchars($v['brand'], ENT_QUOTES) ?>"
            data-model="<?= htmlspecialchars($v['model'], ENT_QUOTES) ?>"
            data-year="<?= $v['year'] ?>"
          >
            <td><?= $v['vehicle_id'] ?></td>
            <td><?= $v['user_id'] ?></td>
            <td><?= htmlspecialchars($v['email']) ?></td>
            <td><?= htmlspecialchars($v['brand']) ?></td>
            <td><?= htmlspecialchars($v['model']) ?></td>
            <td><?= $v['year'] ?></td>
            <td>
              <button type="button"
                      class="btn btn-sm btn-warning editVehBtn"
                      data-toggle="modal"
                      data-target="#editVehicleModal">
                <i class="fas fa-edit"></i>
              </button>
              <form method="post" class="d-inline"
                    onsubmit="return confirm('Ştergi vehiculul #<?= $v['vehicle_id'] ?>?');">
                <input type="hidden" name="form_type"  value="delete_vehicle">
                <input type="hidden" name="vehicle_id" value="<?= $v['vehicle_id'] ?>">
                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" action="admin_vehicles.php">
      <input type="hidden" name="form_type" value="add_vehicle">
      <input type="hidden" name="user_id" id="add_user_id" value="">
      <div class="modal-header">
        <h5 class="modal-title">Adaugă Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>User (ID – Email)</label>
          <input list="usersList" id="addUserSearch" class="form-control" placeholder="ex: 42 – user@ex.com" required>
          <datalist id="usersList">
            <?php foreach($allUsers as $u): ?>
              <option data-id="<?= $u['id'] ?>"
                      value="<?= htmlspecialchars("{$u['id']} – {$u['email']}") ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-group">
          <label>Brand</label>
          <input type="text" name="brand" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" name="model" class="form-control" required>
        </div>
        <div class="form-group">
          <label>An</label>
          <input type="number" name="year" class="form-control"
                 min="1900" max="<?= date('Y') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button class="btn btn-success">Adaugă</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT VEHICLE MODAL -->
<div class="modal fade" id="editVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" action="admin_vehicles.php">
      <input type="hidden" name="form_type"  value="edit_vehicle">
      <input type="hidden" name="vehicle_id" id="edit_vehicle_id" value="">
      <div class="modal-header">
        <h5 class="modal-title">Editează Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Brand</label>
          <input type="text" name="brand" id="edit_brand" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" name="model" id="edit_model" class="form-control" required>
        </div>
        <div class="form-group">
          <label>An</label>
          <input type="number" name="year" id="edit_year" class="form-control"
                 min="1900" max="<?= date('Y') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button class="btn btn-warning">Salvează</button>
      </div>
    </form>
  </div>
</div>

<!-- JS libraries -->
<script
  src="https://code.jquery.com/jquery-3.5.1.min.js"
  integrity="sha384-ZvpUoO/+Pw5y+XE0jk0K8I0X58F3RJTJ63eFUsJovc9y4nHCqB6f+GO6zkRgZNpm"
  crossorigin="anonymous"></script>
<script
  src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"
  integrity="sha384-9/reFTGAW83EW2RDu3Uh2YAdxCxZ1ZZQcP6YkNf9tXKp4YfRvH+8abtTE1Pi6jizo"
  crossorigin="anonymous"></script>
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"
  integrity="sha384-+YQ4/BrkJr7F5Lk08yM6vR7Y1KPTl0Vx1p6nb98R+EyJ5bWFib0ej0LvYlaj9QX"
  crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function(){
  // Initialize DataTable without default search input
  var table = $('#vehiclesTable').DataTable({
    lengthMenu: [10,25,50,100],
    pageLength: 25,
    dom: 'rtip'  // remove default filter ("f")
  });

  // Custom search bar
  $('#tableSearch').on('keyup', function(){
    table.search(this.value).draw();
  });

  // Date range filter (based on "An" column, index 5)
  $.fn.dataTable.ext.search.push(function(settings, data){
    var min = $('#minDate').val();
    var max = $('#maxDate').val();
    var year = parseInt(data[5], 10) || 0;
    // interpret as January 1st of that year
    var date = new Date(year, 0, 1);

    if ((!min || date >= new Date(min)) &&
        (!max || date <= new Date(max))) {
      return true;
    }
    return false;
  });

  $('#minDate, #maxDate').on('change', function(){
    table.draw();
  });

  // Bind user_id from datalist
  $('#addUserSearch').on('input', function(){
    var val = $(this).val();
    var opt = $('#usersList option').filter((_,o)=>o.value===val);
    $('#add_user_id').val(opt.data('id')||'');
  });

  // Prefill edit modal
  $('#editVehicleModal').on('show.bs.modal', function(e){
    var btn = $(e.relatedTarget);
    var tr  = btn.closest('tr');
    $('#edit_vehicle_id').val(tr.data('vid'));
    $('#edit_brand').val(tr.data('brand'));
    $('#edit_model').val(tr.data('model'));
    $('#edit_year').val(tr.data('year'));
  });
});
</script>
<script>
  $(function(){
    // === Deschidere manuală modale ===
    // Adaugă
    $('.btn-success[data-toggle="modal"]').on('click', function(e){
      e.preventDefault();
      var tgt = $(this).attr('data-target'),
          $m  = $(tgt);
      $m.addClass('show').css('display','block').attr('aria-modal','true').removeAttr('aria-hidden');
      $('body').addClass('modal-open');
      $('<div class="modal-backdrop fade show"></div>').appendTo(document.body);
    });
    // Editare
    $('.editVehBtn').on('click', function(e){
      e.preventDefault();
      var $tr = $(this).closest('tr');
      $('#edit_vehicle_id').val($tr.data('vid'));
      $('#edit_brand').      val($tr.data('brand'));
      $('#edit_model').      val($tr.data('model'));
      $('#edit_year').       val($tr.data('year'));
      var $m = $('#editVehicleModal');
      $m.addClass('show').css('display','block').attr('aria-modal','true').removeAttr('aria-hidden');
      $('body').addClass('modal-open');
      $('<div class="modal-backdrop fade show"></div>').appendTo(document.body);
    });

    // === Închidere manuală modale ===
    $('.modal .close').on('click', function(e){
      e.preventDefault();
      var $m = $(this).closest('.modal');
      $m.removeClass('show').css('display','none').attr('aria-hidden','true').removeAttr('aria-modal');
      $('body').removeClass('modal-open');
      $('.modal-backdrop').remove();
    });
  });
</script>
</body>
</html>
