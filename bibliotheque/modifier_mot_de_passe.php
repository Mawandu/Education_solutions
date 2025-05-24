<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et a le rôle "enseignant" ou "eleve"
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['eleve', 'enseignant'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur non trouvé");
}

// Gestion de la modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier que les mots de passe correspondent
    if ($new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        // Vérifier le mot de passe actuel
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $stored_password = $stmt->fetchColumn();

        if (password_verify($current_password, $stored_password)) {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $success = true; // Indiquer que la modification a réussi
        } else {
            $error = "Le mot de passe actuel est incorrect.";
        }
    }
}

$pageTitle = "Modifier le mot de passe";
require_once 'header.php';
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #dee2e6;
        --danger: #dc3545;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --danger: #dc3545;
    }

    /* Styles généraux */
    body {
        background-color: var(--background);
        color: var(--text-color);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 70px;
        z-index: 1000;
        background: var(--primary-color) !important;
        color: white !important;
    }

    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 50px;
        z-index: 1000;
        background: var(--primary-color) !important;
        color: white !important;
    }

    .main-container {
        position: absolute;
        top: 70px;
        bottom: 50px;
        left: 0;
        right: 0;
        overflow-y: auto;
        padding: 40px 20px;
        background-color: var(--background);
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .main-container::-webkit-scrollbar {
        display: none;
    }

    /* Conteneur central */
    .password-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 30px;
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .password-container:hover {
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }

    /* En-tête */
    .password-container h2 {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 20px;
        text-align: center;
        font-size: 1.8rem;
    }

    /* Formulaire */
    .form-label {
        font-weight: 500;
        color: var(--text-color);
    }

    .form-control {
        border-radius: 5px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
    }

    /* Boutons */
    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        transition: all 0.3s ease;
        width: 100%;
        padding: 12px;
        font-size: 1.1rem;
    }

    .btn-primary:hover {
        background-color: var(--primary-light);
    }

    /* Alertes */
    .alert {
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
    }
</style>

<div class="main-container">
    <div class="password-container">
        <h2><i class="fas fa-lock me-2"></i>Modifier le mot de passe</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Mot de passe actuel</label>
                <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <input type="password" class="form-control" name="new_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmer le nouveau mot de passe</label>
                <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Enregistrer
            </button>
        </form>

        <!-- Modal de succès -->
        <?php if (isset($success) && $success): ?>
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="successModalLabel">Succès</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                        </div>
                        <div class="modal-body">
                            Mot de passe modifié avec succès.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="successModalOkBtn">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Afficher la modal si la modification a réussi
<?php if (isset($success) && $success): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var successModal = new bootstrap.Modal(document.getElementById('successModal'), {
            backdrop: 'static', // Empêche de fermer la modal en cliquant à l'extérieur
            keyboard: false // Empêche de fermer la modal avec la touche Échap
        });
        successModal.show();

        // Gérer le clic sur le bouton OK
        document.getElementById('successModalOkBtn').addEventListener('click', function() {
            // Rediriger en fonction du rôle
            var role = '<?php echo $user_role; ?>';
            if (role === 'eleve') {
                window.location.href = 'eleve_dashboard.php';
            } else if (role === 'enseignant') {
                window.location.href = 'enseignant_dashboard.php';
            }
        });
    });
<?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>