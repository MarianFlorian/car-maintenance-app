<?php
// pages/dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require '../includes/db.php';

$uid = $_SESSION['user_id'];

// --- colectare date ---
$stmt = $conn->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute(); $stmt->bind_result($cntVehicles); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(total_cost),0) FROM fuelings WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute(); $stmt->bind_result($sumFuel); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(cost),0) FROM services WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute(); $stmt->bind_result($sumService); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM taxes WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute(); $stmt->bind_result($sumTax); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("
  SELECT MIN(expires_at)
    FROM documents
   WHERE user_id=? AND type IN('ITP','RCA') AND expires_at>=CURDATE()
");
$stmt->bind_param("i", $uid);
$stmt->execute(); $stmt->bind_result($nextExp); $stmt->fetch(); $stmt->close();
?>
<?php include '../includes/header.php'; ?>


<style>
  .dashboard-hero {
    position: relative;
    background: url('/assets/img/car-hero.jpg') center/cover no-repeat;
    height: 200px; border-radius: .5rem; overflow: hidden; margin-bottom: 1rem;
  }
  .dashboard-hero::after {
    content: ''; position: absolute; inset:0;
    background: rgba(0,0,0,0.4);
  }
  .dashboard-hero .hero-content {
    position: relative; z-index:1;
    color:#fff; height:100%;
    display:flex; flex-direction:column;
    justify-content:center; align-items:center;
    text-align: center;
  }
  .dashboard-card-link { text-decoration: none; color: inherit; }
  .dashboard-card-link .card { border-radius:.75rem; }
  @media (max-width:575px) {
    .dashboard-card-link .card { margin-bottom:.75rem; }
  }
</style>

<div class="container-fluid p-0">
  <!-- Hero -->
  <div class="dashboard-hero">
    <div class="hero-content">
      <h1>Condu cu încredere, economisește inteligent!</h1>
      <p>Bine ai venit în panoul de bord</p>
    </div>
  </div>

  <!-- KPI Section -->
  <h2 class="section-heading">Indicatori cheie</h2>
  <div class="row g-4">
    <!-- Vehicule -->
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-car-side kpi-icon"></i>
        <div class="kpi-title">Vehicule</div>
        <div class="kpi-value"><?= $cntVehicles ?></div>
      </div>
    </div>
    <!-- Combustibil -->
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-gas-pump kpi-icon"></i>
        <div class="kpi-title">Combustibil</div>
        <div class="kpi-value"><?= number_format($sumFuel,2) ?> RON</div>
      </div>
    </div>
    <!-- Service -->
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-tools kpi-icon"></i>
        <div class="kpi-title">Service</div>
        <div class="kpi-value"><?= number_format($sumService,2) ?> RON</div>
      </div>
    </div>
    <!-- Taxe -->
    <div class="col-6 col-sm-4 col-lg-3">
      <div class="kpi-card">
        <i class="fas fa-file-invoice-dollar kpi-icon"></i>
        <div class="kpi-title">Taxe</div>
        <div class="kpi-value"><?= number_format($sumTax,2) ?> RON</div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <h2 class="section-heading">Accese rapide</h2>
  <div class="row g-4 mt-2">
    <div class="col-md-6">
      <a href="documents.php?tab=vehicle" class="action-card">
        <i class="fas fa-folder-open action-icon"></i>
        <div>
          <div class="action-label">Documente</div>
          <div class="action-info">Vezi toate documentele</div>
        </div>
      </a>
    </div>
    <div class="col-md-6">
      <a href="statistici_general.php" class="action-card">
        <i class="fas fa-chart-bar action-icon"></i>
        <div>
          <div class="action-label">Statistici</div>
          <div class="action-info">Analize și rapoarte</div>
        </div>
      </a>
    </div>
  </div>

  <!-- Document Expiry -->
  <h2 class="section-heading">Stare documente</h2>
  <div class="row g-4 mt-2">
    <div class="col-sm-6 col-md-4">
      <div class="action-card">
        <i class="fas fa-calendar-alt action-icon"></i>
        <div>
          <div class="action-label">Următoarea expirare</div>
          <div class="action-info"><?= $nextExp ? date('d.m.Y', strtotime($nextExp)) : '— Nicio expirare —' ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
