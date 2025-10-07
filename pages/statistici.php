<!-- statistici.php(OLD-trebuie sters)-->
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}
require '../includes/db.php';
$uid = $_SESSION['user_id'];

// 1) Parametri GET
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$days = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400 + 1);

// 2) Încarcă vehiculele și selectează
$vehicles = [];
$stmt = $conn->prepare("SELECT id, brand, model FROM vehicles WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $vehicles[$r['id']] = $r['brand'].' '.$r['model'];
}
$stmt->close();

$selected = $_GET['veh'] ?? array_keys($vehicles);
if (!is_array($selected)) $selected = [$selected];
$selected = array_map('intval', $selected);
$vehList = implode(',', $selected);

// 3) Funcții helper pentru multiple vehicule
function sumRangeMulti($conn, $table, $col, $dateCol, $from, $to, $uid, $vehList) {
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

// 4) Metrici General
$totalFuel    = sumRangeMulti($conn,'fuelings','total_cost','date',$date_from,$date_to,$uid,$vehList);
$totalService = sumRangeMulti($conn,'services','cost','date',$date_from,$date_to,$uid,$vehList);
$totalTax     = sumRangeMulti($conn,'taxes','amount','date_paid',$date_from,$date_to,$uid,$vehList);
$totalAll     = $totalFuel + $totalService + $totalTax;
$perDayAll    = $totalAll / $days;

// distanță totală
$stmt = $conn->prepare("
  SELECT COALESCE(MIN(km),0), COALESCE(MAX(km),0)
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$stmt->bind_result($minKm, $maxKm);
$stmt->fetch(); $stmt->close();
$distance     = max(0, $maxKm - $minKm);
$perKmAll     = $distance>0 ? $totalAll/$distance : 0;
$avgDailyDist = $distance/$days;

// Combustibil volum și consum
$totalLiters = sumRangeMulti($conn,'fuelings','liters','date',$date_from,$date_to,$uid,$vehList);
$avgCons     = $distance>0 ? $totalLiters/$distance*100 : 0;

// Service count și medie cost
$stmt = $conn->prepare("
  SELECT COUNT(*)
    FROM services
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$stmt->bind_result($serviceCount);
$stmt->fetch(); $stmt->close();
$avgServiceCost = $serviceCount>0 ? $totalService/$serviceCount : 0;

// Taxe count și medie cost
$stmt = $conn->prepare("
  SELECT COUNT(*)
    FROM taxes
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date_paid BETWEEN ? AND ?
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$stmt->bind_result($taxCount);
$stmt->fetch(); $stmt->close();
$avgTax = $taxCount>0 ? $totalTax/$taxCount : 0;

// 5) Date pentru grafice general
// a) Kilometraj în timp (toate alimentările)
$stmt = $conn->prepare("
  SELECT date, km FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   ORDER BY date, time
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();
$kmLabels=[]; $kmData=[];
while ($r=$res->fetch_assoc()) {
    $kmLabels[] = $r['date'];
    $kmData[]   = (int)$r['km'];
}
$stmt->close();

// b) Cheltuieli lunare general
function monthlySumMulti($conn,$table,$col,$dateCol,$from,$to,$uid,$vehList){
    $sql = "SELECT DATE_FORMAT($dateCol,'%Y-%m') AS m, SUM($col) AS s
              FROM $table
             WHERE user_id=? AND vehicle_id IN ($vehList)
               AND $dateCol BETWEEN ? AND ?
             GROUP BY m";
    $st = $conn->prepare($sql);
    $st->bind_param("iss", $uid, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $a=[];
    while ($r=$res->fetch_assoc()) {
        $a[$r['m']] = (float)$r['s'];
    }
    $st->close();
    return $a;
}
$mFuel    = monthlySumMulti($conn,'fuelings','total_cost','date',$date_from,$date_to,$uid,$vehList);
$mService = monthlySumMulti($conn,'services','cost','date',$date_from,$date_to,$uid,$vehList);
$mTaxes   = monthlySumMulti($conn,'taxes','amount','date_paid',$date_from,$date_to,$uid,$vehList);
$months   = array_unique(array_merge(array_keys($mFuel),array_keys($mService),array_keys($mTaxes)));
sort($months);
$barFuel=[]; $barServ=[]; $barTax=[];
foreach ($months as $m) {
    $barFuel[] = $mFuel[$m] ?? 0;
    $barServ[] = $mService[$m] ?? 0;
    $barTax[]  = $mTaxes[$m] ?? 0;
}

// 6) Date pentru grafice celelalte (combustibil, service, taxe)
// Combustibil: preț/l și consum L/100km pe zi
$stmt = $conn->prepare("
  SELECT date, AVG(price_per_l) AS avg_p, SUM(liters) AS tot_l
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   GROUP BY date ORDER BY date
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();
$priceLabels=[]; $priceData=[]; $litLabels=[]; $consData=[];
while ($r=$res->fetch_assoc()) {
    $priceLabels[] = $r['date'];
    $priceData[]   = round($r['avg_p'],2);
    $litLabels[]   = $r['date'];
    // consum L/100 la fiecare zi: litri / (km parcurs între zile) approximativ
    // vom folosi consum mediu simplu:
    $consData[]    = $distance>0 ? round($r['tot_l']/$distance*100,2) : 0;
}
$stmt->close();

// Service: cost și count per zi
$stmt = $conn->prepare("
  SELECT date, SUM(cost) AS tot, COUNT(*) AS cnt
    FROM services
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   GROUP BY date ORDER BY date
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res=$stmt->get_result();
$servLabels=[]; $servCost=[]; $servCountArr=[];
while ($r=$res->fetch_assoc()) {
    $servLabels[]   = $r['date'];
    $servCost[]     = (float)$r['tot'];
    $servCountArr[] = (int)$r['cnt'];
}
$stmt->close();

// Taxe: cost și count per zi
$stmt = $conn->prepare("
  SELECT date_paid AS date, SUM(amount) AS tot, COUNT(*) AS cnt
    FROM taxes
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date_paid BETWEEN ? AND ?
   GROUP BY date_paid ORDER BY date_paid
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res=$stmt->get_result();
$taxLabels=[]; $taxCost=[]; $taxCountArr=[];
while ($r=$res->fetch_assoc()) {
    $taxLabels[]   = $r['date'];
    $taxCost[]     = (float)$r['tot'];
    $taxCountArr[] = (int)$r['cnt'];
}
$stmt->close();

// Trimitem tot în JS
$js = [
  'date_from'=>$date_from,
  'date_to'=>$date_to,
  'kmLabels'=>$kmLabels,
  'kmData'=>$kmData,
  'months'=>$months,
  'barFuel'=>$barFuel,
  'barServ'=>$barServ,
  'barTax'=>$barTax,
  'priceLabels'=>$priceLabels,
  'priceData'=>$priceData,
  'litLabels'=>$litLabels,
  'consData'=>$consData,
  'servLabels'=>$servLabels,
  'servCost'=>$servCost,
  'servCount'=>$servCountArr,
  'taxLabels'=>$taxLabels,
  'taxCost'=>$taxCost,
  'taxCount'=>$taxCountArr,
];
$json = json_encode($js);
?>
<?php include '../includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid">
  <h2 class="my-4">Statistici</h2>

  <!-- Filtre -->
  <form method="get" class="form-inline mb-4">
    <input type="date" name="date_from" class="form-control mr-2" value="<?=htmlspecialchars($date_from)?>">
    <input type="date" name="date_to"   class="form-control mr-4" value="<?=htmlspecialchars($date_to)?>">
    <?php foreach ($vehicles as $id=>$lbl): ?>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" name="veh[]" value="<?=$id?>"
          <?=in_array($id,$selected)?'checked':''?>>
        <label class="form-check-label"><?=$lbl?></label>
      </div>
    <?php endforeach;?>
    <button class="btn btn-primary ml-3">Aplică</button>
  </form>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#combustibil">Combustibil</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#service">Service</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#taxe">Taxe</a></li>
  </ul>

  <div class="tab-content">

    <!-- GENERAL -->
    <div id="general" class="tab-pane fade show active">
      <h4>Prețuri</h4>
      <div class="row mb-4">
        <div class="col-sm-4">
          <div class="card bg-secondary text-white text-center mb-3">
            <div class="card-body"><h5>Total</h5><p class="h4"><?=number_format($totalAll,2)?> RON</p></div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card bg-secondary text-white text-center mb-3">
            <div class="card-body"><h5>Pe zi</h5><p class="h4"><?=number_format($perDayAll,2)?> RON</p></div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card bg-secondary text-white text-center mb-3">
            <div class="card-body"><h5>Pe km</h5><p class="h4"><?=number_format($perKmAll,2)?> RON</p></div>
          </div>
        </div>
      </div>
      <h4>Distanță</h4>
      <div class="row mb-4">
        <div class="col-sm-6">
          <div class="card bg-light text-center mb-3">
            <div class="card-body"><h5>Total</h5><p class="h4"><?=$distance?> km</p></div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="card bg-light text-center mb-3">
            <div class="card-body"><h5>Medie zilnică</h5><p class="h4"><?=number_format($avgDailyDist,2)?> km</p></div>
          </div>
        </div>
      </div>
      <div class="form-inline mb-3">
        <select id="genType" class="form-control mr-2">
          <option value="cost">Cheltuieli lunare</option>
          <option value="km">Kilometraj în timp</option>
        </select>
        <button id="genBtn" class="btn btn-success">Afișează</button>
      </div>
      <canvas id="generalChart" class="border rounded w-100" style="min-height:300px; display:none;"></canvas>
    </div>

    <!-- COMBUSTIBIL -->
    <div id="combustibil" class="tab-pane fade">
      <h4>Preț & Consum</h4>
      <div class="row mb-4">
        <div class="col-sm-4"><div class="card bg-info text-white text-center mb-3"><div class="card-body"><h5>Total Cost</h5><p class="h4"><?=number_format($totalFuel,2)?> RON</p></div></div></div>
        <div class="col-sm-4"><div class="card bg-info text-white text-center mb-3"><div class="card-body"><h5>Volum Total</h5><p class="h4"><?=number_format($totalLiters,2)?> L</p></div></div></div>
        <div class="col-sm-4"><div class="card bg-info text-white text-center mb-3"><div class="card-body"><h5>Consum Mediu</h5><p class="h4"><?=number_format($avgCons,2)?> L/100km</p></div></div></div>
      </div>
      <div class="form-inline mb-3">
        <select id="fuelType" class="form-control mr-2">
          <option value="price">Preț pe zi</option>
          <option value="cons">Consum pe zi</option>
        </select>
        <button id="fuelBtn" class="btn btn-success">Afișează</button>
      </div>
      <canvas id="fuelChart" class="border rounded w-100" style="min-height:300px; display:none;"></canvas>
    </div>

    <!-- SERVICE -->
    <div id="service" class="tab-pane fade">
      <h4>Servicii</h4>
      <div class="row mb-4">
        <div class="col-sm-4"><div class="card bg-warning text-white text-center mb-3"><div class="card-body"><h5>Total Cost</h5><p class="h4"><?=number_format($totalService,2)?> RON</p></div></div></div>
        <div class="col-sm-4"><div class="card bg-warning text-white text-center mb-3"><div class="card-body"><h5>Număr Intervenții</h5><p class="h4"><?=$serviceCount?></p></div></div></div>
        <div class="col-sm-4"><div class="card bg-warning text-white text-center mb-3"><div class="card-body"><h5>Medie Cost</h5><p class="h4"><?=number_format($avgServiceCost,2)?> RON</p></div></div></div>
      </div>
      <div class="form-inline mb-3">
        <select id="servType" class="form-control mr-2">
          <option value="cost">Cost zilnic</option>
          <option value="count">Număr zilnic</option>
        </select>
        <button id="servBtn" class="btn btn-success">Afișează</button>
      </div>
      <canvas id="serviceChart" class="border rounded w-100" style="min-height:300px; display:none;"></canvas>
    </div>

    <!-- TAXE -->
    <div id="taxe" class="tab-pane fade">
      <h4>Taxe</h4>
      <div class="row mb-4">
        <div class="col-sm-4"><div class="card bg-dark text-white text-center mb-3"><div class="card-body"><h5>Total Cost</h5><p class="h4"><?=number_format($totalTax,2)?> RON</p></div></div></div>
        <div class="col-sm-4"><div class="card bg-dark text-white text-center mb-3"><div class="card-body"><h5>Număr Taxe</h5><p class="h4"><?=$taxCount?></p></div></div></div>
        <div class="col-sm-4"><div class="card bg-dark text-white text-center mb-3"><div class="card-body"><h5>Medie Cost</h5><p class="h4"><?=number_format($avgTax,2)?> RON</p></div></div></div>
      </div>
      <div class="form-inline mb-3">
        <select id="taxType" class="form-control mr-2">
          <option value="cost">Cost zilnic</option>
          <option value="count">Număr zilnic</option>
        </select>
        <button id="taxBtn" class="btn btn-success">Afișează</button>
      </div>
      <canvas id="taxChart" class="border rounded w-100" style="min-height:300px; display:none;"></canvas>
    </div>

  </div>
</div>

<script>
  // datele PHP în JS
  const d = <?= $json ?>;

  // vom păstra referinţa la chartul curent
  let currentChart = null;

  // 1) Funcţii pentru generarea fiecărui chart
  function generateGeneral() {
    const type = document.getElementById('genType').value;
    const ctx  = document.getElementById('generalChart').getContext('2d');
    if (currentChart) currentChart.destroy();
    if (type === 'km') {
      currentChart = new Chart(ctx, {
        type: 'line',
        data: { labels: d.kmLabels,
                datasets: [{ label:'Kilometraj (km)', data:d.kmData, fill:false, tension:0.1 }]
              },
        options: { scales:{ y:{ beginAtZero:false } } }
      });
    } else {
      currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: d.months,
          datasets: [
            { label:'Combustibil', data:d.barFuel },
            { label:'Service',     data:d.barServ },
            { label:'Taxe',        data:d.barTax }
          ]
        },
        options:{ scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } } }
      });
    }
    document.getElementById('generalChart').style.display = 'block';
  }

  function generateFuel() {
    const type = document.getElementById('fuelType').value;
    const ctx  = document.getElementById('fuelChart').getContext('2d');
    if (currentChart) currentChart.destroy();
    if (type === 'price') {
      currentChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: d.priceLabels,
          datasets: [{ label:'Preț / L', data:d.priceData, fill:false, tension:0.1 }]
        },
        options:{ scales:{ y:{ beginAtZero:false } } }
      });
    } else {
      currentChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: d.litLabels,
          datasets: [{ label:'Consum L/100km', data:d.consData, fill:false, tension:0.1 }]
        },
        options:{ scales:{ y:{ beginAtZero:false } } }
      });
    }
    document.getElementById('fuelChart').style.display = 'block';
  }

  function generateService() {
    const type = document.getElementById('servType').value;
    const ctx  = document.getElementById('serviceChart').getContext('2d');
    if (currentChart) currentChart.destroy();
    currentChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: d.servLabels,
        datasets: [{
          label: type === 'count' ? 'Număr intervenții' : 'Cost (RON)',
          data:  type === 'count' ? d.servCount : d.servCost,
          fill:false, tension:0.1
        }]
      },
      options:{ scales:{ y:{ beginAtZero:true } } }
    });
    document.getElementById('serviceChart').style.display = 'block';
  }

  function generateTax() {
    const type = document.getElementById('taxType').value;
    const ctx  = document.getElementById('taxChart').getContext('2d');
    if (currentChart) currentChart.destroy();
    currentChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: d.taxLabels,
        datasets: [{
          label: type === 'count' ? 'Număr taxe' : 'Cost (RON)',
          data:  type === 'count' ? d.taxCount : d.taxCost,
          fill:false, tension:0.1
        }]
      },
      options:{ scales:{ y:{ beginAtZero:true } } }
    });
    document.getElementById('taxChart').style.display = 'block';
  }

  // 2) Când pagina s-a încărcat, ataşăm event-uri şi generăm General implicit
  document.addEventListener('DOMContentLoaded', function(){
    // butoanele existente (dacă vrei să laşi şi click-ul manual)
    document.getElementById('genBtn').addEventListener('click', generateGeneral);
    document.getElementById('fuelBtn').addEventListener('click', generateFuel);
    document.getElementById('servBtn').addEventListener('click', generateService);
    document.getElementById('taxBtn').addEventListener('click', generateTax);

    // atunci când dai click pe tab, apelăm funcţia corespunzătoare
    document.querySelector('a[href="#general"]').addEventListener('click', generateGeneral);
    document.querySelector('a[href="#combustibil"]').addEventListener('click', generateFuel);
    document.querySelector('a[href="#service"]').addEventListener('click', generateService);
    document.querySelector('a[href="#taxe"]').addEventListener('click', generateTax);

    // afișăm din start General
    generateGeneral();
  });
</script>


<?php include '../includes/footer.php'; ?>
