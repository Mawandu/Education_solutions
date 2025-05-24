<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 1) { // Exemple : ID 1 = admin
    header("Location: login.php");
    exit;
}

// Récupérez les écoles et bibliothèques non validées
$query = "SELECT e.*, l.email, 'ecole' as type FROM ecoles e JOIN lecteurs l ON e.id = l.id_ecole WHERE e.valide = FALSE
          UNION
          SELECT b.*, l.email, 'bibliotheque' as type FROM bibliotheques b JOIN lecteurs l ON b.id = l.id_bibliotheque WHERE b.valide = FALSE";
$stmt = $pdo->prepare($query);
$stmt->execute();
$entites = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $type = $_POST['type'];
    $table = ($type === 'ecole') ? 'ecoles' : 'bibliotheques';
    $query = "UPDATE $table SET valide = TRUE WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    header("Location: admin_validate.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Validation des Entités</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Validation des Écoles et Bibliothèques</h1>
        <p><a href="index.php">Retour à l'accueil</a></p>
    </header>
    <main>
        <section class="wishlist-section">
            <?php if (empty($entites)): ?>
                <p>Aucune entité en attente de validation.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Adresse</th>
                            <th>Contact</th>
                            <th>Pays</th>
                            <th>Ville</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entites as $entite): ?>
                            <tr>
                                <td><?= ucfirst($entite['type']) ?></td>
                                <td><?= htmlspecialchars($entite['type'] === 'ecole' ? $entite['nom_ecole'] : $entite['nom_bibliotheque']) ?></td>
                                <td><?= htmlspecialchars($entite['email']) ?></td>
                                <td><?= htmlspecialchars($entite['adresse']) ?></td>
                                <td><?= htmlspecialchars($entite['contact']) ?></td>
                                <td><?= htmlspecialchars($entite['pays']) ?></td>
                                <td><?= htmlspecialchars($entite['ville']) ?></td>
                                <td>
                                    <form action="admin_validate.php" method="POST">
                                        <input type="hidden" name="id" value="<?= $entite['id'] ?>">
                                        <input type="hidden" name="type" value="<?= $entite['type'] ?>">
                                        <button type="submit">Valider</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
