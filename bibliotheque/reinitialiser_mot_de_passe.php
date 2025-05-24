<?php
session_start();
require_once 'db_connect.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Vérifier si le token est valide et non expiré
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ?");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data) {
        $_SESSION['error'] = "Lien de réinitialisation invalide.";
        header("Location: login.php");
        exit();
    }

    if (strtotime($token_data['expires_at']) < time()) {
        $_SESSION['error'] = "Ce lien de réinitialisation a expiré.";
        header("Location: login.php");
        exit();
    }

    $user_id = $token_data['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password) || empty($password_confirm)) {
            $_SESSION['error'] = "Tous les champs sont requis.";
        } elseif ($password !== $password_confirm) {
            $_SESSION['error'] = "Les mots de passe ne correspondent pas.";
        } else {
            try {
                // Mettre à jour le mot de passe de l'utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);

                // Supprimer le token utilisé
                $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $stmt->execute([$token]);

                $_SESSION['success'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                $_SESSION['error'] = "Une erreur est survenue : " . $e->getMessage();
            }
        }
    }
} else {
    $_SESSION['error'] = "Aucun token de réinitialisation fourni.";
    header("Location: login.php");
    exit();
}

$pageTitle = "Réinitialiser le mot de passe";
require_once 'header_minimal.php';
?>

<style>
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: rgba(255,255,255,0.95);
        --border-color: #ddd;
        --error: #dc3545;
        --success: #28a745;
        --secondary: #2196F3;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: rgba(58,58,58,0.95);
        --border-color: #555;
        --error: #dc3545;
        --success: #28a745;
        --secondary: #1976D2;
    }

    main {
        position: absolute;
        top: 60px;
        bottom: 40px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1rem;
        background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('/bibliotheque/uploads/biblio.jpg');
        background-size: cover;
        background-position: center;
        overflow-y: auto;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    main::-webkit-scrollbar {
        display: none;
    }

    .reset-container {
        width: 100%;
        max-width: 400px;
    }

    .reset-card {
        background-color: var(--card-bg);
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        text-align: center;
        transition: all 0.3s ease;
    }

    .reset-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }

    .logo {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
    }

    .error-message {
        color: var(--error);
        margin-bottom: 1rem;
        padding: 0.8rem;
        background-color: rgba(244, 67, 54, 0.1);
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .success-message {
        color: var(--success);
        margin-bottom: 1rem;
        padding: 0.8rem;
        background-color: rgba(40, 167, 69, 0.1);
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
        text-align: left;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-color);
    }

    .form-group input {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 1rem;
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
        outline: none;
    }

    .btn {
        width: 100%;
        padding: 0.8rem;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn:hover {
        background-color: var(--primary-light);
    }
</style>

<main>
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo">
                <i class="fas fa-key"></i> Réinitialiser le mot de passe
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                
                <button type="submit" class="btn">
                    Réinitialiser
                </button>
            </form>
        </div>
    </div>
</main>

<?php require_once 'footer_minimal.php'; ?>
