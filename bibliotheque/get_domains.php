<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$library_id = $_GET['library_id'] ?? '';
if (!$library_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT DISTINCT domain AS id, domain AS name FROM books WHERE library_id = :library_id AND available = 1");
$stmt->execute(['library_id' => $library_id]);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($domains);
?>
