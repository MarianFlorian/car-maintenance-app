<!-- filter_form_list.php -->
<form method="get" class="form-inline mb-4">
  <label for="date_from" class="mr-2"><i class="far fa-calendar-alt"></i></label>
  <input type="date" id="date_from" name="date_from" class="form-control mr-2"
         value="<?= htmlspecialchars($date_from) ?>">
  <input type="date" id="date_to"   name="date_to"   class="form-control mr-4"
         value="<?= htmlspecialchars($date_to) ?>">

  <label for="veh_list" class="mr-2">Vehicule:</label>
  <select id="veh_list" name="veh[]" class="form-control mr-2" multiple size="3">
    <?php foreach ($vehicles as $id => $lbl): ?>
      <option value="<?= $id ?>"
        <?= in_array($id, $selected) ? 'selected' : '' ?>>
        <?= htmlspecialchars($lbl) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button class="btn btn-primary">Aplică filtre</button>
</form>
