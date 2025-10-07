<?php
// pages/settings.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require '../includes/db.php';

$success = '';
$error   = '';

// Fetch current email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($current_email);
$stmt->fetch();
$stmt->close();

// Handle delete account
if (isset($_POST['delete_account'])) {
    // Delete user and cascade delete related data
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    if ($stmt->execute()) {
        $stmt->close();
        // Destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: ./landing.php");
        exit();
    } else {
        $error = 'A intervenit o eroare la ștergerea contului.';
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {
    $new_email    = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_pw   = $_POST['confirm_password'] ?? '';

    // Email change
    if ($new_email && $new_email !== $current_email) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalid.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $new_email, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Email deja folosit de alt cont.";
            }
            $stmt->close();
        }
        if (!$error) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $new_email, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            $success = "Email actualizat cu succes.";
            $current_email = $new_email;
        }
    }

    // Password change
    if (!$error && $new_password) {
        if (strlen($new_password) < 6) {
            $error = "Parola trebuie să aibă cel puțin 6 caractere.";
        } elseif ($new_password !== $confirm_pw) {
            $error = "Noile parole nu coincid.";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            $success .= $success ? "<br>Parolă actualizată cu succes." : "Parolă actualizată cu succes.";
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="/assets/css/dashboard.css">

<div class="container-fluid p-0">
  <!-- Hero -->
  <div class="dashboard-hero" style="background: linear-gradient(135deg,#6a11cb,#2575fc);">
    <div class="hero-content">
      <h1>Setări Cont</h1>
      <p>Actualizează email-ul, parola sau șterge contul</p>
    </div>
  </div>

  <div class="px-4 py-4">
    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <h2 class="section-heading">Profil</h2>
    <div class="card" style="max-width:600px;">
      <div class="card-body">
        <form method="post">
          <div class="form-row">
            <div class="form-group col-12 col-md-6">
              <label class="small">Email</label>
              <input type="email"
                     name="email"
                     class="form-control form-control-sm"
                     value="<?= htmlspecialchars($current_email) ?>"
                     required>
            </div>
            <div class="form-group col-12 col-md-6">
              <label class="small">Parola nouă</label>
              <input type="password"
                     name="new_password"
                     class="form-control form-control-sm"
                     placeholder="Lasă necompletat pentru a păstra actuala">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-12 col-md-6">
              <label class="small">Confirmă parola</label>
              <input type="password"
                     name="confirm_password"
                     class="form-control form-control-sm"
                     placeholder="Reintrodu noua parolă">
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">Salvează</button>
        </form>

        <!-- Formular ștergere cont -->
        <form method="post" onsubmit="return confirm('Ești sigur că vrei să-ți ștergi contul? Această acțiune este irevocabilă.');" class="mt-3">
          <button type="submit" name="delete_account" class="btn btn-sm btn-danger">Șterge Cont</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
