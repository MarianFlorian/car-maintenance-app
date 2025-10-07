<?php
// pages/admin_dashboard.php
session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: ../login.php");
    exit();
}
require '../includes/db.php';

// KPI data
$cntUsers    = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$cntVehicles = $conn->query("SELECT COUNT(*) FROM vehicles")->fetch_row()[0];

// Chart data: ultimele 6 luni utilizatori
$userStats = $conn->query("
  SELECT DATE_FORMAT(created_at,'%b') AS luna, COUNT(*) AS total
    FROM users
   GROUP BY luna
   ORDER BY MIN(created_at) DESC
   LIMIT 6
");
$labels       = [];
$userCounts   = [];
while ($row = $userStats->fetch_assoc()) {
    $labels[]     = $row['luna'];
    $userCounts[] = $row['total'];
}

// Chart data: ultimele 6 luni vehicule
$vehStats = $conn->query("
  SELECT DATE_FORMAT(created_at,'%b') AS luna, COUNT(*) AS total
    FROM vehicles
   GROUP BY luna
   ORDER BY MIN(created_at) DESC
   LIMIT 6
");
$vehCounts = [];
while ($row = $vehStats->fetch_assoc()) {
    $vehCounts[] = $row['total'];
}
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid p-0">
  <!-- Hero -->
  <div class="dashboard-hero" style="background: linear-gradient(135deg, #6a11cb, #2575fc);">
    <div class="hero-content">
      <h1>Panou Administrare</h1>
      <p>Monitorizează utilizatorii și vehiculele din aplicație</p>
    </div>
  </div>

  <!-- KPI Section -->
  <h2 class="section-heading">Indicatori cheie</h2>
  <div class="row g-4">
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-users kpi-icon"></i>
        <div class="kpi-title">Utilizatori</div>
        <div class="kpi-value"><?= $cntUsers ?></div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-car-side kpi-icon"></i>
        <div class="kpi-title">Vehicule</div>
        <div class="kpi-value"><?= $cntVehicles ?></div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-chart-bar kpi-icon"></i>
        <div class="kpi-title">Statistici</div>
        <div class="kpi-value">&nbsp;</div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <h2 class="section-heading">Accese rapide</h2>
  <div class="row g-4 mt-2">
    <div class="col-md-4">
      <a href="admin_users.php" class="action-card">
        <i class="fas fa-users action-icon"></i>
        <div>
          <div class="action-label">Gestionează Utilizatori</div>
          <div class="action-info">Adaugă, editează sau șterge</div>
        </div>
      </a>
    </div>
    <div class="col-md-4">
      <a href="admin_vehicles.php" class="action-card">
        <i class="fas fa-car-side action-icon"></i>
        <div>
          <div class="action-label">Gestionează Vehicule</div>
          <div class="action-info">Vizualizează și modifică</div>
        </div>
      </a>
    </div>
    <div class="col-md-4">
      <a href="admin_stats_overview.php" class="action-card">
        <i class="fas fa-chart-bar action-icon"></i>
        <div>
          <div class="action-label">Vezi Rapoarte</div>
          <div class="action-info">Grafice și analize</div>
        </div>
      </a>
    </div>
  </div>

  <!-- Charts -->
  <h2 class="section-heading">Evoluție utilizatori & vehicule</h2>
  <div class="row g-4 mt-2">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-light">
          <h6 class="mb-0">Utilizatori noi (ultimele 6 luni)</h6>
        </div>
        <div class="card-body p-2">
          <canvas id="userChart" height="130"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-light">
          <h6 class="mb-0">Vehicule înregistrate</h6>
        </div>
        <div class="card-body p-2">
          <canvas id="vehicleChart" height="130"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels        = <?= json_encode(array_reverse($labels)) ?>;
const userData      = <?= json_encode(array_reverse($userCounts)) ?>;
const vehicleData   = <?= json_encode(array_reverse($vehCounts)) ?>;

const commonOptions = {
  responsive: true,
  scales: {
    y: { beginAtZero: true },
    x: { ticks: { font: { size: 11 } } }
  }
};

new Chart(document.getElementById('userChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Utilizatori noi',
      data: userData,
      borderColor: '#2575fc',
      fill: false,
      tension: 0.3,
      pointRadius: 3
    }]
  },
  options: commonOptions
});

new Chart(document.getElementById('vehicleChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Vehicule',
      data: vehicleData,
      borderColor: '#6a11cb',
      fill: false,
      tension: 0.3,
      pointRadius: 3
    }]
  },
  options: commonOptions
});
</script>

<?php include '../includes/footer.php'; ?>
