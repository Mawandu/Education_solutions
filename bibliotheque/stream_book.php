<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = $_GET['book_id'] ?? '';

// Vérifier que le livre est actuellement emprunté par l'utilisateur
$stmt = $pdo->prepare("
    SELECT b.pdf_path 
    FROM books b 
    JOIN borrows bor ON b.id = bor.book_id 
    WHERE b.id = :book_id 
    AND bor.user_id = :user_id 
    AND bor.return_date IS NULL
");
$stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book || !$book['pdf_path']) {
    http_response_code(404);
    echo "Livre non trouvé ou non accessible.";
    exit;
}

$file_path = __DIR__ . '/' . $book['pdf_path'];

// Vérifier que le chemin est un fichier et non un répertoire
if (!is_file($file_path)) {
    http_response_code(404);
    error_log("Erreur dans stream_book.php : Le chemin '$file_path' n'est pas un fichier valide.");
    echo "Erreur : Le fichier PDF n'est pas accessible.";
    exit;
}

// Vérifier que le fichier existe
if (!file_exists($file_path)) {
    http_response_code(404);
    error_log("Erreur dans stream_book.php : Le fichier '$file_path' n'existe pas.");
    echo "Erreur : Le fichier PDF n'existe pas.";
    exit;
}

// Vérifier la taille du fichier
$file_size = filesize($file_path);
if ($file_size === 0) {
    http_response_code(500);
    error_log("Erreur dans stream_book.php : Le fichier '$file_path' est vide (taille = 0 octets).");
    echo "Erreur : Le fichier PDF est vide.";
    exit;
}

// Configurer les en-têtes pour le streaming
header('Content-Type: application/pdf');
header('Content-Length: ' . $file_size);
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');

// Lire et envoyer le fichier par morceaux
$handle = fopen($file_path, 'rb');
if ($handle === false) {
    http_response_code(500);
    error_log("Erreur dans stream_book.php : Impossible d'ouvrir le fichier '$file_path'.");
    echo "Erreur : Impossible d'ouvrir le fichier PDF.";
    exit;
}

while (!feof($handle)) {
    $buffer = fread($handle, 8192);
    if ($buffer === false) {
        error_log("Erreur dans stream_book.php : Échec de la lecture du fichier '$file_path'.");
        break;
    }
    echo $buffer;
    flush();
}

fclose($handle);
exit;
