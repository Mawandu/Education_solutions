<?php
session_start();
require_once 'db_connect.php';

if (!isset($pdo)) {
    die("Erreur : Connexion à la base de données non disponible.");
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Traitement des actions
if (isset($_POST['add_user'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role = $_POST['role'] ?? '';
    if ($username && $email && $password && $role) {
        $query = "INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['username' => $username, 'email' => $email, 'password' => $password, 'role' => $role]);
        header("Location: admin_panel.php?message=Utilisateur ajouté");
        exit();
    }
}

if (isset($_POST['update_user']) && is_numeric($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    if ($username && $email) {
        $query = "UPDATE users SET username = :username, email = :email WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['username' => $username, 'email' => $email, 'id' => $user_id]);
        header("Location: admin_panel.php?message=Utilisateur mis à jour");
        exit();
    }
}

if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $user_id = $_GET['reset_password'];
    $new_password = password_hash('nouveau123', PASSWORD_DEFAULT);
    $query = "UPDATE users SET password = :password WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['password' => $new_password, 'id' => $user_id]);
    header("Location: admin_panel.php?message=Mot de passe réinitialisé à 'nouveau123'");
    exit();
}

if (isset($_GET['validate_library']) && is_numeric($_GET['validate_library'])) {
    $library_id = $_GET['validate_library'];
    $query = "UPDATE libraries SET validated = 1 WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $library_id]);
    header("Location: admin_panel.php?message=Bibliothèque validée");
    exit();
}

if (isset($_GET['validate_ecole']) && is_numeric($_GET['validate_ecole'])) {
    $ecole_id = $_GET['validate_ecole'];
    $query = "UPDATE ecoles SET valide = 1 WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $ecole_id]);
    header("Location: admin_panel.php?message=École validée");
    exit();
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $library_id = $_GET['delete'];
    $query = "DELETE FROM libraries WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $library_id]);
    header("Location: admin_panel.php?message=Bibliothèque supprimée");
    exit();
}

if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    $query = "DELETE FROM users WHERE id = :id AND role IN ('individu', 'ecole')";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $user_id]);
    header("Location: admin_panel.php?message=Lecteur supprimé");
    exit();
}

if (isset($_GET['update']) && is_numeric($_GET['update'])) {
    $library_id = $_GET['update'];
    $message = "Demande de mise à jour envoyée pour la bibliothèque ID $library_id.";
}

