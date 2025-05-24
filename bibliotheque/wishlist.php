<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'individu') {
    header('HTTP/1.1 403 Forbidden');
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Ajouter un livre à la liste de souhaits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book_id'])) {
    $book_id = (int)$_POST['add_book_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, book_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $book_id]);
    header("Location: wishlist.php");
    exit;
}

// Supprimer un livre de la liste de souhaits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book_id'])) {
    $book_id = (int)$_POST['delete_book_id'];
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$user_id, $book_id]);
    header("Location: wishlist.php");
    exit;
}

// Récupérer les livres dans la liste de souhaits
$stmt = $pdo->prepare("
    SELECT w.book_id, b.title, b.author, b.available
    FROM wishlist w
    JOIN books b ON w.book_id = b.id
    WHERE w.user_id = ?
");
$stmt->execute([$user_id]);
$wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les livres disponibles qui ne sont pas dans la liste de souhaits
$stmt = $pdo->prepare("
    SELECT id, title, author
    FROM books
    WHERE available = 1
    AND id NOT IN (SELECT book_id FROM wishlist WHERE user_id = ?)
");
$stmt->execute([$user_id]);
$available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ma liste de souhaits</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
</head>
<body>
    <h1>Ma liste de souhaits</h1>
    <a href="index.php" class="back-btn">Retour</a>

    <h2>Livres dans ma liste de souhaits</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Titre</th>
            <th>Auteur</th>
            <th>Disponible</th>
            <th>Action</th>
        </tr>
        <?php if (empty($wishlist)): ?>
            <tr><td colspan="5">Aucune livre dans votre liste de souhaits.</td></tr>
        <?php else: ?>
            <?php foreach ($wishlist as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['book_id']); ?></td>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td><?php echo htmlspecialchars($item['author']); ?></td>
                    <td><?php echo $item['available'] ? 'Oui' : 'Non'; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_book_id" value="<?php echo $item['book_id']; ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce livre de votre liste de souhaits ?');">Supprimer</button>
                        </form>
                        <?php if ($item['available']): ?>
                            <a href="borrow_book.php" class="action-btn green">Emprunter maintenant</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <h2>Ajouter un livre à ma liste de souhaits</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Titre</th>
            <th>Auteur</th>
            <th>Action</th>
        </tr>
        <?php if (empty($available_books)): ?>
            <tr><td colspan="4">Aucun livre disponible à ajouter.</td></tr>
        <?php else: ?>
            <?php foreach ($available_books as $book): ?>
                <tr>
                    <td><?php echo htmlspecialchars($book['id']); ?></td>
                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="add_book_id" value="<?php echo $book['id']; ?>">
                            <button type="submit" class="action-btn green">Ajouter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
