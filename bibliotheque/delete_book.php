<?php
require_once 'db_connect.php';
session_start();

// Vérification des permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bibliotheque') {
    $_SESSION['error'] = "Accès non autorisé";
    header("Location: login.php");
    exit;
}

// Validation CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token de sécurité invalide";
    header("Location: manage_library.php");
    exit;
}

$book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);

try {
    $pdo->beginTransaction();

    // Suppression en cascade complète
    $tables = [
        'ecole_lecture', 
        'ecole_books',
        'borrows',
        'book_requests',
        'wishlist'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE book_id = :book_id");
        $stmt->execute(['book_id' => $book_id]);
    }

    // Suppression finale du livre
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = :book_id");
    $stmt->execute(['book_id' => $book_id]);

    $pdo->commit();
    $_SESSION['success'] = "Livre et toutes ses dépendances supprimés";

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur de suppression : " . $e->getMessage();
}

header("Location: manage_library.php");
exit;