<!-- statistici_general.php-->
<?php
require __DIR__ . '/../includes/statistics_functions.php';
$active = 'general';

// 1) Agregate
$totalFuel    = sumMulti($conn,'fuelings','total_cost','date',$date_from,$date_to,$uid,$vehList);
$totalService = sumMulti($conn,'services','cost','date',$date_from,$date_to,$uid,$vehList);
$totalTax     = sumMulti($conn,'taxes','amount','date_paid',$date_from,$date_to,$uid,$vehList);
$totalAll     = $totalFuel + $totalService + $totalTax;
$perDayAll    = $totalAll / $days;

// 2) Distanță și medii
$stmt = $conn->prepare("
  SELECT COALESCE(MIN(km),0), COALESCE(MAX(km),0)
    FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
");
$stmt->bind_param("iss",$uid,$date_from,$date_to);
$stmt->execute();
$stmt->bind_result($minKm,$maxKm);
$stmt->fetch(); $stmt->close();
$distance    = max(0, $maxKm - $minKm);
$perKmAll    = $distance>0 ? $totalAll/$distance : 0;
$avgKmPerDay = $distance>0 ? $distance/$days      : 0;

// 3) Cheltuieli lunare
function monthlySum($conn,$table,$col,$dateCol,$f,$t,$uid,$v){
  $sql="SELECT DATE_FORMAT($dateCol,'%Y-%m') m, SUM($col) s
        FROM $table
       WHERE user_id=? AND vehicle_id IN ($v)
         AND $dateCol BETWEEN ? AND ?
       GROUP BY m";
  $st=$conn->prepare($sql);
  $st->bind_param("iss",$uid,$f,$t);
  $st->execute();
  $res=$st->get_result(); $o=[];
  while($r=$res->fetch_assoc()) $o[$r['m']] = (float)$r['s'];
  $st->close();
  return $o;
}
$mF=monthlySum($conn,'fuelings','total_cost','date',$date_from,$date_to,$uid,$vehList);
$mS=monthlySum($conn,'services','cost','date',$date_from,$date_to,$uid,$vehList);
$mT=monthlySum($conn,'taxes','amount','date_paid',$date_from,$date_to,$uid,$vehList);
$months = array_unique(array_merge(array_keys($mF),array_keys($mS),array_keys($mT)));
sort($months);
$barFuel=[]; $barServ=[]; $barTax=[];
foreach($months as $m){
  $barFuel[]= $mF[$m] ?? 0;
  $barServ[]= $mS[$m] ?? 0;
  $barTax[] = $mT[$m] ?? 0;
}

// 4) Kilometraj în timp
$stmt=$conn->prepare("
  SELECT date, km FROM fuelings
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   ORDER BY date,time
");
$stmt->bind_param("iss",$uid,$date_from,$date_to);
$stmt->execute();
$res=$stmt->get_result();
$kmLabels=[]; $kmData=[];
while($r=$res->fetch_assoc()){
  $kmLabels[]=$r['date'];
  $kmData[]  =(int)$r['km'];
}
$stmt->close();

// 5) Parametri JS
$js = json_encode([
  'months'=>$months,
  'barFuel'=>$barFuel,
  'barServ'=>$barServ,
  'barTax'=>$barTax,
  'kmLabels'=>$kmLabels,
  'kmData'=>$kmData,
]);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Statistici Generale</h2>
  <?php include __DIR__ . '/partials/filter_header.php'; ?>
  <?php include __DIR__ . '/partials/nav_stats.php'; ?>

  <div class="row mb-4">
    <?php foreach ([
      ['bg'=>'secondary','tit'=>'Total Cheltuieli','val'=>number_format($totalAll,2).' RON'],
      ['bg'=>'secondary','tit'=>'Pe zi','val'=>number_format($perDayAll,2).' RON'],
      ['bg'=>'secondary','tit'=>'Pe km','val'=>number_format($perKmAll,2).' RON'],
      ['bg'=>'secondary','tit'=>'Distanță totală','val'=>$distance.' km'],
      ['bg'=>'secondary','tit'=>'Medie km/zi','val'=>number_format($avgKmPerDay,2).' km'],
    ] as $c): ?>
      <div class="col-md-2 mb-3">
        <div class="card bg-<?= $c['bg'] ?> text-white text-center"><div class="card-body">
          <h6><?= $c['tit'] ?></h6>
          <p class="h5"><?= $c['val'] ?></p>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <h5>Cheltuieli lunare (stacked)</h5>
      <canvas id="generalCostChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportGenCostCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveGenCostImg"  class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
    <div class="col-md-6 mb-4">
      <h5>Kilometraj în timp</h5>
      <canvas id="generalKmChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportGenKmCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveGenKmImg"  class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const d = <?= $js ?>;

  // 1) Grafice
  const genCostCtx = document.getElementById('generalCostChart').getContext('2d');
  const genKmCtx   = document.getElementById('generalKmChart').getContext('2d');

  const genCostChart = new Chart(genCostCtx, {
    type:'bar',
    data:{labels:d.months,datasets:[
      {label:'Combustibil',data:d.barFuel},
      {label:'Service',    data:d.barServ},
      {label:'Taxe',       data:d.barTax}
    ]},
    options:{scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}}}
  });

  const genKmChart = new Chart(genKmCtx, {
    type:'line',
    data:{labels:d.kmLabels,datasets:[
      {label:'km',data:d.kmData,fill:false,tension:0.1}
    ]},
    options:{scales:{y:{beginAtZero:false}}}
  });

  // 2) Export & salvare
  function exportCsv(labels, datasets, filename, header){
    let csv = header + '\n';
    labels.forEach((x,i)=>{
      csv += x + ',' + datasets.map(ds=>ds.data[i]).join(',') + '\n';
    });
    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
  }

  document.getElementById('exportGenCostCsv').addEventListener('click', ()=>{
    exportCsv(
      d.months,
      [
        {label:'Combustibil',data:d.barFuel},
        {label:'Service',    data:d.barServ},
        {label:'Taxe',       data:d.barTax}
      ],
      'general_cost.csv',
      'Luna,Combustibil,Service,Taxe'
    );
  });
  document.getElementById('exportGenKmCsv').addEventListener('click', ()=>{
    exportCsv(
      d.kmLabels,
      [{label:'km',data:d.kmData}],
      'general_km.csv',
      'Data,km'
    );
  });

  document.getElementById('saveGenCostImg').addEventListener('click', ()=>{
    const a = document.createElement('a');
    a.href = genCostChart.toBase64Image(); a.download='general_cost.png'; a.click();
  });
  document.getElementById('saveGenKmImg').addEventListener('click', ()=>{
    const a = document.createElement('a');
    a.href = genKmChart.toBase64Image(); a.download='general_km.png'; a.click();
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
