<?php
// login.php

session_start();
require __DIR__ . '/includes/config.php';

$error = '';
$email = '';

// Dacă deja ești logat, redirecționează
if (!empty($_SESSION['user_id'])) {
    $dest = !empty($_SESSION['is_admin'])
          ? './pages/admin_dashboard.php'
          : './pages/dashboard.php';
    header("Location: $dest");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =            $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Te rog completează toate câmpurile.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, password, role
              FROM users
             WHERE email = ?
             LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $password_hash, $role);
            $stmt->fetch();

            if (password_verify($password, $password_hash)) {
                $_SESSION['user_id']  = $id;
                $_SESSION['role']     = $role;
                $_SESSION['is_admin'] = ($role === 'admin');

                $dest = ($role === 'admin')
                      ? './pages/admin_dashboard.php'
                      : './pages/dashboard.php';
                header("Location: $dest");
                exit();
            } else {
                $error = 'Parolă incorectă.';
            }
        } else {
            $error = 'Utilizatorul nu există.';
        }
        $stmt->close();
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-content d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow-sm p-4" style="max-width:400px; width:100%;">
    <h3 class="card-title text-center mb-3">Autentificare</h3>

    <?php if ($error): ?>
      <div class="alert alert-danger text-center small">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
      <div class="form-group mb-3">
        <label for="email" class="small font-weight-bold">Email</label>
        <input type="email"
               id="email"
               name="email"
               class="form-control form-control-sm"
               value="<?= htmlspecialchars($email) ?>"
               
               required>
      </div>

      <div class="form-group mb-4">
        <label for="password" class="small font-weight-bold">Parolă</label>
        <input type="password"
               id="password"
               name="password"
               class="form-control form-control-sm"
               
               required>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-sm">
        Conectare
      </button>
    </form>

    <p class="mt-3 text-center small">
      Nu ai cont? <a href="register.php">Înregistrează-te</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
