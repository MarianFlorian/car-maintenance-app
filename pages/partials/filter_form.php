<!-- filter_form.php-->
<form method="get" class="form-inline mb-4">
  <label class="mr-2"><i class="far fa-calendar-alt"></i></label>
  <input type="date" name="date_from" class="form-control mr-2" value="<?= htmlspecialchars($date_from) ?>">
  <input type="date" name="date_to"   class="form-control mr-4" value="<?= htmlspecialchars($date_to) ?>">
  <label class="mr-2">Vehicule:</label>
  <?php foreach ($vehicles as $id => $lbl): ?>
    <div class="form-check form-check-inline">
      <input class="form-check-input" type="checkbox" name="veh[]" value="<?= $id ?>"
             <?= in_array($id, $selected) ? 'checked' : '' ?>>
      <label class="form-check-label"><?= $lbl ?></label>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-primary ml-3">Aplică filtre</button>
</form>
