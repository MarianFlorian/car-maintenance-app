<?php
// includes/config.php

// 1) Autoload + .env
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// 2) Configurare DB
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? '';
if (!$DB_NAME) {
    throw new \Exception("DB_NAME nu este setată în .env");
}

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    throw new \Exception("Conexiune DB eșuată: " . $conn->connect_error);
}

// 3) Chei API (opţional)
$OPENAI_API_KEY      = $_ENV['OPENAI_API_KEY']      ?? '';
$GOOGLE_MAPS_API_KEY = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
