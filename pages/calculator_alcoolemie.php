<?php
// pages/calculator_alcoolemie.php
// Public — nu necesită autentificare

// Preluare date POST
$weight    = $_POST['weight']    ?? '';
$gender    = $_POST['gender']    ?? '';
$hours     = $_POST['hours']     ?? '';
$bac       = null;
$elimHours = null;
$elimMin   = null;
$error     = '';

// Procesare formular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_numeric($weight) || !in_array($gender, ['male','female']) || !is_numeric($hours)) {
        $error = 'Completează corect greutatea, genul și timpul.';
    } else {
        $weight = floatval($weight);
        $hours  = floatval($hours);

        $vols = $_POST['volume'] ?? [];
        $abvs = $_POST['abv']    ?? [];

        // calcul total grame alcool pur
        $totalAlcohol = 0.0;
        for ($i = 0; $i < count($vols); $i++) {
            $v = floatval($vols[$i]);
            $a = floatval($abvs[$i]);
            if ($v > 0 && $a > 0) {
                // densitate etanol = 0.789 g/ml
                $grams = $v * ($a/100.0) * 0.789;
                $totalAlcohol += $grams;
            }
        }

        if ($totalAlcohol <= 0) {
            $error = 'Adaugă cel puțin o băutură validă.';
        } else {
            // Widmark
            $r    = $gender==='male'?0.68:0.55;
            $beta = 0.015;

            // BAC inițial și curent
            $bac0 = ($totalAlcohol * 0.806) / ($weight * $r);
            $bac  = max(0, $bac0 - $beta * $hours);

            // timp până la eliminare completă
            $elim = $bac / $beta;
            $elim = max(0, $elim);
            $elimHours = floor($elim);
            $elimMin   = floor(($elim - $elimHours)*60);
        }
    }
}

// lista de opțiuni predefinite
$options = [
  'Bere'=>5.0, 'Bere Strong'=>8.0,
  'Cidru'=>4.5, 'Vin roșu'=>13.0,
  'Vin alb'=>12.5, 'Whiskey'=>40.0,
  'Vodka'=>40.0, 'Rom'=>37.5,
  'Tequila'=>38.0, 'Gin'=>37.5,
  'Lichior'=>25.0, 'Altele'=>''
];

// pregătim HTML-ul pentru dropdown
$drinkOptionsHtml = '';
foreach($options as $label=>$abvOpt) {
    $display = is_numeric($abvOpt) ? "{$label} ({$abvOpt}%)" : $label;
    $drinkOptionsHtml .= "<option value=\"{$label}\" data-abv=\"{$abvOpt}\">{$display}</option>";
}

