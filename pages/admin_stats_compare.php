<?php
// pages/admin_stats_compare.php
require __DIR__ . '/../includes/auth.php';
// Verifică dacă este admin
if (empty($_SESSION['is_admin'])) {
    header('Location: ../pages/dashboard.php');
    exit();
}
require __DIR__ . '/../includes/db.php';

// Intervalele de comparat (A și B)
$fromA = $_GET['fromA'] ?? date('Y-m-d', strtotime('-30 days'));
$toA   = $_GET['toA']   ?? date('Y-m-d');
$fromB = $_GET['fromB'] ?? date('Y-m-d', strtotime('-60 days'));
$toB   = $_GET['toB']   ?? date('Y-m-d', strtotime('-31 days'));

// Helper pentru COUNT
function fetchCount($conn, $table, $dateCol, $from, $to) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE $dateCol BETWEEN ? AND ?");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return (int)$count;
}

// Definirea metricilor
$metrics = [
    'Utilizatori' => ['table'=>'users',    'col'=>'created_at'],
    'Fuelings'    => ['table'=>'fuelings', 'col'=>'date'],
    'Servicii'    => ['table'=>'services', 'col'=>'date'],
    'Taxe'        => ['table'=>'taxes',    'col'=>'date_paid'],
];

// Colectare date pentru A și B
$dataA = [];
$dataB = [];
foreach ($metrics as $label => $info) {
    $dataA[] = fetchCount($conn, $info['table'], $info['col'], $fromA, $toA);
    $dataB[] = fetchCount($conn, $info['table'], $info['col'], $fromB, $toB);
}

// Pregătire JSON pentru Chart.js
$labels  = json_encode(array_keys($metrics));
$seriesA = json_encode($dataA);
$seriesB = json_encode($dataB);

include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/admin_stats.css">

<div class="container-fluid p-4">
  <!-- Hero -->
  <div class="dashboard-hero" style="background: linear-gradient(135deg, #1abc9c, #34495e);">
    <div class="hero-content">
      <h1>Compară Intervalele A vs B</h1>
      <p>Selectează două perioade și compară metrici cheie</p>
    </div>
  </div>

  <!-- Form comparare -->
  <div class="filter-panel mb-4">
    <form method="get" class="row gx-3 gy-2 align-items-end">
      <div class="col-auto">
        <label>Interval A - De la</label>
        <input type="date" name="fromA" class="form-control form-control-sm" value="<?= htmlspecialchars(
$fromA) ?>">
      </div>
      <div class="col-auto">
        <label>Interval A - Până la</label>
        <input type="date" name="toA" class="form-control form-control-sm" value="<?= htmlspecialchars(
$toA) ?>">
      </div>
      <div class="col-auto">
        <label>Interval B - De la</label>
        <input type="date" name="fromB" class="form-control form-control-sm" value="<?= htmlspecialchars(
$fromB) ?>">
      </div>
      <div class="col-auto">
        <label>Interval B - Până la</label>
        <input type="date" name="toB" class="form-control form-control-sm" value="<?= htmlspecialchars(
$toB) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Compară</button>
      </div>
    </form>
  </div>

  <!-- Chart comparație -->
  <div class="card mb-4">
    <div class="card-body">
      <canvas id="compareChart" height="120"></canvas>
    </div>
  </div>

  <!-- Tabel sumar diferențe -->
  <h2 class="section-heading">Rezumat Diferențe</h2>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Metrica</th>
          <th>A (<?= htmlspecialchars($fromA) ?> → <?= htmlspecialchars($toA) ?>)</th>
          <th>B (<?= htmlspecialchars($fromB) ?> → <?= htmlspecialchars($toB) ?>)</th>
          <th>Diff</th>
          <th>% Change</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_keys($metrics) as $i => $lbl): 
          $a = $dataA[$i]; $b = $dataB[$i];
          $diff = $b - $a;
          $pct = $a ? round(($diff/$a)*100,2) : 0;
        ?>
          <tr>
            <td><?= htmlspecialchars($lbl) ?></td>
            <td><?= $a ?></td>
            <td><?= $b ?></td>
            <td><?= $diff >= 0 ? '+' : '' ?><?= $diff ?></td>
            <td><?= $pct ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('compareChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= $labels ?>,
    datasets: [
      { label: 'Interval A', data: <?= $seriesA ?>, backgroundColor: 'rgba(26,188,156,0.7)' },
      { label: 'Interval B', data: <?= $seriesB ?>, backgroundColor: 'rgba(52,73,94,0.7)' }
    ]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    }
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
