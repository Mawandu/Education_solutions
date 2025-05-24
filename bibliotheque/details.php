<?php
require 'db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT * FROM livres WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$livre = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$livre) {
    die("Livre non trouvé.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails du livre</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1><?php echo htmlspecialchars($livre['titre']); ?></h1>
    <p><strong>Auteur :</strong> <?php echo htmlspecialchars($livre['auteur']); ?></p>
    <p><strong>Description :</strong> <?php echo htmlspecialchars($livre['description']); ?></p>
    <p><strong>Maison d'édition :</strong> <?php echo htmlspecialchars($livre['maison_edition']); ?></p>
    <p><strong>Exemplaires :</strong> <?php echo $livre['nombre_exemplaire']; ?></p>
    <form action="wishlist.php" method="POST">
        <input type="hidden" name="id_livre" value="<?php echo $livre['id']; ?>">
        <input type="hidden" name="id_lecteur" value="1">
        <button type="submit">Ajouter à ma liste</button>
    </form>
    <a href="edit_book.php?id=<?php echo $livre['id']; ?>">Modifier</a>
    <form action="delete_book.php" method="POST" style="display:inline;">
        <input type="hidden" name="id" value="<?php echo $livre['id']; ?>">
        <button type="submit" style="color:red;">Supprimer</button>
    </form>
    <br>
    <a href="results.php?query=">Retour aux résultats</a>
</body>
</html>

<?php
$conn->close();
?>
