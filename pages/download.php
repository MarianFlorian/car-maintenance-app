<?php
// download.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Trebuie să te autentifici.');
}
require __DIR__ . '/../includes/db.php';

// 1) Ia parametrul ID
$id = intval($_GET['id'] ?? 0);

// 2) Verifică în baza de date ownership-ul
$stmt = $conn->prepare("
  SELECT file_path
    FROM documents
   WHERE id=? AND user_id=?
");
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Document inexistent.');
}

// 3) Construiește calea reală
$fullPath = __DIR__ . '/../' . $row['file_path'];
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Fișier lipsă.');
}

// 4) Detectează MIME type şi trimite header-ele
$mime = mime_content_type($fullPath);
header('Content-Type: ' . $mime);

if (strpos($mime, 'image/') === 0) {
    // afișăm inline în browser
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
} else {
    // forțează download pentru PDF, DOC etc.
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
}

header('Content-Length: ' . filesize($fullPath));

// 5) Trimite conținutul
readfile($fullPath);
exit;
