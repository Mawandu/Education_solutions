<?php
session_start();
require_once 'db_connect.php';

// Inclure PHPMailer pour envoyer des emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Assurez-vous que PHPMailer est installé via Composer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $_SESSION['error'] = "Veuillez entrer votre email.";
    } else {
        try {
            // Vérifier si l'email existe dans la base de données
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $_SESSION['error'] = "Aucun utilisateur trouvé avec cet email.";
            } else {
                // Générer un token de réinitialisation
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Le token expire dans 1 heure

                // Enregistrer le token dans la base de données
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires_at]);

                // Préparer l'email de réinitialisation
                $reset_link = "http://votre-domaine.com/reinitialiser_mot_de_passe.php?token=" . urlencode($token);
                $subject = "Réinitialisation de votre mot de passe";
                $message = "Bonjour,\n\n";
                $message .= "Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien suivant pour définir un nouveau mot de passe :\n";
                $message .= $reset_link . "\n\n";
                $message .= "Ce lien est valide pendant 1 heure.\n";
                $message .= "Si vous n'avez pas fait cette demande, ignorez cet email.\n\n";
                $message .= "Cordialement,\nL'équipe Bibliothèque Virtuelle";

                // Configurer PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // Paramètres du serveur SMTP (à configurer selon votre serveur de messagerie)
                    $mail->isSMTP();
                    $mail->Host = 'smtp.example.com'; // Remplacez par votre serveur SMTP
                    $mail->SMTPAuth = true;
                    $mail->Username = 'votre-email@example.com'; // Remplacez par votre email
                    $mail->Password = 'votre-mot-de-passe'; // Remplacez par votre mot de passe SMTP
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Destinataire et expéditeur
                    $mail->setFrom('votre-email@example.com', 'Bibliothèque Virtuelle');
                    $mail->addAddress($email);

                    // Contenu de l'email
                    $mail->isHTML(false);
                    $mail->Subject = $subject;
                    $mail->Body = $message;

                    // Envoyer l'email
                    $mail->send();
                    $_SESSION['success'] = "Un lien de réinitialisation a été envoyé à votre adresse email.";
                    header("Location: login.php");
                    exit();
                } catch (Exception $e) {
                    $_SESSION['error'] = "Erreur lors de l'envoi de l'email : " . $mail->ErrorInfo;
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Une erreur est survenue : " . $e->getMessage();
        }
    }
}

$pageTitle = "Mot de passe oublié";
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

    .links {
        margin-top: 1.5rem;
        font-size: 0.9rem;
        text-align: center;
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
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo">
                <i class="fas fa-lock"></i> Mot de passe oublié
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Entrez votre email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <button type="submit" class="btn">
                    Envoyer le lien de réinitialisation
                </button>
            </form>

            <div class="links">
                <a href="login.php">Retour à la connexion</a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'footer_minimal.php'; ?>
