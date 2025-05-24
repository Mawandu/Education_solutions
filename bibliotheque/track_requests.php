<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION['user_id'];

// Récupérer les demandes de l'école
$stmt = $pdo->prepare("
    SELECT br.id, br.request_date, br.status, b.title, b.author
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.school_id = ?
    ORDER BY br.request_date DESC
");
$stmt->execute([$school_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivre les demandes</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
</head>
<body>
    <h1>Suivre les demandes</h1>
    <a href="index.php" class="back-btn">Retour</a>

    <table>
        <tr>
            <th>ID</th>
            <th>Titre</th>
            <th>Auteur</th>
            <th>Date de demande</th>
            <th>Statut</th>
        </tr>
        <?php if (empty($requests)): ?>
            <tr><td colspan="5">Aucune demande pour le moment.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                    <td><?php echo htmlspecialchars($request['title']); ?></td>
                    <td><?php echo htmlspecialchars($request['author']); ?></td>
                    <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                    <td class="status-<?php echo strtolower($request['status']); ?>">
                        <?php echo htmlspecialchars(ucfirst($request['status'])); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
