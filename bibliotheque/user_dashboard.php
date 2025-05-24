<?php
session_start();
require_once 'db_connect.php';

// Vérification du rôle individu
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'individu') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Récupérer les infos de l'utilisateur et de l'individu
    $stmt = $pdo->prepare("SELECT u.*, i.* FROM users u 
                          JOIN individus i ON u.id = i.user_id 
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }

    // 2. Récupérer les statistiques
    $stats = [
        'emprunts_actifs' => 0,
        'retards' => 0,
        'livres_lus' => 0
    ];

    // Compter les emprunts en cours
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id = ? AND return_date IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['emprunts_actifs'] = $stmt->fetchColumn();

    // Compter les retards
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows 
                          WHERE user_id = ? 
                          AND return_date IS NULL 
                          AND borrow_date < DATE_SUB(NOW(), INTERVAL 14 DAY)");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['retards'] = $stmt->fetchColumn();

    // Compter les livres lus (historique)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id = ? AND return_date IS NOT NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['livres_lus'] = $stmt->fetchColumn();

    // Derniers emprunts
    $stmt = $pdo->prepare("SELECT b.*, bk.title, bk.author
                          FROM borrows b
                          JOIN books bk ON b.book_id = bk.id
                          WHERE b.user_id = ?
                          ORDER BY b.borrow_date DESC
                          LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $derniers_emprunts = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$pageTitle = "Tableau de bord - " . htmlspecialchars($user['username'] ?? 'Utilisateur');
require_once 'header.php';
?>

<style>
    /* Variables de thème (identiques à ecole_dashboard.php) */
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #dee2e6;
        --badge-school-bg: #e3f2fd;
        --badge-school-text: #1976d2;
        --badge-teacher-bg: #e8f5e9;
        --badge-teacher-text: #2e7d32;
        --badge-student-bg: #fff3e0;
        --badge-student-text: #e65100;
        --meta-text: #666;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --badge-school-bg: #1976d2;
        --badge-school-text: #e3f2fd;
        --badge-teacher-bg: #2e7d32;
        --badge-teacher-text: #e8f5e9;
        --badge-student-bg: #e65100;
        --badge-student-text: #fff3e0;
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        text-align: center;
        border-top: 4px solid var(--primary-color);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-value {
        font-size: 2.5rem;
        color: var(--primary-color);
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-card div {
        color: var(--text-color);
    }

    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .action-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        text-align: center;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        border-color: var(--primary-light);
    }

    .action-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .recent-borrows {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .recent-borrows h3 {
        color: var(--text-color);
    }

    .borrow-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .borrow-info strong {
        color: var(--text-color);
    }

    .borrow-meta {
        font-size: 0.9rem;
        color: var(--meta-text);
    }

    .recent-borrows a {
        color: var(--primary-color);
        text-decoration: none;
    }

    .recent-borrows a:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
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
        <h1><?= htmlspecialchars($user['username'] ?? 'Utilisateur') ?></h1>
        <p class="lead">Votre espace personnel de lecture</p>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['emprunts_actifs'] ?></div>
                    <div>Emprunts actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['retards'] ?></div>
                    <div>Retours en retard</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['livres_lus'] ?></div>
                    <div>Livres lus</div>
                </div>
            </div>

            <div class="action-grid">
                <a href="borrow_book.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <h3>Emprunter un livre</h3>
                    <p>Emprunter des livres pour vous</p>
                </a>

                <a href="retourner_livre.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Rendre un livre</h3>
                    <p>Gérer vos retours de livres</p>
                </a>

                <a href="lire_livres.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h3>Lire les livres</h3>
                    <p>Consulter vos livres en cours</p>
                </a>

                <a href="borrow_history.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>Historique des emprunts</h3>
                    <p>Consulter vos emprunts passés</p>
                </a>

                <a href="individu_statistiques.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Statistiques</h3>
                    <p>Analyser votre activité</p>
                </a>

                <a href="user_settings.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>Paramètres</h3>
                    <p>Modifier vos paramètres</p>
                </a>
            </div>

            <div class="recent-borrows">
                <h3>Derniers emprunts</h3>
                <?php if (empty($derniers_emprunts)): ?>
                    <p>Aucun emprunt récent</p>
                <?php else: ?>
                    <?php foreach ($derniers_emprunts as $emprunt): ?>
                        <div class="borrow-item">
                            <div class="borrow-info">
                                <strong><?= htmlspecialchars($emprunt['title']) ?></strong>
                                <div class="borrow-meta">
                                    <?= htmlspecialchars($emprunt['author']) ?>
                                    <?php if ($emprunt['return_date'] === null): ?>
                                        <span class="badge badge-student">En cours</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?= date('d/m/Y', strtotime($emprunt['borrow_date'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: right; margin-top: 15px;">
                        <a href="borrow_history.php">Voir tout l'historique →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>