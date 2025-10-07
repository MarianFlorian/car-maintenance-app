<?php
// pages/combustibil.php
require __DIR__ . '/combustibil_logic.php';
include __DIR__ . '/../includes/header.php';
?>
<!-- Tesseract OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4.0.2/dist/tesseract.min.js"></script>

<div class="fuel-container">

  <!-- Page Header -->
  <div class="fuel-header">
    <h2>Realimentări</h2>
  </div>

  <!-- Feedback Alerts -->
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Stat Cards -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="card-title">Cost Total</div>
      <div class="card-value"><?= number_format($totalCostOverall,2) ?> RON</div>
    </div>
    <div class="stat-card">
      <div class="card-title">Consum Mediu</div>
      <div class="card-value"><?= number_format($averageConsumption,2) ?> L/100km</div>
    </div>
  </div>

  <!-- Filter Panel -->
  <form method="get" class="filter-panel form-inline">
    <div class="form-group mb-2">
      <label for="vehicle_id">Vehicul:</label>
      <select id="vehicle_id" name="vehicle_id" class="form-control mx-2">
        <option value="">Toate</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id']?>" <?= ($filterVehicle === $v['id']) ? 'selected':''?>>
            <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group mb-2">
      <label for="date_from">De la:</label>
      <input type="date" id="date_from" name="date_from" class="form-control mx-2"
             value="<?= htmlspecialchars($date_from)?>">
    </div>
    <div class="form-group mb-2">
      <label for="date_to">Până la:</label>
      <input type="date" id="date_to" name="date_to" class="form-control mx-2"
             value="<?= htmlspecialchars($date_to)?>">
    </div>
    <button type="submit" class="btn btn-primary mb-2 mx-2">Aplică filtre</button>
    <a href="combustibil.php" class="btn btn-secondary mb-2">Toate</a>
  </form>

  <!-- Add Button -->
  <div class="mb-3 text-right">
    <button
      type="button"
      id="addBtn"
      class="btn btn-success"
      data-toggle="modal"
      data-target="#fuelModal"
    >
      <i class="fas fa-plus"></i> Adaugă Realimentare
    </button>
  </div>

  <!-- Table -->
  <div class="fuel-table mb-4">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Data</th>
          <th>Ora</th>
          <th>Vehicul</th>
          <th>Km</th>
          <th>Tip</th>
          <th>Pret/L</th>
          <th>Cost</th>
          <th>Litri</th>
          <th>Benzinărie</th>
          <th>Full</th>
          <th>Acțiuni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fuelings as $f): ?>
          <tr
            data-id="<?= $f['id']?>"
            data-vehicle-id="<?= $f['vehicle_id']?>"
            data-date="<?= htmlspecialchars($f['date'])?>"
            data-time="<?= htmlspecialchars($f['time'])?>"
            data-km="<?= $f['km']?>"
            data-fuel-type="<?= htmlspecialchars($f['fuel_type'],ENT_QUOTES)?>"
            data-price-per-l="<?= $f['price_per_l']?>"
            data-total-cost="<?= $f['total_cost']?>"
            data-liters="<?= $f['liters']?>"
            data-gas-station="<?= htmlspecialchars($f['gas_station'],ENT_QUOTES)?>"
            data-full-tank="<?= $f['full_tank'] ? 'true' : 'false'?>"
          >
            <td><?= htmlspecialchars($f['date'])?></td>
            <td><?= htmlspecialchars($f['time'])?></td>
            <td><?= htmlspecialchars("{$f['brand']} {$f['model']} ({$f['year']})")?></td>
            <td><?= $f['km']?></td>
            <td><?= htmlspecialchars($f['fuel_type'])?></td>
            <td><?= $f['price_per_l']?></td>
            <td><?= $f['total_cost']?></td>
            <td><?= $f['liters']?></td>
            <td><?= htmlspecialchars($f['gas_station'])?></td>
            <td><?= $f['full_tank'] ? 'Da' : 'Nu'?></td>
            <td>
              <button class="btn btn-sm btn-primary editFuelBtn">
                <i class="fas fa-edit"></i>
              </button>
              <form method="post" style="display:inline" onsubmit="return confirm('Ștergi realimentarea?');">
                <input type="hidden" name="form_type" value="delete_fueling">
                <input type="hidden" name="fuel_id"    value="<?= $f['id']?>">
                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


</div><!-- /.fuel-container -->

<!-- Modal Add/Edit -->
<div class="modal fade" id="fuelModal" tabindex="-1" role="dialog" aria-labelledby="fuelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="fuelForm" method="post" class="modal-content" action="combustibil.php">
      <input type="hidden" name="form_type" id="formType" value="add_fueling">
      <input type="hidden" name="fuel_id"   id="fuelId"   value="">
      <div class="modal-header">
        <h5 class="modal-title" id="fuelModalLabel">Adaugă Realimentare</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <!-- Vehicul -->
        <div class="form-group">
          <label>Vehicul</label>
          <select name="vehicle_id" class="form-control" required>
            <option value="">— Selectează —</option>
            <?php foreach($vehicles as $v): ?>
              <option value="<?= $v['id']?>"><?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Data & Ora -->
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Data</label>
            <input type="date" name="date" class="form-control" required>
          </div>
          <div class="form-group col-md-6">
            <label>Ora</label>
            <input type="time" name="time" class="form-control" required>
          </div>
        </div>
        <!-- KM & Tip -->
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Kilometri (km)</label>
            <input type="number" name="km" class="form-control" required>
          </div>
          <div class="form-group col-md-6">
            <label>Tip Combustibil</label>
            <select name="fuel_type" class="form-control" required>
              <option value="">— Alege —</option>
              <option value="Benzină">Benzină</option>
              <option value="Diesel">Diesel</option>
              <option value="Electric">Electric (kWh)</option>
              <option value="GPL">GPL</option>
            </select>
          </div>
        </div>
        <!-- Full tank -->
        <div class="form-group form-check">
          <input type="checkbox" name="full_tank" id="full_tank" class="form-check-input">
          <label class="form-check-label" for="full_tank">Am umplut rezervorul complet</label>
        </div>
        <!-- Preț, Cost, Litri -->
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Pret/L</label>
            <input type="number" step="0.01" name="price_per_l" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Cost Total</label>
            <input type="number" step="0.01" name="total_cost" class="form-control" required>
          </div>
          <div class="form-group col-md-4">
            <label>Litri</label>
            <input type="number" step="0.01" name="liters" class="form-control" required>
          </div>
        </div>
        <!-- Bon fiscal -->
        <div class="form-group">
          <label>Bon fiscal (imagine)</label>
          <input type="file" id="receiptImage" accept="image/*" class="form-control-file mb-2">
          <button type="button" id="extractBtn" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-file-image"></i> Extrage date din bon
          </button>
          <small id="ocrStatus" class="form-text text-muted"></small>
        </div>
        <!-- Benzinărie -->
        <div class="form-group">
          <label>Benzinărie</label>
          <input type="text" name="gas_station" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Închide</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">Adaugă Realimentare</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- Our custom script -->
<script src="../assets/js/scripts.js"></script>