// includem header-ul
include '../includes/header.php';
?>
<div class="container">
  <div class="row">
    <!-- Sidebar -->
    

    <!-- Conținut principal -->
    <div class="col-md-9">
      <div class="hero" style="background:linear-gradient(135deg,#ff416c,#ff4b2b);color:#fff;padding:60px;text-align:center;margin-bottom:2rem;">
        <h1>Calculator Alcoolemie Avansat</h1>
        <p>Lista predefinită de băuturi și ABV automat</p>
      </div>

      <div class="alert alert-warning" role="alert">
        <strong>Notă:</strong> acest instrument oferă doar o estimare orientativă a concentrației de alcool în sânge (BAC) și a timpului necesar eliminării alcoolului. <u>Nu este un dispozitiv medical</u> și nu trebuie folosit pentru decizii critice legate de siguranța rutieră.
      </div>

      <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="card calc-card mb-4" style="border:none;border-radius:.75rem;box-shadow:0 .5rem 1rem rgba(0,0,0,0.1);">
        <div class="card-header" style="background:#fff;font-weight:600;font-size:1.25rem;border-bottom:none;">
          Completează datele
        </div>
        <div class="card-body">
          <form method="post" id="alcForm">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Greutate (kg)</label>
                <input type="number" step="0.1" name="weight" class="form-control"
                       value="<?=htmlspecialchars($weight)?>" required>
              </div>
              <div class="form-group col-md-4">
                <label>Gen</label>
                <select name="gender" class="form-control" required>
                  <option value="">— Alege —</option>
                  <option value="male"   <?= $gender==='male'?'selected':''?>>Bărbat</option>
                  <option value="female" <?= $gender==='female'?'selected':''?>>Femeie</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Timp scurs (ore)</label>
                <input type="number" step="0.1" name="hours" class="form-control"
                       value="<?=htmlspecialchars($hours)?>" required>
              </div>
            </div>

            <hr>

            <h5>Băuturi consumate</h5>
            <table class="table drinks-table">
              <thead>
                <tr>
                  <th>Băutură</th>
                  <th>Volum (ml)</th>
                  <th>Concentrație (%)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="drinksBody">
                <?php
                  $vols = $_POST['volume'] ?? [''];
                  $abvs = $_POST['abv']    ?? [''];
                  $names= $_POST['drink_name'] ?? [''];
                  $n    = max(count($vols),1);
                  for ($i=0; $i<$n; $i++):
                    $volVal  = htmlspecialchars($vols[$i]  ?? '');
                    $abvVal  = htmlspecialchars($abvs[$i]  ?? '');
                    $selName = htmlspecialchars($names[$i] ?? '');
                ?>
                <tr>
                  <td>
                    <select name="drink_name[]" class="form-control drink-select" required>
                      <option value="">— Alege băutură —</option>
                      <?= $drinkOptionsHtml ?>
                    </select>
                  </td>
                  <td><input type="number" step="1" name="volume[]" class="form-control" value="<?= $volVal ?>" required></td>
                  <td><input type="number" step="0.1" name="abv[]" class="form-control" value="<?= $abvVal ?>" required></td>
                  <td><button type="button" class="btn btn-sm btn-danger removeDrink">&times;</button></td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
            <button type="button" id="addDrink" class="btn btn-secondary mb-3">
              <i class="fas fa-plus"></i> Adaugă băutură
            </button>

            <button type="submit" class="btn btn-primary btn-block" style="background:#ff4b2b;border:none;">
              Calculează BAC
            </button>
          </form>
        </div>
      </div>

      <?php if($bac !== null): ?>
        <div class="result" style="background:#f9f9f9;padding:1rem;border-radius:.5rem;margin-top:1rem;">
          <p><strong>Concentrație Alcool:</strong> <?= number_format($bac,2) ?> ‰</p>
          <p><strong>Timp până la eliminare:</strong> <?= $elimHours ?> ore și <?= $elimMin ?> minute</p>
        </div>
      <?php endif; ?>
    </div><!-- /.col-md-9 -->
  </div><!-- /.row -->
</div><!-- /.container -->

<?php include '../includes/footer.php'; ?>

<script>
// HTML pentru dropdown opțiuni
const drinkOptionsHtml = `<?= str_replace("`","'", $drinkOptionsHtml) ?>`;

// Creează un rând nou
function newRow() {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="drink_name[]" class="form-control drink-select" required>
        <option value="">— Alege băutură —</option>
        ${drinkOptionsHtml}
      </select>
    </td>
    <td><input type="number" step="1" name="volume[]" class="form-control" required></td>
    <td><input type="number" step="0.1" name="abv[]" class="form-control" required></td>
    <td><button type="button" class="btn btn-sm btn-danger removeDrink">&times;</button></td>
  `;
  return tr;
}

// Adaugă băutură
document.getElementById('addDrink').addEventListener('click', ()=>{
  document.getElementById('drinksBody').appendChild(newRow());
});
// Șterge rând băutură
document.getElementById('drinksBody').addEventListener('click', e=>{
  if (e.target.classList.contains('removeDrink')) {
    e.target.closest('tr').remove();
  }
});
// Când se schimbă băutura, completează ABV automat
document.getElementById('drinksBody').addEventListener('change', e=>{
  if (e.target.classList.contains('drink-select')) {
    const sel = e.target;
    const abvInput = sel.closest('tr').querySelector('input[name="abv[]"]');
    abvInput.value = sel.selectedOptions[0].dataset.abv || '';
  }
});
</script>
