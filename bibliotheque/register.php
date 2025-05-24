<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validation des champs
    if (empty($email) || empty($password) || empty($password_confirm) || empty($role)) {
        $_SESSION['error'] = "Tous les champs sont requis.";
    } elseif ($password !== $password_confirm) {
        $_SESSION['error'] = "Les mots de passe ne correspondent pas.";
    } elseif (!in_array($role, ['individu', 'ecole', 'bibliotheque'])) {
        $_SESSION['error'] = "Type de compte invalide.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet email est déjà utilisé.");
            }

            // Enregistrer l'utilisateur avec must_change_password = 0
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, configured, must_change_password) VALUES (?, ?, ?, 0, 0)");
            $stmt->execute([$email, $hashed_password, $role]);

            // Stocker les informations dans la session pour l'étape 2
            $_SESSION['register_email'] = $email;
            $_SESSION['register_role'] = $role;

            header("Location: register_complete.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
    header("Location: register.php");
    exit();
}

$pageTitle = "Inscription - Étape 1";
require_once 'header_minimal.php'; // Utiliser le header minimaliste
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #e0e0e0;
        --error: #dc3545;
        --secondary: #2196F3;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --error: #dc3545;
        --secondary: #1976D2;
    }

    /* Styles généraux */
    main {
        position: absolute;
        top: 60px; /* Hauteur du header minimal */
        bottom: 40px; /* Hauteur du footer minimal */
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, rgba(0,121,107,0.1) 0%, rgba(76,175,80,0.05) 100%);
        overflow-y: auto;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    main::-webkit-scrollbar {
        display: none;
    }

    .auth-card {
        width: 100%;
        max-width: 500px;
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        margin: 2rem;
        transition: all 0.3s ease;
    }

    .auth-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .auth-header {
        background: var(--primary-color);
        color: white;
        padding: 1.5rem;
        text-align: center;
    }

    .auth-header h1 {
        margin: 0;
        font-size: 1.8rem;
    }

    .progress-steps {
        display: flex;
        justify-content: center;
        margin-top: 1rem;
    }

    .step {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .step.active {
        background: white;
        color: var(--primary-color);
    }

    .step-divider {
        width: 50px;
        height: 2px;
        background: rgba(255,255,255,0.5);
        margin: 0 5px;
        align-self: center;
    }

    .auth-body {
        padding: 2rem;
    }

    .alert-error {
        background: rgba(244, 67, 54, 0.1);
        color: var(--error);
        padding: 0.8rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--error);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-color);
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 1rem;
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
        outline: none;
    }

    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1em;
    }

    button[type="submit"] {
        width: 100%;
        padding: 1rem;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 121, 107, 0.2);
    }

    button[type="submit"]:hover {
        background: var(--primary-light);
        box-shadow: 0 6px 8px rgba(0, 121, 107, 0.3);
    }

    .auth-footer {
        text-align: center;
        margin-top: 2rem;
        color: var(--text-color);
        font-size: 0.9rem;
    }

    .auth-footer a {
        color: var(--secondary);
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .auth-footer a:hover {
        color: var(--primary-color);
    }
</style>

<main>
    <div class="auth-card">
        <div class="auth-header">
            <h1><i class="fas fa-user-plus"></i> Créer un compte</h1>
            <div class="progress-steps">
                <div class="step active">1</div>
                <div class="step-divider"></div>
                <div class="step">2</div>
            </div>
        </div>

        <div class="auth-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="password_confirm" required>
                </div>

                <div class="form-group">
                    <label>Type de compte</label>
                    <select name="role" required>
                        <option value="">-- Sélectionnez --</option>
                        <option value="individu">Particulier</option>
                        <option value="ecole">Établissement scolaire</option>
                        <option value="bibliotheque">Bibliothèque</option>
                    </select>
                </div>

                <button type="submit">
                    Continuer <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer">
                Déjà un compte ? <a href="login.php">Connectez-vous</a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'footer_minimal.php'; // Utiliser le footer minimaliste ?>