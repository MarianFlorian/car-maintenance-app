<?php
// pages/statistici_combustibil.php

session_start();
require __DIR__ . '/../includes/statistics_functions.php';
// statistics_functions.php already set:
//   $conn, $uid, $date_from, $date_to, $days, $vehicles (array), $selected (array), $vehList (comma-separated string)

$totalCost  = sumMulti(
    $conn,
    'fuelings',
    'total_cost',
    'date',
    $date_from,
    $date_to,
    $uid,
    $vehList           // <<-- pass the comma-string here
);

$totalVol   = sumMulti(
    $conn,
    'fuelings',
    'liters',
    'date',
    $date_from,
    $date_to,
    $uid,
    $vehList           // <<-- same here
);

$perDayCost = $totalCost / $days;

// 2) Distanta totală
$stmt = $conn->prepare("
  SELECT COALESCE(MIN(km),0), COALESCE(MAX(km),0)
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$stmt->bind_result($minKm, $maxKm);
$stmt->fetch();
$stmt->close();
$distance  = max(0, $maxKm - $minKm);
$perKmCost = $distance > 0 ? $totalCost / $distance : 0;

// 3) Calcul exact medie L/100km (algoritm ponderat pe segmente full_tank)
$avgPer100km   = 0.0;
$totalWeighted = 0.0;
$totalKmAll    = 0.0;

$sql = "
  SELECT vehicle_id, km, liters, full_tank
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   ORDER BY vehicle_id, date, time
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// grupăm pe vehicul
$byVeh = [];
foreach ($all as $r) {
    $byVeh[$r['vehicle_id']][] = $r;
}

// pentru fiecare vehicul, calculăm consum pe segmente
foreach ($byVeh as $fills) {
    $prevFull  = null;
    $accLiters = 0.0;
    foreach ($fills as $f) {
        $km     = (float)$f['km'];
        $liters = (float)$f['liters'];
        if ($f['full_tank']) {
            if ($prevFull !== null && $km > $prevFull) {
                $dist = $km - $prevFull;
                $cSeg = ($accLiters / $dist) * 100;
                $totalWeighted += $cSeg * $dist;
                $totalKmAll    += $dist;
            }
            $prevFull  = $km;
            $accLiters = 0.0;
        }
        $accLiters += $liters;
    }
}

if ($totalKmAll > 0) {
    $avgPer100km = round($totalWeighted / $totalKmAll, 2);
}

// 4) Timeseries pentru grafice
$stmt = $conn->prepare("
  SELECT date,
         SUM(total_cost)   AS totc,
         SUM(liters)       AS totl,
         AVG(price_per_l)  AS avgp
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   GROUP BY date
   ORDER BY date
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();

$dates  = [];
$costs  = [];
$vols   = [];
$prices = [];
while ($r = $res->fetch_assoc()) {
    $dates[]  = $r['date'];
    $costs[]  = round($r['totc'], 2);
    $vols[]   = round($r['totl'], 2);
    $prices[] = round($r['avgp'], 2);
}
$stmt->close();

$js = json_encode([
    'dates'  => $dates,
    'costs'  => $costs,
    'vols'   => $vols,
    'prices' => $prices,
]);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Statistici Combustibil</h2>
  <?php include __DIR__ . '/partials/filter_header.php'; ?>
  <?php include __DIR__ . '/partials/nav_stats.php'; ?>

  <div class="row mb-4">
    <?php foreach ([
      ['tit'=>'Preț total',      'val'=>number_format($totalCost,2).' RON'],
      ['tit'=>'Preț pe zi',      'val'=>number_format($perDayCost,2).' RON'],
      ['tit'=>'Preț pe km',      'val'=>number_format($perKmCost,2).' RON'],
      ['tit'=>'Volum total',     'val'=>number_format($totalVol,2).' L'],
      ['tit'=>'Consum mediu',    'val'=>number_format($avgPer100km,2).' L/100km'],
    ] as $c): ?>
      <div class="col-md-2 mb-3">
        <div class="card bg-info text-white text-center">
          <div class="card-body">
            <h6><?= $c['tit'] ?></h6>
            <p class="h5"><?= $c['val'] ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <h5>Cost & Volum pe zi</h5>
      <canvas id="fuelCostVolChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportFuelCv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveFuelCv"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
    <div class="col-md-6 mb-4">
      <h5>Preț per litru</h5>
      <canvas id="fuelPriceChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportFuelPr" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveFuelPr"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const d = <?= $js ?>;

  const chartCV = new Chart(
    document.getElementById('fuelCostVolChart').getContext('2d'),
    {
      type:'bar',
      data:{
        labels:d.dates,
        datasets:[
          { label:'Cost RON', data:d.costs, backgroundColor:'rgba(37,117,252,0.6)' },
          { label:'Litri',    data:d.vols,  backgroundColor:'rgba(106,17,203,0.6)' }
        ]
      },
      options:{ scales:{ y:{ beginAtZero:true } } }
    }
  );

  const chartP = new Chart(
    document.getElementById('fuelPriceChart').getContext('2d'),
    {
      type:'line',
      data:{
        labels:d.dates,
        datasets:[{
          label:'Preț/L',
          data:d.prices,
          fill:false,
          tension:0.1,
          borderColor:'#2575fc',
          pointBackgroundColor:'#2575fc'
        }]
      },
      options:{ scales:{ y:{ beginAtZero:false } } }
    }
  );

  function expCSV(labels, sets, name, hdr) {
    let csv = hdr + '\n';
    labels.forEach((dt,i)=>{
      csv += dt + ',' + sets.map(s=>s.data[i]).join(',') + '\n';
    });
    const blob = new Blob([csv],{type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = name; a.click();
    URL.revokeObjectURL(url);
  }
  document.getElementById('exportFuelCv').onclick = ()=> {
    expCSV(d.dates, [{data:d.costs},{data:d.vols}], 'fuel_cost_vol.csv', 'Data,Cost,Volum');
  };
  document.getElementById('exportFuelPr').onclick = ()=> {
    expCSV(d.dates, [{data:d.prices}], 'fuel_price.csv', 'Data,Preț/L');
  };

  document.getElementById('saveFuelCv').onclick = ()=> {
    const a=document.createElement('a');
    a.href=chartCV.toBase64Image(); a.download='fuel_cost_vol.png'; a.click();
  };
  document.getElementById('saveFuelPr').onclick = ()=> {
    const a=document.createElement('a');
    a.href=chartP.toBase64Image(); a.download='fuel_price.png'; a.click();
  };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
