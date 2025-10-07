<?php
// pages/admin_stats_overview.php
require __DIR__ . '/../includes/admin_functions.php'; // session, db, date range, helpers
require __DIR__ . '/../includes/auth.php';
$requireAdmin = true;

// Parametrii Top-N şi interval
$topN = isset($_GET['topN']) ? max(1, intval($_GET['topN'])) : 5;
$date_from = $GLOBALS['date_from'];
$date_to   = $GLOBALS['date_to'];

// KPI-uri generale
$totalUsers    = getTotalUsers($conn);
$active30d     = getActiveUsers30d($conn);
$totalVeh      = getTotalVehicles($conn);
$avgVehPerUser = getAvgVehiclesPerUser($conn);
$totalFuelings = getTotalFuelings($conn);
$totalServ     = getTotalServices($conn);
$totalTaxes    = getTotalTaxes($conn);

// Date pentru grafice
$userTrend      = getMonthlyCounts($conn, 'users',     'created_at');
$fuelPriceTrend = getMonthlyAvg   ($conn, 'fuelings', 'price_per_l', 'date');
$serviceTrend   = getMonthlyCounts($conn, 'services',  'date');
$taxTrend       = getMonthlyCounts($conn, 'taxes',     'date_paid');

// Distribuții Top-N
$fuelDist  = getDistribution($conn, 'fuelings', 'fuel_type',      $topN);
$svcDist   = getDistribution($conn, 'services', 'service_center', $topN);
$taxDist   = getDistribution($conn, 'taxes',    'tax_type',       $topN);
$brandDist = getDistribution($conn, 'vehicles', 'brand',          $topN);

// Top-uri speciale
$vehSvcCount     = getVehicleServiceCount($conn, $topN);
$topExpensiveSvc = getTopExpensiveServices($conn, $topN);
$vehConsumption  = getVehicleConsumption($conn, $topN);

?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- CSS comune și specifice -->
<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/admin_stats.css">

