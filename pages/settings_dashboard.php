<?php
// pages/settings_dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location:/Licenta/login.php');
    exit;
}
require __DIR__ . '/../includes/db.php';
$uid = $_SESSION['user_id'];

$all = [
  'general'   => 'General',
  'fuel'      => 'Combustibil',
  'service'   => 'Service',
  'tax'       => 'Taxe',
  'benchmark' => 'Benchmark'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sel  = $_POST['sections'] ?? [];
    $json = $conn->real_escape_string(json_encode($sel));
    $conn->query("
      INSERT INTO user_settings (user_id,setting_key,setting_value)
      VALUES ($uid,'dashboard_sections','$json')
      ON DUPLICATE KEY UPDATE setting_value='$json'
    ");
    $msg = 'Setările au fost salvate.';
}

$stmt = $conn->prepare("
  SELECT setting_value
    FROM user_settings
   WHERE user_id=? AND setting_key='dashboard_sections'
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->bind_result($val);
$stmt->fetch();
$stmt->close();
$current = $val ? json_decode($val, true) : array_keys($all);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <h2>Personalizare Dashboard</h2>
  <?php if (isset($msg)): ?>
    <div class="alert alert-success"><?= $msg ?></div>
  <?php endif; ?>
  <form method="post">
    <?php foreach ($all as $key => $label): ?>
      <div class="form-check">
        <input class="form-check-input"
               type="checkbox"
               name="sections[]"
               id="sec-<?= $key ?>"
               value="<?= $key ?>"
               <?= in_array($key, $current) ? 'checked' : '' ?>>
        <label class="form-check-label" for="sec-<?= $key ?>">
          <?= $label ?>
        </label>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary mt-3">Salvează</button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
