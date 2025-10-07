<?php
// admin_user_detail.php
require '../includes/auth.php';
require '../includes/db.php';

$id = intval($_GET['id'] ?? 0);

// 1) Fetch user info
$stmt = $conn->prepare("
  SELECT id, email, role, created_at
  FROM users
  WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2) Fetch user's vehicles
$stmt = $conn->prepare("
  SELECT id, brand, model, year
  FROM vehicles
  WHERE user_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$user['vehicles'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3) Return JSON
header('Content-Type: application/json');
echo json_encode($user, JSON_UNESCAPED_UNICODE);
