<!-- filter_header.php -->
<form method="get" class="mb-4">
  <!-- 1) Preset-uri perioadă -->
  <div class="btn-group btn-group-sm mb-2" role="group">
    <?php 
      $presets = [
        ['7', 'Ultimele 7 zile'],
        ['30','Ultimele 30 zile'],
        ['month','Luna curentă'],
        ['prev_month','Luna trecută'],
        ['all','Toate datele']
      ];
      foreach ($presets as $p):
    ?>
      <button 
        type="submit" 
        name="preset" 
        value="<?= $p[0] ?>" 
        class="btn btn-outline-secondary<?= (isset($_GET['preset']) ? ($_GET['preset']==$p[0]?' active':'') : ($p[0]==='all' ? ' active':'')) ?>">
        <?= $p[1] ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- 2) Interval manual și buton „Aplică” -->
  <div class="form-row align-items-end">
    <div class="col-auto">
      <label for="date_from">De la</label>
      <input type="date" id="date_from" name="date_from" class="form-control"
             value="<?= htmlspecialchars($date_from) ?>">
    </div>
    <div class="col-auto">
      <label for="date_to">Până la</label>
      <input type="date" id="date_to" name="date_to" class="form-control"
             value="<?= htmlspecialchars($date_to) ?>">
    </div>
    <div class="col-auto">
      <!-- buton manual are name="manual" -->
      <button type="submit" name="manual" value="1" class="btn btn-primary">Aplică</button>
    </div>

    <!-- 3) Selectare vehicule -->
    <div class="col-12 mt-3">
      <label>Vehicule:</label>
      <div class="mb-2">
        <input type="checkbox" id="veh_all" class="form-check-input mr-1">
        <label for="veh_all" class="form-check-label mr-3"><strong>Toate vehiculele</strong></label>
        <?php foreach ($vehicles as $id => $lbl): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input veh-checkbox" 
                   type="checkbox" name="veh[]" 
                   id="veh_<?= $id ?>" value="<?= $id ?>"
                   <?= in_array($id, $selected) ? 'checked' : '' ?>>
            <label class="form-check-label" for="veh_<?= $id ?>">
              <?= htmlspecialchars($lbl) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</form>

<script>
// “Select all” vehicule
document.getElementById('veh_all').addEventListener('change', function(){
  document.querySelectorAll('.veh-checkbox').forEach(chk=>{
    chk.checked = this.checked;
  });
});
window.addEventListener('DOMContentLoaded', ()=>{
  const allChecked = [...document.querySelectorAll('.veh-checkbox')].every(c=>c.checked);
  document.getElementById('veh_all').checked = allChecked;
});
</script>
