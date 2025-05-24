<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a le rôle "individu" ou "ecole"
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['individu', 'ecole'])) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

if (!isset($_GET['book_id'])) {
    http_response_code(400);
    echo "ID du livre manquant.";
    exit;
}

$book_id = (int)$_GET['book_id'];
$user_id = $_SESSION['user_id'];

// Mettre à jour l'emprunt (marquer comme rendu)
$stmt = $pdo->prepare("UPDATE borrows SET return_date = NOW() WHERE book_id = :book_id AND user_id = :user_id AND return_date IS NULL");
$stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);

// Mettre à jour la disponibilité du livre
$stmt = $pdo->prepare("UPDATE books SET available = 1 WHERE id = :book_id");
$stmt->execute(['book_id' => $book_id]);

// Rediriger vers la page appropriée
if ($_SESSION['role'] === 'individu') {
    header("Location: borrow_book.php");
} else {
    header("Location: manage_books.php");
}
exit;
