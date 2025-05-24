<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = $pageTitle ?? 'Bibliothèque Virtuelle';
$theme = $_COOKIE['theme'] ?? 'light';
?>

<?php
// Mise à jour de l’activité des utilisateurs
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("REPLACE INTO user_activity (user_id, last_activity) VALUES (?, NOW())");
    $stmt->execute([$_SESSION['user_id']]);
}
?>

<?php
// Enregistrement d'une visite
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("INSERT INTO visits (ip) VALUES (?)");
$stmt->execute([$ip]);
?>


<!DOCTYPE html>
<html lang="fr" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' fill='%234CAF50'>📚</text></svg>">
    <style>
        

        :root {
            --primary: #4CAF50;
            --secondary: #00796b;
            --dark: #2E7D32;
            --light: #f5f5f5;
            --text: #333;
        }

        [data-theme="dark"] {
            --primary: #4CAF50;
            --secondary: #005a4c;
            --dark: #1B5E20;
            --light: #2d2d2d;
            --text: #f5f5f5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--light);
            color: var(--text);
            line-height: 1.6;
        }

        .navbar {
            width: 100%;
            background-color: var(--secondary);
            color: white;
            padding: 0.8rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .logo {
            font-size: 1.3rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-links a, .nav-links button {
            color: white;
            text-decoration: none;
            font-weight: 500;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-family: inherit;
            padding: 0.3rem 0.6rem;
        }

        .btn {
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary);
        }

        .btn-logout {
            background-color: #d32f2f;
        }

        .main-content {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <i class="fas fa-book-open"></i> Bibliothèque Virtuelle
        </div>
        <div class="nav-links">
            <?php if(isset($_SESSION['user_id'])): ?>
                <span style="font-size: 0.95rem;">Bienvenue, <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                <form action="logout.php" method="post">
                    <button type="submit" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </button>
                </form>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Connexion</a>
                <a href="register.php">Inscription</a>
            <?php endif; ?>
            <a href="about.php">À propos</a>
            <a href="index.php"><i class="fas fa-home"></i></a>
        </div>
    </nav>

    <main class="main-content">
