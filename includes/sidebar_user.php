<?php
// includes/sidebar_user.php
if (session_status() === PHP_SESSION_NONE) session_start();

// afișăm doar pentru utilizatorii non-admin
if (empty($_SESSION['user_id']) || !empty($_SESSION['is_admin'])) {
    return;
}

/**
 * Returnează 'active' dacă pagina curentă corespunde.
 */
function isActive(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? ' active' : '';
}
?>
<nav id="sidebar">
  <div class="sidebar-header">
    <h4>Manager Auto</h4>
  </div>
  <ul class="list-unstyled components">
    <li class="<?= isActive('dashboard.php') ?>">
      <a href="../pages/dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
    </li>
    <li class="<?= isActive('vehicles.php') ?>">
      <a href="../pages/vehicles.php">
        <i class="fas fa-car-side"></i> Vehiculele tale
      </a>
    </li>
    <li class="<?= isActive('combustibil.php') ?>">
      <a href="../pages/combustibil.php">
        <i class="fas fa-gas-pump"></i> Alimentări
      </a>
    </li>
    <li class="<?= isActive('service.php') ?>">
      <a href="../pages/service.php">
        <i class="fas fa-tools"></i> Service
      </a>
    </li>
    <li class="<?= isActive('taxe.php') ?>">
      <a href="../pages/taxe.php">
        <i class="fas fa-file-invoice-dollar"></i> Taxe
      </a>
    </li>
    <li class="sidebar-parent">
      <span>
        <i class="fas fa-chart-bar"></i> Statistici
      </span>
      <ul class="list-unstyled sidebar-submenu">
        <li class="<?= isActive('statistici_combustibil.php') ?>">
          <a href="../pages/statistici_general.php">General</a>
        </li>
        <li class="<?= isActive('statistici_combustibil.php') ?>">
          <a href="../pages/statistici_combustibil.php">Combustibil</a>
        </li>
        <li class="<?= isActive('statistici_service.php') ?>">
          <a href="../pages/statistici_service.php">Service</a>
        </li>
        <li class="<?= isActive('statistici_taxe.php') ?>">
          <a href="../pages/statistici_taxe.php">Taxe</a>
        </li>
      </ul>
    </li>
    <li class="<?= isActive('documents.php') ?>">
      <a href="../pages/documents.php">
        <i class="fas fa-folder-open"></i> Documente
      </a>
    </li>
  </ul>
</nav>
