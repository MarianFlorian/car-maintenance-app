<?php
// pages/calculator_cost.php

session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// 1) Dacă există user, aducem vehiculele; altfel, lista rămâne goală
$user_id = $_SESSION['user_id'] ?? null;
$vehicles = [];
if ($user_id) {
    $stmt = $conn->prepare("
      SELECT id, brand, model, year
        FROM vehicles
       WHERE user_id = ?
       ORDER BY brand, model, year
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 2) Calculăm consumul mediu per vehicul doar dacă suntem autentificat
$vehicleConsumptions = [];
if ($user_id) {
    foreach ($vehicles as $v) {
        $vid = $v['id'];
        $stmt = $conn->prepare("
          SELECT km, liters, full_tank
            FROM fuelings
           WHERE user_id = ? AND vehicle_id = ?
           ORDER BY date, time
        ");
        $stmt->bind_param("ii", $user_id, $vid);
        $stmt->execute();
        $fills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $totalWeighted = 0.0;
        $totalKm       = 0.0;
        $prevFullKm    = null;
        $accLiters     = 0.0;

        foreach ($fills as $f) {
            $km     = (float)$f['km'];
            $liters = (float)$f['liters'];
            if ($f['full_tank']) {
                if ($prevFullKm !== null && $km > $prevFullKm) {
                    $dist   = $km - $prevFullKm;
                    $cons   = $accLiters / $dist * 100;
                    $totalWeighted += $cons * $dist;
                    $totalKm       += $dist;
                }
                $prevFullKm = $km;
                $accLiters  = 0.0;
            }
            $accLiters += $liters;
        }

        $vehicleConsumptions[$vid] = $totalKm > 0
            ? round($totalWeighted / $totalKm, 2)
            : 0.0;
    }
}

// 3) Inițializăm variabilele
$error = '';

// Cost Călătorie
$distance      = $_POST['distance'] ?? '';
$vehCostSel    = $_POST['vehicle_id_cost'] ?? '';
$consCost      = $_POST['consumption_cost'] ?? '';
$priceCost     = $_POST['price_per_l_cost'] ?? '';
$litersCost    = $cost = $emCost = null;

// Buget ⇒ Distanță
$budget        = $_POST['budget'] ?? '';
$vehBudSel     = $_POST['vehicle_id_budget'] ?? '';
$consBud       = $_POST['consumption_budget'] ?? '';
$priceBud      = $_POST['price_per_l_budget'] ?? '';
$litersBud     = $distBud = $emBud = null;

// 4) Procesăm POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form_type'] ?? '';

    if ($form === 'cost') {
        if (!is_numeric($distance) || !is_numeric($priceCost)) {
            $error = 'Distanța și prețul trebuie să fie numerice.';
        } else {
            $distance  = (float)$distance;
            $priceCost = (float)$priceCost;
            // suprascriem consumul doar dacă suntem autentificat și există calcul
            if ($user_id && isset($vehicleConsumptions[$vehCostSel])) {
                $consCost = $vehicleConsumptions[$vehCostSel];
            }
            if (!is_numeric($consCost)) {
                $error = 'Consum invalid.';
            } else {
                $consCost   = (float)$consCost;
                $litersCost = $distance * $consCost / 100;
                $cost       = $litersCost * $priceCost;
                $emCost     = $litersCost * 2.31;
            }
        }
    }

    if ($form === 'budget') {
        if (!is_numeric($budget) || !is_numeric($priceBud)) {
            $error = 'Bugetul și prețul trebuie să fie numerice.';
        } else {
            $budget   = (float)$budget;
            $priceBud = (float)$priceBud;
            if ($user_id && isset($vehicleConsumptions[$vehBudSel])) {
                $consBud = $vehicleConsumptions[$vehBudSel];
            }
            if (!is_numeric($consBud) || $consBud == 0) {
                $error = 'Consum invalid sau insuficiente date.';
            } else {
                $consBud    = (float)$consBud;
                $litersBud  = $budget / $priceBud;
                $distBud    = $litersBud * 100 / $consBud;
                $emBud      = $litersBud * 2.31;
            }
        }
    }
}
?>

<style>
  .hero { background: linear-gradient(135deg,#6a11cb,#2575fc);
    color:#fff; text-align:center; padding:50px; margin-bottom:2rem; }
  .calc-card { border:none;border-radius:.75rem;box-shadow:0 .5rem 1rem rgba(0,0,0,0.1); }
  .calc-card .card-header { background:#fff;font-weight:600;border:none; }
  .btn-primary { background:#2575fc;border:none; }
  .result { background:#f7f9fc;padding:1rem;border-radius:.5rem;margin-top:1rem; }
</style>

<div class="container">
  <div class="row">
    
    <div class="col-md-9">
      <div class="hero">
        <h1>Calculator Cost & Distanță</h1>
    
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="row">
        <!-- Cost Călătorie -->
        <div class="col-lg-6 mb-4">
          <div class="card calc-card">
            <div class="card-header">Cost Călătorie</div>
            <div class="card-body">
              <form method="post">
                <input type="hidden" name="form_type" value="cost">

                <div class="form-group">
                  <label>Distanță (km)</label>
                  <input type="number" step="0.1" name="distance" class="form-control"
                         value="<?= htmlspecialchars($distance) ?>" required>
                </div>

                <?php if ($user_id && $vehicles): ?>
                  <div class="form-group">
                    <label>Vehicul</label>
                    <select id="vehCost" name="vehicle_id_cost" class="form-control">
                      <option value="">– manual –</option>
                      <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>"
                          <?= $vehCostSel == $v['id'] ? 'selected':''?>>
                          <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>

                <div class="form-group">
                  <label>Consum mediu (L/100km)</label>
                  <input id="consCost" type="number" step="0.1"
                         name="consumption_cost" class="form-control"
                         value="<?= htmlspecialchars($consCost) ?>" required>
                </div>

                <div class="form-group">
                  <label>Preț Carburant (RON/L)</label>
                  <input type="number" step="0.01" name="price_per_l_cost" class="form-control"
                         value="<?= htmlspecialchars($priceCost) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Calculează</button>
              </form>

              <?php if ($cost !== null): ?>
                <div class="result">
                  <p><strong>Litri:</strong> <?= number_format($litersCost,2) ?> L</p>
                  <p><strong>Cost:</strong> <?= number_format($cost,2) ?> RON</p>
                  <p><strong>Emisii:</strong> <?= number_format($emCost,2) ?> kg CO₂</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Buget ⇒ Distanță -->
        <div class="col-lg-6 mb-4">
          <div class="card calc-card">
            <div class="card-header">Distanță Posibilă</div>
            <div class="card-body">
              <form method="post">
                <input type="hidden" name="form_type" value="budget">

                <div class="form-group">
                  <label>Buget (RON)</label>
                  <input type="number" step="0.01" name="budget" class="form-control"
                         value="<?= htmlspecialchars($budget) ?>" required>
                </div>

                <?php if ($user_id && $vehicles): ?>
                  <div class="form-group">
                    <label>Vehicul</label>
                    <select id="vehBud" name="vehicle_id_budget" class="form-control">
                      <option value="">– manual –</option>
                      <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>"
                          <?= $vehBudSel == $v['id'] ? 'selected':''?>>
                          <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year']})")?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>

                <div class="form-group">
                  <label>Consum mediu (L/100km)</label>
                  <input id="consBud" type="number" step="0.1"
                         name="consumption_budget" class="form-control"
                         value="<?= htmlspecialchars($consBud) ?>" required>
                </div>

                <div class="form-group">
                  <label>Preț Carburant (RON/L)</label>
                  <input type="number" step="0.01" name="price_per_l_budget" class="form-control"
                         value="<?= htmlspecialchars($priceBud) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Calculează</button>
              </form>

              <?php if ($distBud !== null): ?>
                <div class="result">
                  <p><strong>Litri posibili:</strong> <?= number_format($litersBud,2) ?> L</p>
                  <p><strong>Distanță:</strong> <?= number_format($distBud,2) ?> km</p>
                  
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($user_id): ?>
<script>
// Date consumuri medii
const consData = <?= json_encode($vehicleConsumptions) ?>;
function bindCalc(selId, inpId) {
  const s = document.getElementById(selId), i = document.getElementById(inpId);
  s.addEventListener('change', ()=>{
    const v = s.value;
    if (consData[v] !== undefined) {
      i.value = consData[v];
      i.readOnly = true;
    } else {
      i.readOnly = false;
      i.value = '';
    }
  });
}
bindCalc('vehCost','consCost');
bindCalc('vehBud','consBud');
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
