<?php 
// includes/header.php

// 1. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Autoload + .env
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// 3. DB connection
require_once __DIR__ . '/db.php';

// 4. Load notifications for logged-in users
$notifications = [];
$notifCount    = 0;
if (!empty($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
      SELECT n.id, v.brand, v.model, n.type, n.trigger_date, n.trigger_km, n.note
        FROM notifications n
        JOIN vehicles v ON v.id = n.vehicle_id
       WHERE n.user_id = ?
         AND n.is_active = 1
         AND n.trigger_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
       ORDER BY n.trigger_date DESC, n.trigger_km DESC
       LIMIT 5
");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notifCount    = count($notifications);
    $stmt->close();
}

// 5. Determine role
$isAdmin     = !empty($_SESSION['is_admin']);
$isLoggedIn  = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Manager Auto</title>

  <!-- Bootstrap 4 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N"
    crossorigin="anonymous">
  <!-- FontAwesome -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
    rel="stylesheet">
  <!-- Custom & Sidebar CSS -->
  <link href="../assets/css/custom.css" rel="stylesheet">
  <link href="../assets/css/dashboard.css" rel="stylesheet">
  <link href="../assets/css/fuel.css" rel="stylesheet">
  <link href="../assets/css/service.css" rel="stylesheet">
  <link href="../assets/css/taxes.css" rel="stylesheet">
  <link href="../assets/css/admin_users.css" rel="stylesheet">
  <link href="../assets/css/sidebar.css" rel="stylesheet">
</head>
<body>

<?php if ($isLoggedIn): ?>

  <!-- Toast container for in-app notifications -->
  <div aria-live="polite" aria-atomic="true"
       style="position: fixed; bottom: 1rem; right: 1rem; z-index: 1080;">
    <div id="notifToast" class="toast" data-delay="5000">
      <div class="toast-header">
        <strong class="mr-auto">Manager Auto</strong>
        <small>acum</small>
        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast">&times;</button>
      </div>
      <div class="toast-body">
        Ai <?= $notifCount ?> notificare<?= $notifCount > 1 ? 'i' : '' ?>!
      </div>
    </div>
  </div>

  <!-- Main Navbar with sidebar-toggle -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
      <!-- Sidebar hamburger toggle (mobile) -->
      <button class="btn btn-dark btn-sidebar-toggle d-lg-none me-2"
              type="button"
              data-toggle="collapse"
              data-target="#sidebar"
              aria-controls="sidebar"
              aria-expanded="false"
              aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </button>

      <!-- Brand -->
      <a class="navbar-brand d-flex align-items-center" href="../pages/dashboard.php">
        <img src="../assets/images/logo-light.png" height="30" class="me-2" alt="Manager Auto">
        Manager Auto
      </a>

      <!-- MainNav toggler -->
      <button class="navbar-toggler" type="button"
              data-toggle="collapse"
              data-target="#mainNav"
              aria-controls="mainNav"
              aria-expanded="false"
              aria-label="Toggle main navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- MainNav links -->
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ml-auto align-items-center">
          
          <li class="nav-item"><a class="nav-link" href="../pages/calculator_alcoolemie.php">Calculator alcoolemie</a></li>
          <li class="nav-item"><a class="nav-link" href="../pages/calculator_cost.php">Calculator cost</a></li>

          <!-- Notification bell -->
          <li class="nav-item position-relative">
            <a class="nav-link" href="#" id="notifToggle">
              <i class="fas fa-bell"></i>
              <?php if ($notifCount > 0): ?>
                <span class="badge badge-pill badge-danger position-absolute" style="top:0; right:0;">
                  <?= $notifCount ?>
                </span>
              <?php endif; ?>
            </a>
          </li>

          <!-- Profile & Logout -->
          <li class="nav-item"><a class="nav-link btn btn-outline-light ml-2" href="../pages/settings.php"><i class="fas fa-cog"></i></a></li>
          <li class="nav-item"><a class="nav-link btn btn-outline-light ml-2" href="../logout.php"><i class="fas fa-sign-out-alt"></i></a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Include appropriate sidebar -->
  <?php
    if ($isAdmin) {
      include __DIR__ . '/sidebar_admin.php';
    } else {
      include __DIR__ . '/sidebar_user.php';
    }
  ?>

  <!-- Notification panel -->
  <div id="notifPanel" class="collapse position-absolute bg-white border"
       style="top:56px; width:280px; z-index:1050;">
    <div class="p-2" style="max-height:300px; overflow-y:auto;">
      <?php if ($notifCount > 0): ?>
        <?php foreach ($notifications as $n): ?>
          <a class="d-block small py-1 border-bottom" href="../pages/notifications.php">
            <strong><?= htmlspecialchars("{$n['brand']} {$n['model']}") ?></strong><br>
            <?php if (in_array($n['type'], ['date','both'])): ?>
              <i class="far fa-calendar-alt"></i> <?= htmlspecialchars($n['trigger_date']) ?><br>
            <?php endif; ?>
            <?php if (in_array($n['type'], ['km','both'])): ?>
              <i class="fas fa-tachometer-alt"></i> <?= htmlspecialchars($n['trigger_km']) ?> km<br>
            <?php endif; ?>
            <?php if ($n['note']): ?>
              <small class="text-muted">
                <?= htmlspecialchars(strlen($n['note'])>30 ? substr($n['note'],0,27).'...' : $n['note']) ?>
              </small>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <div class="border-top text-center p-1">
          <a href="../pages/notifications.php" class="small">Vezi toate notificările →</a>
        </div>
      <?php else: ?>
        <div class="text-center text-muted small py-3">Nicio notificare activă</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Page content wrapper -->
  <div id="content" class="container-fluid mt-4">
<?php else: ?>
  <!-- Guest Navbar (same structure, no sidebar toggle, no notifications) -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
      <a class="navbar-brand" >Manager Auto</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#guestNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="guestNav">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item"><a class="nav-link" href="./pages/landing.php">Acasă</a></li>
          <li class="nav-item"><a class="nav-link" href="../login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="../register.php">Înregistrare</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Guest content wrapper -->
  <div class="container mt-4">
<?php endif; ?>

<!-- JS: jQuery, Popper.js, Bootstrap JS -->
<script
  src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
  integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
  crossorigin="anonymous"></script>
<script
  src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"
  integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN"
  crossorigin="anonymous"></script>
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"
  integrity="sha384-+YQ4/BrkJr7F5Lk08yM6vR7Y1KPTl0Vx1p6nb98R+EyJ5bWFib0ej0LvYlaj9QX"
  crossorigin="anonymous"></script>

<script>
// Sidebar toggle + click-away (logged in only)
$('.btn-sidebar-toggle').on('click', e => {
  e.stopPropagation();
  $('#sidebar').collapse('toggle');
});
$(document).on('click', () => {
  if ($('#sidebar').hasClass('show')) {
    $('#sidebar').collapse('hide');
  }
});

// MainNav toggle (already handled by Bootstrap) skip

// Notifications panel toggle
$('#notifToggle').on('click', e => {
  e.preventDefault();
  e.stopPropagation();
  const rect = e.currentTarget.getBoundingClientRect();
  $('#notifPanel').css({
    top: rect.bottom + 5 + 'px',
    left: rect.left - $('#notifPanel').outerWidth() + rect.width + 'px'
  }).toggleClass('show');
});
$(document).on('click', e => {
  if (!$(e.target).closest('#notifToggle, #notifPanel').length) {
    $('#notifPanel').removeClass('show');
  }
});
</script>
