<?php
// includes/db.php

// Încarcă mai întâi configuraţia (.env + variabile)
require_once __DIR__ . '/config.php';

// Deschide conexiunea
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Conectare la baza de date eşuată: " . $conn->connect_error);
}

// Asigură-te că foloseşti UTF-8
$conn->set_charset('utf8mb4');
