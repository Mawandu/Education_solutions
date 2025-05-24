<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et que son rôle est autorisé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['individu', 'eleve', 'enseignant'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['book_id']) || !is_numeric($_GET['book_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$book_id = (int)$_GET['book_id'];

// Selon le rôle, utiliser la table appropriée
if ($_SESSION['role'] === 'individu') {
    $stmt = $pdo->prepare("
        SELECT b.pdf_path
        FROM books b
        JOIN borrows bor ON b.id = bor.book_id
        WHERE bor.user_id = :user_id 
          AND bor.book_id = :book_id 
          AND bor.return_date IS NULL
    ");
    $params = ['user_id' => $user_id, 'book_id' => $book_id];
} else {
    // Pour les rôles "eleve" et "enseignant", la lecture se fait via la table ecole_lecture.
    // On accepte l'enregistrement individuel (user_id = :user_id) ou groupé (user_id IS NULL)
    $stmt = $pdo->prepare("
        SELECT b.pdf_path
        FROM books b
        JOIN ecole_lecture el ON b.id = el.book_id
        WHERE el.book_id = :book_id 
          AND el.role = :role 
          AND (el.user_id = :user_id OR el.user_id IS NULL)
    ");
    $params = [
        'book_id' => $book_id,
        'user_id' => $user_id,
        'role' => $_SESSION['role']
    ];
}

$stmt->execute($params);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book || empty($book['pdf_path']) || !file_exists(__DIR__ . '/' . $book['pdf_path'])) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Envoyer le PDF au navigateur
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($book['pdf_path']) . '"');
readfile(__DIR__ . '/' . $book['pdf_path']);
