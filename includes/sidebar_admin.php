<?php
// includes/sidebar_admin.php
if (session_status() === PHP_SESSION_NONE) session_start();

// afișăm doar pentru admin
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    return;
}

/** Helper pentru link activ **/
function isActive(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? ' active' : '';
}

// Meniul Admin
$adminMenu = [
  ['label'=>'Dashboard Admin', 'icon'=>'tachometer-alt', 'link'=>'admin_dashboard.php'],
  ['label'=>'Utilizatori',      'icon'=>'users',         'link'=>'admin_users.php'],
  ['label'=>'Vehicule',         'icon'=>'car-side',      'link'=>'admin_vehicles.php'],
   ['label'=>'Statistici', 'icon'=>'chart-line',   'link'=>'admin_stats_overview.php'],
];

// Meniul Utilizator (doar ca referință, pentru admin)
$userMenu = [
  ['label'=>'Dashboard',       'icon'=>'tachometer-alt',       'link'=>'dashboard.php'],
  ['label'=>'Alimentări',      'icon'=>'gas-pump',             'link'=>'combustibil.php'],
  ['label'=>'Service',         'icon'=>'tools',                'link'=>'service.php'],
  ['label'=>'Taxe',            'icon'=>'file-invoice-dollar',  'link'=>'taxe.php'],
  ['label'=>'Documente',       'icon'=>'folder-open',          'link'=>'documents.php'],
  
  ['label'=>'Statistici Combustibil', 'icon'=>'chart-line',   'link'=>'statistici_combustibil.php'],
  ['label'=>'Statistici Service',     'icon'=>'chart-line',   'link'=>'statistici_service.php'],
  ['label'=>'Statistici Taxe',        'icon'=>'chart-line',   'link'=>'statistici_taxe.php'],
];
?>
<nav id="sidebar">
  <div class="sidebar-header">
    <h4>Manager Auto</h4>
  </div>

  <ul class="list-unstyled components">
    <li class="sidebar-section">ADMIN</li>
    <?php foreach ($adminMenu as $item): ?>
      <li class="<?= isActive($item['link']) ?>">
        <a href="../pages/<?= $item['link'] ?>">
          <i class="fas fa-<?= $item['icon'] ?>"></i>
          <span><?= $item['label'] ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <hr class="sidebar-divider">

  <ul class="list-unstyled components">
    <li class="sidebar-section">UTILIZATOR</li>
    <?php foreach ($userMenu as $item): ?>
      <li class="<?= isActive($item['link']) ?>">
        <a href="../pages/<?= $item['link'] ?>">
          <i class="fas fa-<?= $item['icon'] ?>"></i>
          <span><?= $item['label'] ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
