<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
if (!$query) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, title, author FROM books WHERE (title LIKE :query OR author LIKE :query) AND available = 1 LIMIT 10");
$stmt->execute(['query' => "%$query%"]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($books);
?>