<div class="container-fluid p-0">
  <!-- Hero -->
  <div class="dashboard-hero" style="background: linear-gradient(135deg, #1abc9c, #34495e);">
    <div class="hero-content">
      <h1>Rapoarte &amp; Statistici</h1>
      <p>Analizează evoluția și activitățile aplicației</p>
    </div>
  </div>

  <!-- Filtre & Top-N Inline -->
  <div class="filter-panel mb-4">
    <form method="get" class="row gx-2 gy-2 align-items-end">
      <?php $current = $_GET['preset'] ?? 'all'; ?>
      <div class="col-12">
        <div class="btn-group" role="group" aria-label="Preset interval">
          <?php foreach ([
            ['7','7 zile'],
            ['30','30 zile'],
            ['month','Luna curentă'],
            ['all','Toate datele']
          ] as $p): ?>
            <button type="submit" name="preset" value="<?= $p[0] ?>"
              class="btn btn-outline-primary<?= $current=== $p[0] ? ' active':'' ?>">
              <?= $p[1] ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-auto">
        <label>De la</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($date_from) ?>">
      </div>
      <div class="col-auto">
        <label>Până la</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($date_to) ?>">
      </div>
      <div class="col-auto">
        <label>Top N</label>
        <input type="number" name="topN" class="form-control form-control-sm" min="1"
               style="width:4rem" value="<?= $topN ?>">
      </div>
      <div class="col-auto">
        <button type="submit" name="manual" value="1"
                class="btn btn-sm btn-primary">Aplică</button>
      </div>
      <div class="col-auto">
        <a href="admin_stats_compare.php?fromA=<?= urlencode($date_from) ?>&toA=<?= urlencode($date_to) ?>&topN=<?= $topN ?>"
           class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-exchange-alt"></i> Compară perioade
        </a>
      </div>
    </form>
  </div>

  <!-- KPI Cards -->
  <h2 class="section-heading">Indicatori cheie</h2>
  <div class="row g-4 mb-4">
    <?php foreach ([
      ['fas fa-users','Total Utilizatori',$totalUsers],
      ['fas fa-user-check','Activi 30z',$active30d],
      ['fas fa-car-side','Total Vehicule',$totalVeh],
      ['fas fa-chart-bar','Media veh/user',$avgVehPerUser],
      ['fas fa-gas-pump','Fuelings',$totalFuelings],
      ['fas fa-tools','Servicii',$totalServ],
      ['fas fa-file-invoice-dollar','Taxe',$totalTaxes],
    ] as $kpi): ?>
      <div class="col-6 col-sm-4 col-lg-3">
        <div class="kpi-card">
          <i class="<?= $kpi[0] ?> kpi-icon"></i>
          <div class="kpi-title"><?= $kpi[1] ?></div>
          <div class="kpi-value"><?= $kpi[2] ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Unified Trend Chart -->
  <h2 class="section-heading">Evoluție unificată</h2>
  <div class="card mb-4">
    <div class="card-body position-relative">
      <canvas id="trendChart" height="150"></canvas>
      <button type="button"
              class="btn btn-sm btn-outline-secondary position-absolute"
              style="top:0.5rem; right:0.5rem;"
              onclick="downloadChart(trendChart, 'trend_chart.png')">
        <i class="fas fa-download"></i>
      </button>
    </div>
  </div>

  <!-- Distribuții Interactive -->
  <h2 class="section-heading">Distribuții Top <?= $topN ?></h2>
  <div class="row g-4 mb-4" id="distCharts">
    <?php foreach ([
      'fuel'  => ['Tip combustibil',$fuelDist],
      'svc'   => ['Centru service',$svcDist],
      'tax'   => ['Tip taxă',$taxDist],
      'brand' => ['Marcă vehicul',$brandDist],
    ] as $key => [$title,$dist]): ?>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
          <div class="card-header bg-light"><small><?= htmlspecialchars($title) ?></small></div>
          <div class="card-body p-2 text-center position-relative">
            <canvas id="<?= $key ?>Dist" height="120"></canvas>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary position-absolute"
                    style="top:0.5rem; right:0.5rem;"
                    onclick="downloadChart(<?= $key ?>Dist, 'dist_<?= $key ?>.png')">
              <i class="fas fa-download"></i>
            </button>
          </div>
          <ul class="list-group list-group-flush">
            <?php foreach ($dist['labels'] as $i => $lbl): ?>
              <li class="list-group-item d-flex justify-content-between">
                <?= htmlspecialchars($lbl) ?>
                <span class="badge bg-primary"><?= $dist['data'][$i] ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>


  <!-- Top Detaliate -->
  <h2 class="section-heading">Top Detaliate</h2>
  <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-header bg-light"><small>Vehicule cu cele mai multe servicii</small></div>
        <div class="card-body position-relative">
          <canvas id="vehSvcChart" height="120"></canvas>
          <button type="button"
                  class="btn btn-sm btn-outline-secondary position-absolute"
                  style="top:0.5rem; right:0.5rem;"
                  onclick="downloadChart(vehSvcChart, 'top_servicii_vehicule.png')">
            <i class="fas fa-download"></i>
          </button>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-header bg-light"><small>Servicii cele mai scumpe</small></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Vehicul</th><th>Dată</th><th>Cost</th></tr></thead>
              <tbody>
                <?php foreach ($topExpensiveSvc as $s): ?>
                  <tr>
                    <td><?= htmlspecialchars($s['veh']) ?></td>
                    <td><?= $s['date'] ?></td>
                    <td><?= $s['cost'] ?> RON</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-header bg-light"><small>Vehicule cu cel mai mare consum</small></div>
        <div class="card-body position-relative">
          <canvas id="vehConsChart" height="120"></canvas>
          <button type="button"
                  class="btn btn-sm btn-outline-secondary position-absolute"
                  style="top:0.5rem; right:0.5rem;"
                  onclick="downloadChart(vehConsChart, 'top_consum_vehicule.png')">
            <i class="fas fa-download"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Scripturi Chart.js și funcție download -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  function downloadChart(chart, filename) {
    const canvas = chart.canvas || chart;
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = filename;
    link.click();
  }

  // Trend unified
  new Chart(document.getElementById('trendChart'), {
    type:'line',
    data:{
      labels: <?= json_encode($userTrend['labels']) ?>,
      datasets:[
        { label:'Utilizatori', data: <?= json_encode($userTrend['data']) ?>, borderColor:'#1abc9c', fill:false },
        { label:'Fuel price', data: <?= json_encode($fuelPriceTrend['data']) ?>, borderColor:'#34495e', fill:false },
        { label:'Servicii', data: <?= json_encode($serviceTrend['data']) ?>, borderColor:'#2575fc', fill:false },
        { label:'Taxe', data: <?= json_encode($taxTrend['data']) ?>, borderColor:'#e74c3c', fill:false }
      ]
    },
    options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
  });

  // Distribuții pie
  ['fuel','svc','tax','brand'].forEach(key => {
    const dist = ({
      fuel: <?= json_encode($fuelDist) ?>,
      svc:  <?= json_encode($svcDist) ?>,
      tax:  <?= json_encode($taxDist) ?>,
      brand:<?= json_encode($brandDist) ?>
    })[key];
    new Chart(document.getElementById(key+'Dist'), {
      type:'pie',
      data:{ labels:dist.labels, datasets:[{ data:dist.data }] }
    });
  });

  // Bar charts Top detaliate
  new Chart(document.getElementById('vehSvcChart'), {
    type:'bar',
    data:{
      labels: <?= json_encode(array_column($vehSvcCount,'veh')) ?>,
      datasets:[{ label:'Servicii', data:<?= json_encode(array_column($vehSvcCount,'c')) ?> }]
    },
    options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
  });
  new Chart(document.getElementById('vehConsChart'), {
    type:'bar',
    data:{
      labels: <?= json_encode(array_column($vehConsumption,'veh')) ?>,
      datasets:[{ label:'L/100km', data:<?= json_encode(array_column($vehConsumption,'consumption')) ?> }]
    },
    options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
