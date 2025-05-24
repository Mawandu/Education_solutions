<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email et mot de passe sont requis.";
    } else {
        $query = "SELECT id, username, password, role, configured, must_change_password FROM users WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Vérifier si l'utilisateur doit changer son mot de passe (uniquement pour enseignant et eleve)
            if (in_array($user['role'], ['enseignant', 'eleve']) && $user['must_change_password'] == 1) {
                header("Location: modifier_mot_de_passe.php");
                exit();
            }

            // Redirection si compte non complètement configuré (sauf pour eleve et enseignant)
            if (!in_array($user['role'], ['enseignant', 'eleve']) && !$user['configured']) {
                header("Location: register_complete.php");
                exit();
            }

            // Redirection selon le rôle
            switch ($user['role']) {
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'bibliotheque':
                    header("Location: bibliotheque_dashboard.php");
                    break;
                case 'ecole':
                    header("Location: ecole_dashboard.php");
                    break;
                case 'individu':
                    header("Location: user_dashboard.php");
                    break;
                case 'enseignant':
                    header("Location: enseignant_dashboard.php");
                    break;
                case 'eleve':
                    header("Location: eleve_dashboard.php");
                    break;
                default:
                    header("Location: index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Email ou mot de passe incorrect.";
        }
    }
    header("Location: login.php");
    exit();
}

$pageTitle = "Connexion";
require_once 'header_minimal.php'; // Utiliser le header minimaliste
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: rgba(255,255,255,0.95);
        --border-color: #ddd;
        --error: #dc3545;
        --secondary: #2196F3;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: rgba(58,58,58,0.95);
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

    .login-container {
        width: 100%;
        max-width: 400px;
    }

    .login-card {
        background-color: var(--card-bg);
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        text-align: center;
        transition: all 0.3s ease;
    }

    .login-card:hover {
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

    .links {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
        font-size: 0.9rem;
    }

    .links a {
        color: var(--secondary);
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .links a:hover {
        color: var(--primary-color);
    }
</style>

<main>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <i class="fas fa-book"></i> Bibliothèque Virtuelle
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">
                    Se connecter
                </button>
            </form>

            <div class="links">
                <a href="mot_de_passe_oublie.php">Mot de passe oublié ?</a>
                <a href="register.php">S'inscrire</a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'footer_minimal.php'; // Utiliser le footer minimaliste ?>