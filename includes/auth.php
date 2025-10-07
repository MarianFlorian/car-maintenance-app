<?php
// includes/auth.php

// 1) Încarcă configurația (DB, API keys etc.)
require_once __DIR__ . '/config.php';

// 2) Pornește sesiunea
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3) Verificare autentificare
if (empty($_SESSION['user_id'])) {
    // Nu e logat → du-l la login
    header('Location: /login.php');
    exit();
}

// 4) (Opțional) Protejare doar pentru admin
//    Dacă înainte de include ai definit:
//      $requireAdmin = true;
//    atunci verifică rolul:
if (!empty($requireAdmin) && ($_SESSION['role'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acces interzis.';
    exit();
}
