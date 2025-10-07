<?php
// pages/add_vehicle.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require '../includes/db.php';

// Inițializare variabile de feedback
$success = "";
$error = "";

// Procesarea formularului dacă a fost trimis (metoda POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preluăm și curățăm datele trimise
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);

    // Validare simplă (poți adăuga validări suplimentare aici)
    if (empty($brand) || empty($model) || $year <= 0) {
        $error = "Toate câmpurile sunt obligatorii și anul trebuie să fie un număr valid.";
    } else {
        // Inserare în tabelul vehicles cu denumirile de coloană corecte: brand, model, year
        $stmt = $conn->prepare("INSERT INTO vehicles (user_id, brand, model, year) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $_SESSION['user_id'], $brand, $model, $year);
        if ($stmt->execute()) {
            $success = "Vehicul adăugat cu succes!";
        } else {
            $error = "Eroare la adăugarea vehiculului: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="row">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="col-md-9">
        <h2>Adaugă Vehicul</h2>

        <!-- Afișare mesaje de succes sau eroare -->
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="add_vehicle.php" method="post">
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Model</label>
                <input type="text" name="model" class="form-control" required>
            </div>
            <div class="form-group">
                <label>An (Year)</label>
                <input type="number" name="year" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success">Adaugă Vehicul</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
