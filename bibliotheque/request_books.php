<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION['user_id'];

// Gérer la soumission des demandes (plusieurs livres à la fois)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ids'])) {
    $book_ids = $_POST['book_ids'];
    $stmt = $pdo->prepare("INSERT INTO book_requests (school_id, book_id) VALUES (?, ?)");
    foreach ($book_ids as $book_id) {
        $book_id = (int)$book_id;
        $stmt->execute([$school_id, $book_id]);
    }
    $success = "Demandes envoyées avec succès !";
}

// Gérer le filtrage des livres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = $search ? "AND (b.title LIKE :search OR b.author LIKE :search)" : "";
$search_param = $search ? "%$search%" : null;

// Récupérer les livres disponibles (non demandés ou non empruntés)
$query = "
    SELECT b.id, b.title, b.author
    FROM books b
    WHERE b.available = 1
    AND b.id NOT IN (
        SELECT book_id FROM book_requests WHERE school_id = :school_id AND status = 'pending'
    )
    AND b.id NOT IN (
        SELECT book_id FROM borrows WHERE return_date IS NULL
    )
    $search_query
";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
if ($search) {
    $stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
}
$stmt->execute();
$available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demander des livres</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
</head>
<body>
    <h1>Demander des livres</h1>
    <a href="index.php" class="back-btn">Retour</a>

    <?php if (isset($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <!-- Formulaire de recherche -->
    <form method="GET" action="request_books.php" class="search-form">
        <input type="text" name="search" placeholder="Rechercher par titre ou auteur..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="action-btn green">Rechercher</button>
    </form>

    <!-- Formulaire pour demander plusieurs livres -->
    <form method="POST" action="request_books.php">
        <table>
            <tr>
                <th>Sélectionner</th>
                <th>ID</th>
                <th>Titre</th>
                <th>Auteur</th>
            </tr>
            <?php if (empty($available_books)): ?>
                <tr><td colspan="4">Aucun livre disponible pour le moment.</td></tr>
            <?php else: ?>
                <?php foreach ($available_books as $book): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="book_ids[]" value="<?php echo $book['id']; ?>">
                        </td>
                        <td><?php echo htmlspecialchars($book['id']); ?></td>
                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        <?php if (!empty($available_books)): ?>
            <button type="submit" class="action-btn green">Envoyer les demandes</button>
        <?php endif; ?>
    </form>
</body>
</html>
