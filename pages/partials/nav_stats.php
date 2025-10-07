<!-- nav_stats.php -->
<?php
// partial pentru tab-uri; $active = 'general'|'combustibil'|'service'|'taxe'
$query = http_build_query([
  'date_from'=>$date_from,
  'date_to'=>$date_to,
  'preset'=>$_GET['preset'] ?? null,
  'veh'=>$selected
]);
?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $active==='general'?'active':''?>"
       href="statistici_general.php?<?= $query ?>">General</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $active==='combustibil'?'active':''?>"
       href="statistici_combustibil.php?<?= $query ?>">Combustibil</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $active==='service'?'active':''?>"
       href="statistici_service.php?<?= $query ?>">Service</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $active==='taxe'?'active':''?>"
       href="statistici_taxe.php?<?= $query ?>">Taxe</a>
  </li>
</ul>
