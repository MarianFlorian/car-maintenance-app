<?php
// register.php

session_start();
require 'includes/db.php';

// 1) Dacă ești deja autentificat, redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';

// 2) Procesare formular POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email             = trim($_POST['email'] ?? '');
    $password          = $_POST['password'] ?? '';
    $password_confirm  = $_POST['password_confirm'] ?? '';

    if ($email === '' || $password === '' || $password_confirm === '') {
        $error = "Te rog completează toate câmpurile.";
    }
    elseif ($password !== $password_confirm) {
        $error = "Parolele nu corespund.";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $password_hash);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            header("Location: pages/dashboard.php");
            exit();
        } else {
            $error = "Eroare la înregistrare. Email-ul poate exista deja.";
        }
        $stmt->close();
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
  <div class="card shadow p-4" style="width: 100%; max-width: 500px;">
    <h2 class="text-center mb-4 text-primary">Înregistrare cont nou</h2>

    <?php if ($error): ?>
      <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="register.php" onsubmit="return validateForm();">
      <div class="form-group mb-3">
        <label for="email">Adresa de email</label>
        <input type="email" id="email" name="email"
               class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label for="password">Parolă</label>
        <input type="password" id="password" name="password"
               class="form-control" required>
      </div>

      <div class="form-group mb-4">
        <label for="password_confirm">Confirmă parola</label>
        <input type="password" id="password_confirm" name="password_confirm"
               class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary w-100">Creează cont</button>
    </form>

    <p class="mt-3 text-center">
      Ai deja cont? <a href="login.php" class="text-primary">Autentifică-te aici</a>
    </p>
  </div>
</div>

<script>
function validateForm() {
  const pwd = document.getElementById('password').value;
  const conf = document.getElementById('password_confirm').value;
  if (pwd !== conf) {
    alert("Parolele nu corespund.");
    return false;
  }
  return true;
}
</script>

<?php include 'includes/footer.php'; ?>