// Requêtes pour afficher les données
try {
    $query = "SELECT id, username, email, role, created_at FROM users WHERE role IN ('admin', 'bibliotheque') ORDER BY created_at";
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT u.id, u.username, u.email, u.role, u.entity_id FROM users u WHERE u.role = 'individu' ORDER BY u.id";
    $stmt = $pdo->query($query);
    $individus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT u.id, u.username, u.email, u.role, u.entity_id, 
                     e.nom_ecole, e.adresse, e.contact, e.pays, e.ville, e.valide 
              FROM users u 
              LEFT JOIN ecoles e ON u.entity_id = e.id 
              WHERE u.role = 'ecole' 
              ORDER BY u.id";
    $stmt = $pdo->query($query);
    $ecoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT l.id, l.name, l.location, l.contact, l.validated, u.username, u.id AS user_id 
              FROM libraries l 
              JOIN users u ON l.user_id = u.id 
              WHERE u.role = 'bibliotheque'";
    $stmt = $pdo->query($query);
    $libraries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de requête : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panneau Admin</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Panneau d'Administration</h1>
    <a href="index.php">Retour à l'accueil</a> | <a href="logout.php">Se déconnecter</a>

    <?php if (isset($_GET['message']) || isset($message)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_GET['message'] ?? $message); ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['edit_user']) && is_numeric($_GET['edit_user'])): ?>
        <?php
        $user_id = $_GET['edit_user'];
        $query = "SELECT username, email FROM users WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user):
        ?>
        <h2>Modifier l'utilisateur</h2>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <label>Nom d'utilisateur : <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></label><br>
            <label>Email : <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></label><br>
            <input type="submit" name="update_user" value="Mettre à jour">
        </form>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['details_ecole']) && is_numeric($_GET['details_ecole'])): ?>
        <?php
        $ecole_id = $_GET['details_ecole'];
        $query = "SELECT e.*, u.username FROM ecoles e JOIN users u ON e.id = u.entity_id WHERE e.id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $ecole_id]);
        $ecole = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ecole):
        ?>
        <h2>Détails de l'école</h2>
        <p>ID : <?php echo htmlspecialchars($ecole['id']); ?></p>
        <p>Nom : <?php echo htmlspecialchars($ecole['nom_ecole']); ?></p>
        <p>Adresse : <?php echo htmlspecialchars($ecole['adresse']); ?></p>
        <p>Contact : <?php echo htmlspecialchars($ecole['contact']); ?></p>
        <p>Pays : <?php echo htmlspecialchars($ecole['pays']); ?></p>
        <p>Ville : <?php echo htmlspecialchars($ecole['ville']); ?></p>
        <p>Validée : <?php echo $ecole['valide'] ? 'Oui' : 'Non'; ?></p>
        <p>Propriétaire : <?php echo htmlspecialchars($ecole['username']); ?></p>
        <a href="admin_panel.php">Retour</a>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Ajouter un utilisateur</h2>
    <form method="POST">
        <label>Nom d'utilisateur : <input type="text" name="username" required></label><br>
        <label>Email : <input type="email" name="email" required></label><br>
        <label>Mot de passe : <input type="password" name="password" required></label><br>
        <label>Rôle : 
            <select name="role">
                <option value="admin">Admin</option>
                <option value="bibliotheque">Bibliothèque</option>
                <option value="ecole">École</option>
                <option value="individu">Individu</option>
            </select>
        </label><br>
        <input type="submit" name="add_user" value="Ajouter">
    </form>

    <?php if (isset($_GET['details_library']) && is_numeric($_GET['details_library'])): ?>
        <?php
        $library_id = $_GET['details_library'];
        $query = "SELECT l.*, u.username FROM libraries l JOIN users u ON l.user_id = u.id WHERE l.id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $library_id]);
        $library = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($library):
        ?>
        <h2>Détails de la bibliothèque</h2>
        <p>ID : <?php echo htmlspecialchars($library['id']); ?></p>
        <p>Nom : <?php echo htmlspecialchars($library['name']); ?></p>
        <p>Localisation : <?php echo htmlspecialchars($library['location']); ?></p>
        <p>Contact : <?php echo htmlspecialchars($library['contact']); ?></p>
        <p>Validée : <?php echo $library['validated'] ? 'Oui' : 'Non'; ?></p>
        <p>Propriétaire : <?php echo htmlspecialchars($library['username']); ?></p>
        <a href="admin_panel.php">Retour</a>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Utilisateurs (Admin et Bibliothèques)</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nom d'utilisateur</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Date de création</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                <td>
                    <a href="admin_panel.php?edit_user=<?php echo $user['id']; ?>">Modifier</a> |
                    <a href="admin_panel.php?reset_password=<?php echo $user['id']; ?>" onclick="return confirm('Réinitialiser le mot de passe ?');">Réinitialiser mot de passe</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Lecteurs - Individus</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nom d'utilisateur</th>
            <th>Email</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($individus as $individu): ?>
            <tr>
                <td><?php echo htmlspecialchars($individu['id']); ?></td>
                <td><?php echo htmlspecialchars($individu['username']); ?></td>
                <td><?php echo htmlspecialchars($individu['email']); ?></td>
                <td>
                    <a href="admin_panel.php?edit_user=<?php echo $individu['id']; ?>">Modifier</a> |
                    <a href="admin_panel.php?reset_password=<?php echo $individu['id']; ?>" onclick="return confirm('Réinitialiser le mot de passe ?');">Réinitialiser mot de passe</a> |
                    <a href="admin_panel.php?delete_user=<?php echo $individu['id']; ?>" onclick="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');">Supprimer</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Lecteurs - Écoles</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nom d'utilisateur</th>
            <th>Email</th>
            <th>Nom École</th>
            <th>Adresse</th>
            <th>Contact</th>
            <th>Pays</th>
            <th>Ville</th>
            <th>Validée</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($ecoles as $ecole): ?>
            <tr>
                <td><?php echo htmlspecialchars($ecole['id']); ?></td>
                <td><?php echo htmlspecialchars($ecole['username']); ?></td>
                <td><?php echo htmlspecialchars($ecole['email']); ?></td>
                <td><?php echo htmlspecialchars($ecole['nom_ecole'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ecole['adresse'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ecole['contact'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ecole['pays'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ecole['ville'] ?? '-'); ?></td>
                <td><?php echo ($ecole['valide'] ?? 0) ? 'Oui' : 'Non'; ?></td>
                <td>
                    <a href="admin_panel.php?details_ecole=<?php echo $ecole['entity_id']; ?>">Détails</a> |
                    <?php if (!($ecole['valide'] ?? 0)): ?>
                        <a href="admin_panel.php?validate_ecole=<?php echo $ecole['entity_id']; ?>" onclick="return confirm('Voulez-vous valider cette école ?');">Valider</a> |
                    <?php endif; ?>
                    <a href="admin_panel.php?edit_user=<?php echo $ecole['id']; ?>">Modifier</a> |
                    <a href="admin_panel.php?reset_password=<?php echo $ecole['id']; ?>" onclick="return confirm('Réinitialiser le mot de passe ?');">Réinitialiser mot de passe</a> |
                    <a href="admin_panel.php?delete_user=<?php echo $ecole['id']; ?>" onclick="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');">Supprimer</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Bibliothèques</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Localisation</th>
            <th>Contact</th>
            <th>Validée</th>
            <th>Propriétaire</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($libraries as $library): ?>
            <tr>
                <td><?php echo htmlspecialchars($library['id']); ?></td>
                <td><?php echo htmlspecialchars($library['name']); ?></td>
                <td><?php echo htmlspecialchars($library['location']); ?></td>
                <td><?php echo htmlspecialchars($library['contact']); ?></td>
            <td><?php echo $library['validated'] ? 'Oui' : 'Non'; ?></td>
                <td><?php echo htmlspecialchars($library['username']); ?></td>
                <td>
                    <a href="admin_panel.php?details_library=<?php echo $library['id']; ?>">Détails</a> |
                    <?php if (!$library['validated']): ?>
                        <a href="admin_panel.php?validate_library=<?php echo $library['id']; ?>" onclick="return confirm('Voulez-vous valider cette bibliothèque ?');">Valider</a> |
                    <?php endif; ?>
                    <a href="admin_panel.php?delete=<?php echo $library['id']; ?>" onclick="return confirm('Voulez-vous vraiment supprimer cette bibliothèque ?');">Supprimer</a> |
                    <a href="admin_panel.php?update=<?php echo $library['id']; ?>">Demander mise à jour</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
