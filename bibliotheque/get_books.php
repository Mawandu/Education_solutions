<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$domain_id = $_GET['domain_id'] ?? '';
if (!$domain_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, title, author FROM books WHERE domain = :domain AND available = 1");
$stmt->execute(['domain' => $domain_id]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($books);
?>
