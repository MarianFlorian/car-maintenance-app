<!-- statistici_service.php-->
<?php
require __DIR__ . '/../includes/statistics_functions.php';
$active = 'service';

// 1) Metrici generale Service
$totalService = sumMulti($conn,'services','cost','date',$date_from,$date_to,$uid,$vehList);
$stmt = $conn->prepare("
  SELECT COUNT(*) 
    FROM services
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
");
$stmt->bind_param("iss",$uid,$date_from,$date_to);
$stmt->execute();
$stmt->bind_result($serviceCount);
$stmt->fetch();
$stmt->close();
$avgServiceCost = $serviceCount > 0 ? $totalService / $serviceCount : 0;

// 2) Timeseries pentru Service: cost și count pe zi
$stmt = $conn->prepare("
  SELECT date,
         SUM(cost)  AS tot,
         COUNT(*)    AS cnt
    FROM services
   WHERE user_id=? AND vehicle_id IN ($vehList)
     AND date BETWEEN ? AND ?
   GROUP BY date
   ORDER BY date
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$res = $stmt->get_result();
$dates   = [];
$costs   = [];
$counts  = [];
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
  <h2>Statistici Service</h2>

  <?php include __DIR__ . '/partials/filter_header.php'; ?>
  <?php include __DIR__ . '/partials/nav_stats.php'; ?>

  <!-- Carduri metrici -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card bg-warning text-white text-center">
        <div class="card-body">
          <h6>Total Cost</h6>
          <p class="h5"><?= number_format($totalService,2) ?> RON</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-warning text-white text-center">
        <div class="card-body">
          <h6>Număr intervenții</h6>
          <p class="h5"><?= $serviceCount ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-warning text-white text-center">
        <div class="card-body">
          <h6>Medie cost/intervenție</h6>
          <p class="h5"><?= number_format($avgServiceCost,2) ?> RON</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Grafice side-by-side -->
  <div class="row">
    <!-- Cost pe zi -->
    <div class="col-md-6 mb-4">
      <h5>Cost pe zi</h5>
      <canvas id="serviceCostChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportServCostCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveServCostImg"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
    <!-- Număr intervenții pe zi -->
    <div class="col-md-6 mb-4">
      <h5>Număr intervenții pe zi</h5>
      <canvas id="serviceCountChart" class="w-100" style="min-height:220px"></canvas>
      <div class="mt-2 text-right">
        <button id="exportServCountCsv" class="btn btn-sm btn-secondary">Export CSV</button>
        <button id="saveServCountImg"   class="btn btn-sm btn-secondary">Salvează imagine</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const dService = <?= $js ?>;
  // inițializare charturi
  const costCtx  = document.getElementById('serviceCostChart').getContext('2d');
  const countCtx = document.getElementById('serviceCountChart').getContext('2d');

  const costChart = new Chart(costCtx, {
    type: 'line',
    data: { labels: dService.dates, datasets: [{ label: 'Cost RON', data: dService.costs, fill:false, tension:0.1 }] },
    options: { scales:{ y:{ beginAtZero:true } } }
  });

  const countChart = new Chart(countCtx, {
    type: 'line',
    data: { labels: dService.dates, datasets: [{ label: 'Număr', data: dService.counts, fill:false, tension:0.1 }] },
    options: { scales:{ y:{ beginAtZero:true } } }
  });

  // funcție export CSV
  function exportCsv(labels, dataset, filename, header) {
    let csv = header + '\n';
    labels.forEach((dt,i) => {
      csv += dt + ',' + dataset[i] + '\n';
    });
    const blob = new Blob([csv], { type:'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
  }

  // Handlere export / salvare imagine
  document.getElementById('exportServCostCsv').addEventListener('click', () => {
    exportCsv(dService.dates, dService.costs, 'service_cost.csv', 'Data,Cost');
  });
  document.getElementById('exportServCountCsv').addEventListener('click', () => {
    exportCsv(dService.dates, dService.counts, 'service_count.csv', 'Data,Count');
  });
  document.getElementById('saveServCostImg').addEventListener('click', () => {
    const a = document.createElement('a');
    a.href = costChart.toBase64Image(); a.download = 'service_cost.png'; a.click();
  });
  document.getElementById('saveServCountImg').addEventListener('click', () => {
    const a = document.createElement('a');
    a.href = countChart.toBase64Image(); a.download = 'service_count.png'; a.click();
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
