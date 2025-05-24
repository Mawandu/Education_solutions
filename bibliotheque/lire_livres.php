<?php
session_start();
require_once 'db_connect.php';

// Vérification des rôles autorisés
$roles_autorises = ['individu', 'enseignant', 'eleve'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $roles_autorises)) {
    header("Location: login.php");
    exit();
}

// Récupérer les livres actuellement empruntés par l'utilisateur connecté
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.title, b.author, b.domain, b.description
        FROM books b
        JOIN borrows br ON b.id = br.book_id
        WHERE br.user_id = ? AND br.return_date IS NULL
        ORDER BY b.title ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $livres_empruntes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

$pageTitle = "Lire les livres";
require_once 'header.php';
?>

<style>
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #dee2e6;
        --meta-text: #666;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --meta-text: #bbb;
    }

    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--background);
        color: var(--text-color);
    }

    header.navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background-color: var(--primary-color) !important;
        color: white !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: flex;
        align-items: center;
        padding: 0 20px;
    }

    footer.footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50px;
        background-color: var(--primary-color) !important;
        color: white !important;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .main-container {
        position: absolute;
        top: 70px;
        bottom: 50px;
        left: 0;
        right: 0;
        overflow-y: auto;
        background-color: var(--background);
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .main-container::-webkit-scrollbar {
        display: none;
    }

    .dashboard-content {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 20px;
    }

    .welcome-header {
        position: fixed;
        top: 70px;
        left: 0;
        right: 0;
        background-color: var(--background);
        z-index: 999;
        text-align: center;
        padding: 20px 0;
        border-bottom: 1px solid var(--border-color);
        margin: 0;
    }

    .welcome-header h1 {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 10px;
    }

    .welcome-header p {
        color: var(--text-color);
    }

    .content-wrapper {
        margin-top: 110px;
    }

    .books-list {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .books-list h3 {
        color: var(--text-color);
        margin-bottom: 20px;
    }

    .book-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.3s ease;
    }

    .book-item:hover {
        background-color: rgba(0, 121, 107, 0.05);
    }

    .book-info strong {
        color: var(--text-color);
    }

    .book-meta {
        font-size: 0.9rem;
        color: var(--meta-text);
    }

    .books-list a {
        color: var(--primary-color);
        text-decoration: none;
    }

    .books-list a:hover {
        text-decoration: underline;
    }

    .back-button {
        display: inline-block;
        margin-bottom: 20px;
        padding: 10px 20px;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }

    .back-button:hover {
        background-color: var(--primary-light);
        color: white;
        text-decoration: none;
    }

    @media (max-width: 768px) {
        header.navbar {
            height: 60px;
        }
        
        .main-container {
            top: 60px;
        }

        .welcome-header {
            top: 60px;
        }

        .content-wrapper {
            margin-top: 100px;
        }
    }
</style>

<div class="main-container">
    <div class="welcome-header">
        <h1>Lire les livres</h1>
        <p class="lead">Vos livres actuellement empruntés</p>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="books-list">
                <a href="user_dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
                <h3>Vos livres à lire</h3>
                <?php if (empty($livres_empruntes)): ?>
                    <p>Vous n'avez aucun livre en cours de lecture</p>
                <?php else: ?>
                    <?php foreach ($livres_empruntes as $livre): ?>
                        <a href="read_book.php?id=<?= $livre['id'] ?>" class="book-item">
                            <div class="book-info">
                                <strong><?= htmlspecialchars($livre['title']) ?></strong>
                                <div class="book-meta">
                                    <?= htmlspecialchars($livre['author']) ?> • 
                                    <?= htmlspecialchars($livre['domain']) ?>
                                </div>
                            </div>
                            <div>
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>