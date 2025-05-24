<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Récupérer l'utilisateur à modifier
$userId = $_GET['id'] ?? $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé";
    header("Location: manage_users.php");
    exit();
}

// Vérifier les permissions
$isAdmin = ($_SESSION['role'] === 'admin');
$isOwner = ($_SESSION['user_id'] == $userId);

if (!$isAdmin && !$isOwner) {
    $_SESSION['error'] = "Vous n'avez pas la permission de modifier cet utilisateur";
    header("Location: manage_users.php");
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    try {
        // Mise à jour de base
        $updateData = [$username, $email, $userId];
        $sql = "UPDATE users SET username = ?, email = ?";
        
        // Si admin, peut changer le rôle
        if ($isAdmin && isset($_POST['role'])) {
            $sql .= ", role = ?";
            $updateData[] = $_POST['role'];
            array_push($updateData, $userId);
        }
        
        // Si changement de mot de passe
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $updateData[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            array_push($updateData, $userId);
        }
        
        $sql .= " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateData);
        
        $_SESSION['success'] = "Profil mis à jour avec succès!";
        header("Location: " . ($isAdmin ? "manage_users.php" : "profile.php"));
        exit();
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = "Modifier Utilisateur";
require_once 'header.php';
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 100px auto 80px;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    }
    
    .form-title {
        color: var(--primary);
        margin-top: 0;
        margin-bottom: 1.5rem;
        font-size: 1.8rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }
    
    .btn-submit {
        background: var(--primary);
        color: white;
        padding: 0.8rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .btn-submit:hover {
        background: var(--primary-dark);
    }
</style>

<div class="form-container">
    <h1 class="form-title"><i class="fas fa-user-edit"></i> Modifier Utilisateur</h1>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Nom complet</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        
        <?php if ($isAdmin): ?>
        <div class="form-group">
            <label>Rôle</label>
            <select name="role" class="form-control" required>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                <option value="ecole" <?= $user['role'] === 'ecole' ? 'selected' : '' ?>>École</option>
                <option value="bibliotheque" <?= $user['role'] === 'bibliotheque' ? 'selected' : '' ?>>Bibliothèque</option>
                <option value="individu" <?= $user['role'] === 'individu' ? 'selected' : '' ?>>Individu</option>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>Nouveau mot de passe (laisser vide pour ne pas changer)</label>
            <input type="password" name="password" class="form-control" minlength="8">
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="fas fa-save"></i> Enregistrer
        </button>
        
        <?php if (!$isAdmin): ?>
        <a href="profile.php" class="btn btn-back" style="margin-left: 1rem;">
            <i class="fas fa-times"></i> Annuler
        </a>
        <?php else: ?>
        <a href="manage_users.php" class="btn btn-back" style="margin-left: 1rem;">
            <i class="fas fa-times"></i> Annuler
        </a>
        <?php endif; ?>
    </form>
</div>

<?php require_once 'footer.php'; ?>
