<?php
require 'db_connect.php';

$query = isset($_GET['query']) ? $_GET['query'] : '';
$sql = "SELECT * FROM livres WHERE titre LIKE ? OR auteur LIKE ?";
$stmt = $conn->prepare($sql);
$search = "%" . $query . "%";
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultats de recherche</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Résultats pour "<?php echo htmlspecialchars($query); ?>"</h1>
    <ul>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <li>
                <?php echo htmlspecialchars($row['titre']); ?> - <?php echo htmlspecialchars($row['auteur']); ?>
                <a href="details.php?id=<?php echo $row['id']; ?>">Détails</a>
            </li>
        <?php } ?>
    </ul>
    <a href="index.html">Retour à l'accueil</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
