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

// Vérifier que le livre existe, est disponible et récupérer la durée d'emprunt
$stmt = $pdo->prepare("SELECT available, borrow_duration FROM books WHERE id = :book_id");
$stmt->execute(['book_id' => $book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book || !$book['available']) {
    http_response_code(400);
    echo "Ce livre n'est pas disponible.";
    exit;
}

// Enregistrer l'emprunt dans la table borrows pour tous les types d'utilisateur
$stmt = $pdo->prepare("INSERT INTO borrows (user_id, book_id, borrow_date) VALUES (:user_id, :book_id, NOW())");
$stmt->execute(['user_id' => $user_id, 'book_id' => $book_id]);

// Si l'utilisateur est une école, enregistrer aussi dans la table ecole_books
if ($_SESSION['role'] === 'ecole') {
    // Récupérer l'école associée à cet utilisateur
    $stmt = $pdo->prepare("SELECT id FROM ecoles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $ecole = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ecole) {
        die("École non trouvée.");
    }
    $ecole_id = $ecole['id'];

    // Calculer la date d'emprunt et la date d'échéance
    $borrow_date = new DateTime();    
    $borrow_duration = $book['borrow_duration'] ?? 7; // 7 jours par défaut
    $echeance_date = (clone $borrow_date)->modify("+{$borrow_duration} days");

    // Insérer dans ecole_books avec status "active"
    $stmt = $pdo->prepare("INSERT INTO ecole_books (ecole_id, book_id, borrow_date, echeance_date, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute([
        $ecole_id,
        $book_id,
        $borrow_date->format("Y-m-d H:i:s"),
        $echeance_date->format("Y-m-d H:i:s")
    ]);
}

// Mettre à jour la disponibilité du livre (le livre devient indisponible)
$stmt = $pdo->prepare("UPDATE books SET available = 0 WHERE id = :book_id");
$stmt->execute(['book_id' => $book_id]);

// Rediriger vers la page appropriée
if ($_SESSION['role'] === 'individu') {
    header("Location: borrow_book.php");
} else {
    header("Location: borrow_book.php");
}
exit;
?>
