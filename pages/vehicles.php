<?php
// pages/vehicles.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require '../includes/db.php';

$success = '';
$error   = '';
$user_id = $_SESSION['user_id'];

// 1) PROCESS POST (add/edit/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    if ($ft === 'add_vehicle') {
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $year  = intval($_POST['year']);
        if (!$brand || !$model || !$year) {
            $error = "Completați toate câmpurile.";
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
        $id    = intval($_POST['vehicle_id']);
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $year  = intval($_POST['year']);
        if (!$brand || !$model || !$year) {
            $error = "Completați toate câmpurile.";
        } else {
            $stmt = $conn->prepare("
              UPDATE vehicles
                 SET brand=?, model=?, year=?
               WHERE id=? AND user_id=?
            ");
            $stmt->bind_param("ssiii", $brand, $model, $year, $id, $user_id);
            if ($stmt->execute()) {
                $success = "Vehicul actualizat cu succes.";
            } else {
                $error = "Eroare la actualizare: " . $stmt->error;
            }
            $stmt->close();
        }

    } elseif ($ft === 'delete_vehicle') {
        $id = intval($_POST['vehicle_id']);
        // Delete dependents
        foreach (['fuelings','services','taxes','documents','notifications'] as $tbl) {
            $stmt = $conn->prepare("DELETE FROM {$tbl} WHERE vehicle_id=? AND user_id=?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        // Delete vehicle
        $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        if ($stmt->execute()) {
            $success = "Vehicul și toate datele asociate au fost șterse.";
        } else {
            $error = "Eroare la ștergere: " . $stmt->error;
        }
        $stmt->close();
    }
}

// 2) FETCH vehicles
$stmt = $conn->prepare("
  SELECT id, brand, model, year 
    FROM vehicles 
   WHERE user_id=? 
ORDER BY id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/dashboard.css">

<div class="container-fluid p-0">
  <!-- Hero -->
  <div class="dashboard-hero" style="background: linear-gradient(135deg,rgb(0, 86, 120),rgb(0, 37, 67));">
    <div class="hero-content">
      <h1>Gestionare Vehicule</h1>
      <p>Toate mașinile tale, într-un singur loc.</p>
    </div>
  </div>

  <div class="px-4">
    <!-- Action Card -->
    <div class="action-card mb-4">
      <i class="fas fa-car-side action-icon"></i>
      <div>
        <div class="action-label">Adaugă vehicul nou</div>
        <div class="action-info">Completează marca, modelul și anul</div>
      </div>
      <button class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#addVehicleModal">
        <i class="fas fa-plus"></i> Adaugă
      </button>
    </div>

    <!-- Section Heading -->
    <h2 class="section-heading">Vehiculele Tale</h2>

    <!-- Vehicles Grid -->
    <div class="row g-4 mb-5">
      <?php foreach ($vehicles as $v): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <i class="fas fa-car-side kpi-icon mb-3" style="font-size:2rem;color:#2575fc;"></i>
              <h5 class="kpi-title"><?= htmlspecialchars("{$v['brand']} {$v['model']}") ?></h5>
              <p class="text-muted mb-4">An: <?= $v['year'] ?></p>
              <div class="mt-auto d-flex justify-content-between">
                <button class="btn btn-sm btn-outline-warning editBtn"
                        data-id="<?= $v['id'] ?>"
                        data-brand="<?= htmlspecialchars($v['brand'],ENT_QUOTES) ?>"
                        data-model="<?= htmlspecialchars($v['model'],ENT_QUOTES) ?>"
                        data-year="<?= $v['year'] ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger deleteBtn"
                        data-id="<?= $v['id'] ?>"
                        data-label="<?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})",ENT_QUOTES) ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($vehicles)): ?>
        <div class="col-12">
          <div class="alert alert-info">Nu ai niciun vehicul înregistrat.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Add Vehicle Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="form_type" value="add_vehicle">
      <div class="modal-header">
        <h5 class="modal-title">Adaugă Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Marcă</label>
          <input type="text" name="brand" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" name="model" class="form-control" required>
        </div>
        <div class="form-group">
          <label>An</label>
          <input type="number" name="year" class="form-control" min="1900" max="<?= date('Y') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-primary">Salvează</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" id="editVehicleForm">
      <input type="hidden" name="form_type" value="edit_vehicle">
      <input type="hidden" name="vehicle_id" id="editVehicleId">
      <div class="modal-header">
        <h5 class="modal-title">Editează Vehicul</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Marcă</label>
          <input type="text" name="brand" class="form-control" id="editBrand" required>
        </div>
        <div class="form-group">
          <label>Model</label>
          <input type="text" name="model" class="form-control" id="editModel" required>
        </div>
        <div class="form-group">
          <label>An</label>
          <input type="number" name="year" class="form-control" id="editYear" min="1900" max="<?= date('Y') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-warning">Actualizează</button>
      </div>
    </form>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content" id="deleteVehicleForm">
      <input type="hidden" name="form_type" value="delete_vehicle">
      <input type="hidden" name="vehicle_id" id="deleteVehicleId">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          Confirmare Ștergere
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>
          Sigur dorești să ștergi mașina
          <strong id="deleteVehicleLabel"></strong>?
        </p>
        <p class="text-warning small">
          Toate datele asociate (alimentări, service, taxe, documente, notificări) vor fi șterse definitiv.
        </p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Anulează</button>
        <button type="submit" class="btn btn-danger">Șterge definitiv</button>
      </div>
    </form>
  </div>
</div>

<script>
// Edit modal
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    const tr = btn.closest('tr');
    document.getElementById('editVehicleId').value = btn.dataset.id;
    document.getElementById('editBrand').value     = btn.dataset.brand;
    document.getElementById('editModel').value     = btn.dataset.model;
    document.getElementById('editYear').value      = btn.dataset.year;
    $('#editVehicleModal').modal('show');
  });
});

// Delete confirmation modal
document.querySelectorAll('.deleteBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('deleteVehicleId').value    = btn.dataset.id;
    document.getElementById('deleteVehicleLabel').innerText = btn.dataset.label;
    $('#confirmDeleteModal').modal('show');
  });
});
</script>
