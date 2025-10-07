<!-- statistici_taxe.php-->
<?php
require __DIR__ . '/../includes/statistics_functions.php';
$active = 'taxe';

// 1) Metrici generale Taxe
$totalTax = sumMulti($conn,'taxes','amount','date_paid',$date_from,$date_to,$uid,$vehList);
$stmt = $conn->prepare("
  SELECT COUNT(*) 
    FROM taxes
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date_paid BETWEEN ? AND ?
");
$stmt->bind_param("iss",$uid,$date_from,$date_to);
$stmt->execute();
$stmt->bind_result($taxCount);
$stmt->fetch();
$stmt->close();
$avgTax = $taxCount > 0 ? $totalTax / $taxCount : 0;

// 2) Timeseries pentru Taxe: cost și count pe zi
$stmt = $conn->prepare("
  SELECT date_paid AS date,
         SUM(amount) AS tot,
         COUNT(*)   AS cnt
    FROM taxes
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date_paid BETWEEN ? AND ?
   GROUP BY date
   ORDER BY date
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();
$dates  = [];
$costs  = [];
$counts = [];
while ($r = $res->fetch_assoc()) {
    $dates[]  = $r['date'];
    $costs[]  = (float)$r['tot'];
    $counts[] = (int)$r['cnt'];
}
$stmt->close();

// JSON pentru JS
$js = json_encode([
  'dates' => $dates,
  'costs' => $costs,
  'counts'=> $counts,
]);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Statistici Taxe</h2>

  <?php include __DIR__ . '/partials/filter_header.php'; ?>
  <?php include __DIR__ . '/partials/nav_stats.php'; ?>

  <!-- Carduri metrici -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card bg-dark text-white text-center">
        <div class="card-body">
          <h6>Total Cost</h6>
          <p class="h5"><?= number_format($totalTax,2) ?> RON</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-dark text-white text-center">
        <div class="card-body">
          <h6>Număr Taxe</h6>
          <p class="h5"><?= $taxCount ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-dark text-white text-center">
        <div class="card-body">
          <h6>Medie cost/taxă</h6>
          <p class="h5"><?= number_format($avgTax,2) ?> RON</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Grafice side-by-side -->
  <div class="row">
    <!-- Cost pe zi -->
    <div class="col-md-6 mb-4">
      <h5>Cost pe zi</h5>
      <canvas id="taxCostChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportTaxCostCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveTaxCostImg"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
    <!-- Număr taxe pe zi -->
    <div class="col-md-6 mb-4">
      <h5>Număr taxe pe zi</h5>
      <canvas id="taxCountChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportTaxCountCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveTaxCountImg"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const dTax = <?= $js ?>;
  // inițializare charturi
  const taxCostCtx  = document.getElementById('taxCostChart').getContext('2d');
  const taxCountCtx = document.getElementById('taxCountChart').getContext('2d');

  const taxCostChart = new Chart(taxCostCtx, {
    type: 'line',
    data: { labels: dTax.dates, datasets: [{ label:'Cost RON', data:dTax.costs, fill:false, tension:0.1 }] },
    options:{ scales:{ y:{ beginAtZero:true } } }
  });

  const taxCountChart = new Chart(taxCountCtx, {
    type: 'line',
    data: { labels: dTax.dates, datasets: [{ label:'Număr', data:dTax.counts, fill:false, tension:0.1 }] },
    options:{ scales:{ y:{ beginAtZero:true } } }
  });

  // funcție export CSV
  function exportCsv(labels,dataSet,filename,header){
    let csv = header + '\n';
    labels.forEach((dt,i)=>{
      csv += dt + ',' + dataSet[i] + '\n';
    });
    const blob=new Blob([csv],{type:'text/csv'}), url=URL.createObjectURL(blob), a=document.createElement('a');
    a.href=url; a.download=filename; a.click(); URL.revokeObjectURL(url);
  }

  // handlere export/salvare
  document.getElementById('exportTaxCostCsv').onclick=()=>exportCsv(dTax.dates, dTax.costs, 'tax_cost.csv', 'Data,Cost');
  document.getElementById('exportTaxCountCsv').onclick=()=>exportCsv(dTax.dates, dTax.counts, 'tax_count.csv', 'Data,Count');
  document.getElementById('saveTaxCostImg').onclick=()=>{
    const a=document.createElement('a'); a.href=taxCostChart.toBase64Image(); a.download='tax_cost.png'; a.click();
  };
  document.getElementById('saveTaxCountImg').onclick=()=>{
    const a=document.createElement('a'); a.href=taxCountChart.toBase64Image(); a.download='tax_count.png'; a.click();
  };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
