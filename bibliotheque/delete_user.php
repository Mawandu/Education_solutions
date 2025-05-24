<?php
session_start();
require_once 'db_connect.php';

// Vérification de l'authentification et des droits admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Vérification de l'ID et protection contre la suppression de soi-même
if (!isset($_GET['id'])) {
    header("Location: manage_users.php?error=missing_id");
    exit();
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
    header("Location: manage_users.php?error=invalid_id");
    exit();
}

// Empêcher un admin de se supprimer lui-même
if ($_SESSION['user_id'] == $id) {
    header("Location: manage_users.php?error=self_delete");
    exit();
}

try {
    // Vérifier d'abord si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: manage_users.php?error=user_not_found");
        exit();
    }

    // Suppression de l'utilisateur
    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$id]);

    // Journalisation de l'action
    $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id) VALUES (?, ?, ?)");
    $logStmt->execute([$_SESSION['user_id'], 'delete_user', $id]);

    header("Location: manage_users.php?success=user_deleted&role=" . urlencode($user['role']));
    exit();

} catch (PDOException $e) {
    // Journalisation de l'erreur
    error_log("Erreur suppression utilisateur : " . $e->getMessage());
    header("Location: manage_users.php?error=db_error");
    exit();
}

$pageTitle = "Suppression d'utilisateur";
require_once 'header.php';
?>

<style>
    .confirmation-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .confirmation-icon {
        font-size: 4rem;
        color: #c62828;
        margin-bottom: 20px;
    }
    
    .confirmation-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 30px;
    }
    
    .btn-confirm {
        background: #c62828;
        color: white;
        padding: 10px 25px;
    }
    
    .btn-cancel {
        background: var(--secondary);
        color: white;
        padding: 10px 25px;
    }
</style>

<div class="management-container">
    <div class="confirmation-container">
        <div class="confirmation-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>Confirmer la suppression</h2>
        <p>Êtes-vous certain de vouloir supprimer définitivement cet utilisateur ? Cette action est irréversible.</p>
        
        <div class="confirmation-buttons">
            <a href="manage_users.php" class="btn btn-cancel">
                <i class="fas fa-times"></i> Annuler
            </a>
            <a href="delete_user.php?id=<?= $id ?>&confirm=true" class="btn btn-confirm">
                <i class="fas fa-check"></i> Confirmer
            </a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>