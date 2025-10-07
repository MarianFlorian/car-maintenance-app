<?php 
// pages/admin_users.php
$requireAdmin = true;
require '../includes/auth.php';
require '../includes/db.php';

/**
 * Leagă dinamic parametrii la un mysqli_stmt fără unpacking de argumente.
 */
function bindParams(mysqli_stmt $stmt, string $types, array $params): void {
    $bind = [$types];
    foreach ($params as $i => $value) {
        ${"p{$i}"} = $value;
        $bind[] = &${"p{$i}"};
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$success = '';
$error   = '';

// 0) Handle POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    // --- ADD USER ---
    if ($ft === 'add_user') {
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm_password'];
        $role     = $_POST['role'] === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalid.";
        } elseif ($password !== $confirm) {
            $error = "Parolele nu se potrivesc.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Email deja înregistrat.";
            }
            $stmt->close();
        }

        if (!$error) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
              INSERT INTO users (email, password, role)
              VALUES (?, ?, ?)
            ");
            $stmt->bind_param("sss", $email, $hash, $role);
            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                $success = "Utilizator adăugat cu succes.";

                // optional: adaugă vehicul pentru acest user
                $vb = trim($_POST['vehicle_brand'] ?? '');
                $vm = trim($_POST['vehicle_model'] ?? '');
                $vy = intval($_POST['vehicle_year'] ?? 0);
                if ($vb !== '' && $vm !== '' && $vy > 0) {
                    $iv = $conn->prepare("INSERT INTO vehicles (user_id, brand, model, year) VALUES (?, ?, ?, ?)");
                    $iv->bind_param("issi", $newUserId, $vb, $vm, $vy);
                    $iv->execute();
                    $iv->close();
                    $success .= " Vehicul adăugat.";
                }
            } else {
                $error = "Eroare la adăugare: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- EDIT USER ---
    elseif ($ft === 'edit_user') {
        $user_id = intval($_POST['user_id']);
        $email   = trim($_POST['email']);
        $role    = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $new_pw  = $_POST['new_password'];
        $conf_pw = $_POST['confirm_password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalid.";
        } elseif ($user_id === $_SESSION['user_id'] && $role !== 'admin') {
            $error = "Nu poți schimba propriul rol din admin.";
        }

        if (!$error) {
            $stmt = $conn->prepare("UPDATE users SET email=?, role=? WHERE id=?");
            $stmt->bind_param("ssi", $email, $role, $user_id);
            if ($stmt->execute()) {
                $success = "Utilizator actualizat.";
            } else {
                $error = "Eroare la actualizare: " . $stmt->error;
            }
            $stmt->close();
        }

        // schimbă parola dacă a fost introdusă
        if (!$error && $new_pw !== '') {
            if (strlen($new_pw) < 6) {
                $error = "Parola trebuie să aibă minim 6 caractere.";
            } elseif ($new_pw !== $conf_pw) {
                $error = "Parolele nu se potrivesc.";
            } else {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $hash, $user_id);
                if ($stmt->execute()) {
                    $success .= " Parolă actualizată.";
                } else {
                    $error = "Eroare la schimbarea parolei: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // --- DELETE USER ---
    elseif ($ft === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        if ($user_id === $_SESSION['user_id']) {
            $error = "Nu poți șterge contul curent.";
        } else {
            $delV = $conn->prepare("DELETE FROM vehicles WHERE user_id = ?");
            $delV->bind_param("i", $user_id);
            $delV->execute();
            $delV->close();

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "Utilizator și vehiculele aferente au fost șterse cu succes.";
            } else {
                $error = "Eroare la ștergere: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- ADD VEHICLE TO USER ---
    elseif ($ft === 'add_vehicle_user') {
        $user_id = intval($_POST['user_vehicle_id']);
        $vb = trim($_POST['vehicle_brand'] ?? '');
        $vm = trim($_POST['vehicle_model'] ?? '');
        $vy = intval($_POST['vehicle_year'] ?? 0);
        if (!$user_id || $vb === '' || $vm === '' || $vy <= 0) {
            $error = "Toate câmpurile pentru vehicul sunt obligatorii.";
        } else {
            $iv = $conn->prepare("INSERT INTO vehicles (user_id, brand, model, year) VALUES (?, ?, ?, ?)");
            $iv->bind_param("issi", $user_id, $vb, $vm, $vy);
            if ($iv->execute()) {
                $success = "Vehicul adăugat utilizatorului.";
            } else {
                $error = "Eroare la adăugare vehicul: " . $iv->error;
            }
            $iv->close();
        }
    }

    // --- EDIT VEHICLE ---
    elseif ($ft === 'edit_vehicle_user') {
        $veh_id = intval($_POST['vehicle_id']);
        $vb = trim($_POST['brand'] ?? '');
        $vm = trim($_POST['model'] ?? '');
        $vy = intval($_POST['year'] ?? 0);
        if (!$veh_id || $vb === '' || $vm === '' || $vy <= 0) {
            $error = "Toate câmpurile pentru vehicul sunt obligatorii.";
        } else {
            $uv = $conn->prepare("UPDATE vehicles SET brand=?, model=?, year=? WHERE id=?");
            $uv->bind_param("ssii", $vb, $vm, $vy, $veh_id);
            if ($uv->execute()) {
                $success = "Vehicul actualizat.";
            } else {
                $error = "Eroare la actualizare vehicul: " . $uv->error;
            }
            $uv->close();
        }
    }
}

// 1) Preluăm filtrele GET (pentru compatibilitate, dar client-side DataTables va face filtrarea)
$search    = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

// 2) Construim WHERE dinamic pentru interogarea inițială
$where   = [];
$params  = [];
$types   = '';

if ($search !== '') {
    $where[]    = "(CAST(u.id AS CHAR) LIKE ? OR u.email LIKE ?)";
    $types     .= 'ss';
    $params[]   = "%{$search}%";
    $params[]   = "%{$search}%";
}
if ($date_from !== '') {
    $where[]   = "u.created_at >= ?";
    $types    .= 's';
    $params[]  = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $where[]   = "u.created_at <= ?";
    $types    .= 's';
    $params[]  = $date_to   . ' 23:59:59';
}

$sql = "
  SELECT
    u.id, u.email, u.role, u.created_at,
    COUNT(v.id) AS vehicles_count
  FROM users u
  LEFT JOIN vehicles v ON v.user_id = u.id
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " GROUP BY u.id
          ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) bindParams($stmt, $types, $params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4) Pregătim datele vehiculelor pentru JS
$userIds = array_column($users, 'id');
if ($userIds) {
    $in  = implode(',', array_fill(0, count($userIds), '?'));
    $vehSql = "SELECT id, user_id, brand, model, year FROM vehicles WHERE user_id IN ($in)";
    $vehStmt = $conn->prepare($vehSql);
    bindParams($vehStmt, str_repeat('i', count($userIds)), $userIds);
    $vehStmt->execute();
    $rawVs = $vehStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $vehStmt->close();
    $vehiclesByUser = [];
    foreach ($rawVs as $v) {
        $vehiclesByUser[$v['user_id']][] = $v;
    }
} else {
    $vehiclesByUser = [];
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Gestionare Utilizatori</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="container-fluid mt-4">
  <div class="row">
    <div class="col-md-9">
      <h2>Gestionare Utilizatori</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Controls: Add user, date range, search -->
      <div class="mb-3 d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addUserModal">
          <i class="fas fa-user-plus"></i> Adaugă Utilizator
        </button>
        <div class="d-flex align-items-center">
          <label class="mr-2 mb-0">Perioadă:</label>
          <input type="date" id="minDate" class="form-control mr-2">
          <input type="date" id="maxDate" class="form-control">
        </div>
        <input type="text" id="tableSearch" class="form-control w-25"
               placeholder="Caută ID, email, rol">
      </div>

      <table id="usersTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>ID</th><th>Email</th><th>Rol</th><th>Creat la</th><th># Vehicule</th><th>Acțiuni</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <td><?= $u['vehicles_count'] ?></td>
            <td>
              <button type="button"
                      class="btn btn-sm btn-warning editUserBtn"
                      data-toggle="modal"
                      data-target="#userDetailsModal"
                      data-id="<?= $u['id'] ?>"
                      data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                      data-role="<?= $u['role'] ?>">
                <i class="fas fa-edit"></i>
              </button>
              <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Ștergi utilizatorul?');">
                  <input type="hidden" name="form_type" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" action="admin_users.php">
      <input type="hidden" name="form_type" value="add_user">
      <div class="modal-header">
        <h5 class="modal-title">Adaugă Utilizator</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Email</label>
          <input type="email" name="email" class="form-control" required></div>
        <div class="form-group"><label>Parolă</label>
          <input type="password" name="password" class="form-control" required></div>
        <div class="form-group"><label>Confirmă Parolă</label>
          <input type="password" name="confirm_password" class="form-control" required></div>
        <div class="form-group"><label>Rol</label>
          <select name="role" class="form-control">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select></div>
        <hr>
        <h6>Adaugă vehicul (opțional)</h6>
        <div class="form-group"><label>Brand</label>
          <input type="text" name="vehicle_brand" class="form-control"></div>
        <div class="form-group"><label>Model</label>
          <input type="text" name="vehicle_model" class="form-control"></div>
        <div class="form-group"><label>An</label>
          <input type="number" name="vehicle_year" class="form-control" min="1900" max="<?= date('Y') ?>"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-primary">Adaugă</button>
      </div>
    </form>
  </div>
</div>

<!-- User Details / Edit Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalii Utilizator</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <!-- EDIT USER -->
        <form method="post" action="admin_users.php" id="editUserForm">
          <input type="hidden" name="form_type" value="edit_user">
          <input type="hidden" name="user_id" id="detailUserId" value="">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Email</label>
              <input type="email" name="email" id="detailEmail" class="form-control" required></div>
            <div class="form-group col-md-6"><label>Rol</label>
              <select name="role" id="detailRole" class="form-control">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Parolă Nouă (opțional)</label>
              <input type="password" name="new_password" class="form-control"></div>
            <div class="form-group col-md-6"><label>Confirmă Parolă</label>
              <input type="password" name="confirm_password" class="form-control"></div>
          </div>
          <button type="submit" class="btn btn-primary mb-3">Salvează Modificări Utilizator</button>
        </form>

        <hr>

        <!-- VEHICLES LIST -->
        <h6>Vehicule</h6>
        <table class="table table-sm mb-3" id="detailVehiclesTable">
          <thead><tr><th>ID</th><th>Brand</th><th>Model</th><th>An</th><th>Acțiuni</th></tr></thead>
          <tbody></tbody>
        </table>

        <!-- ADD VEHICLE TO USER -->
        <form method="post" action="admin_users.php" id="addVehicleForm">
          <input type="hidden" name="form_type" value="add_vehicle_user">
          <input type="hidden" name="user_vehicle_id" id="addUserVehicleId" value="">
          <div class="form-row">
            <div class="form-group col-md-4"><label>Brand</label>
              <input type="text" name="vehicle_brand" class="form-control" required></div>
            <div class="form-group col-md-4"><label>Model</label>
              <input type="text" name="vehicle_model" class="form-control" required></div>
            <div class="form-group col-md-4"><label>An</label>
              <input type="number" name="vehicle_year" class="form-control" min="1900" max="<?= date('Y') ?>" required></div>
          </div>
          <button type="submit" class="btn btn-success">Adaugă Vehicul</button>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" action="admin_users.php">
      <input type="hidden" name="form_type" value="edit_vehicle_user">
      <input type="hidden" name="vehicle_id" id="editVehId" value="">
      <div class="modal-header">
        <h5 class="modal-title">Editează Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Brand</label>
          <input type="text" name="brand" id="editVehBrand" class="form-control" required></div>
        <div class="form-group"><label>Model</label>
          <input type="text" name="model" id="editVehModel" class="form-control" required></div>
        <div class="form-group"><label>An</label>
          <input type="number" name="year" id="editVehYear" class="form-control" min="1900" max="<?= date('Y') ?>" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-warning">Salvează Vehicul</button>
      </div>
    </form>
  </div>
</div>

<!-- JS includes -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>

<script>
  // Datele vehiculelor pentru modaluri
  const vehiclesData = <?= json_encode($vehiclesByUser, JSON_HEX_TAG) ?>;
</script>

<script>
$(function(){
  // Initialize DataTable fără filter box nativ
  var table = $('#usersTable').DataTable({
    lengthMenu: [10,25,50,100],
    pageLength: 25,
    dom: 'rtip'
  });

  // Search custom
  $('#tableSearch').on('keyup', function(){
    table.search(this.value).draw();
  });

  // Filtru perioada ("Creat la", coloana index 3)
  $.fn.dataTable.ext.search.push(function(settings, data){
    var min = $('#minDate').val(),
        max = $('#maxDate').val(),
        date = new Date(data[3]);
    return (!min || date >= new Date(min)) && (!max || date <= new Date(max));
  });
  $('#minDate, #maxDate').on('change', function(){ table.draw(); });

  // Când deschidem modalul Detalii Utilizator
  $('#userDetailsModal').on('show.bs.modal', function(e){
    var btn   = $(e.relatedTarget);
    var uid   = btn.data('id');
    var email = btn.data('email');
    var role  = btn.data('role');

    // Setăm câmpurile de edit user
    $('#detailUserId').val(uid);
    $('#detailEmail').val(email);
    $('#detailRole').val(role);

    // Setăm hidden-ul pentru addVehicleForm
    $('#addUserVehicleId').val(uid);

    // Populăm tabel vehicule
    var vs = vehiclesData[uid] || [];
    var tbody = $('#detailVehiclesTable tbody').empty();
    vs.forEach(function(v){
      tbody.append(
        '<tr data-vid="'+v.id+'" data-brand="'+v.brand+
        '" data-model="'+v.model+'" data-year="'+v.year+'">'+
          '<td>'+v.id+'</td><td>'+v.brand+'</td><td>'+v.model+'</td><td>'+v.year+'</td>'+
          '<td>'+
            '<button class="btn btn-sm btn-warning editVehBtn" '+
                    'data-toggle="modal" data-target="#editVehicleModal" '+
                    'data-id="'+v.id+'" data-brand="'+v.brand+'" '+
                    'data-model="'+v.model+'" data-year="'+v.year+'">'+
              '<i class="fas fa-edit"></i>'+
            '</button>'+
          '</td>'+
        '</tr>'
      );
    });
  });

  // Când deschidem modalul Edit Vehicle
  $('#editVehicleModal').on('show.bs.modal', function(e){
    var btn = $(e.relatedTarget);
    $('#editVehId').val(btn.data('id'));
    $('#editVehBrand').val(btn.data('brand'));
    $('#editVehModel').val(btn.data('model'));
    $('#editVehYear').val(btn.data('year'));
  });
});
</script>
</body>
</html>
