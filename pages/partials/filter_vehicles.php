<!-- filter_vehicles.php -->
<form method="get" class="form-inline mb-4">
  <!-- Date -->
  <label for="date_from" class="mr-2"><i class="far fa-calendar-alt"></i></label>
  <input type="date" id="date_from" name="date_from" class="form-control mr-2"
         value="<?= htmlspecialchars($date_from) ?>">
  <input type="date" id="date_to"   name="date_to"   class="form-control mr-4"
         value="<?= htmlspecialchars($date_to) ?>">

  <!-- Vehicule -->
  <div class="form-group mr-3">
    <label class="d-block">Vehicule:</label>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="veh_all">
      <label class="form-check-label" for="veh_all"><strong>Toate vehiculele</strong></label>
    </div>
    <?php foreach ($vehicles as $id => $lbl): ?>
      <div class="form-check form-check-inline">
        <input class="form-check-input veh-checkbox" type="checkbox" name="veh[]" 
               id="veh_<?= $id ?>" value="<?= $id ?>"
               <?= in_array($id, $selected) ? 'checked' : '' ?>>
        <label class="form-check-label" for="veh_<?= $id ?>">
          <?= htmlspecialchars($lbl) ?>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <button class="btn btn-primary">Aplică filtre</button>
</form>

<script>
// când dai click pe “Toate vehiculele”
document.getElementById('veh_all').addEventListener('change', function(){
  document.querySelectorAll('.veh-checkbox').forEach(chk => {
    chk.checked = this.checked;
  });
});
// la încărcare, sincronizează “Toate” cu stare checkbox-urilor
window.addEventListener('DOMContentLoaded', () => {
  const all = [...document.querySelectorAll('.veh-checkbox')].every(c=>c.checked);
  document.getElementById('veh_all').checked = all;
});
</script>
