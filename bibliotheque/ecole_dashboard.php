<?php
session_start();
require_once 'db_connect.php';

// Vérification du rôle école
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Récupérer les infos de l'école
    $stmt = $pdo->prepare("SELECT e.*, u.email FROM ecoles e JOIN users u ON e.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ecole = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ecole) {
        throw new Exception("Profil école non trouvé");
    }

    // 2. Récupérer les statistiques
    $stats = [
        'enseignants' => 0,
        'eleves' => 0,
        'emprunts_actifs' => 0,
        'retards' => 0
    ];

    // Compter les enseignants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enseignants WHERE ecole_id = ?");
    $stmt->execute([$ecole['id']]);
    $stats['enseignants'] = $stmt->fetchColumn();

    // Compter les élèves
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE ecole_id = ?");
    $stmt->execute([$ecole['id']]);
    $stats['eleves'] = $stmt->fetchColumn();

    // Emprunts en cours
    $stmt = $pdo->prepare("
    SELECT COUNT(*) FROM borrows 
    WHERE (user_id = ? 
        OR user_id IN (SELECT user_id FROM enseignants WHERE ecole_id = ?)
        OR user_id IN (SELECT user_id FROM eleves WHERE ecole_id = ?))
        AND return_date IS NULL
    ");
    $stmt->execute([$_SESSION['user_id'], $ecole['id'], $ecole['id']]);
    $stats['emprunts_actifs'] = $stmt->fetchColumn();

    // Emprunts en retard
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows 
                          WHERE (user_id IN (SELECT id FROM users WHERE entity_id = ? AND role = 'ecole')
                          OR user_id IN (SELECT user_id FROM enseignants WHERE ecole_id = ?)
                          OR user_id IN (SELECT user_id FROM eleves WHERE ecole_id = ?))
                          AND return_date IS NULL 
                          AND borrow_date < DATE_SUB(NOW(), INTERVAL 14 DAY)");
    $stmt->execute([$ecole['id'], $ecole['id'], $ecole['id']]);
    $stats['retards'] = $stmt->fetchColumn();

    // Derniers emprunts
    $stmt = $pdo->prepare("SELECT b.*, bk.title, bk.author, 
                          CASE 
                            WHEN u.role = 'ecole' THEN 'École'
                            WHEN EXISTS (SELECT 1 FROM enseignants e WHERE e.user_id = b.user_id) THEN 'Enseignant'
                            ELSE 'Élève'
                          END AS emprunteur_type
                          FROM borrows b
                          JOIN books bk ON b.book_id = bk.id
                          JOIN users u ON b.user_id = u.id
                          WHERE (u.entity_id = ? AND u.role = 'ecole')
                          OR b.user_id IN (SELECT user_id FROM enseignants WHERE ecole_id = ?)
                          OR b.user_id IN (SELECT user_id FROM eleves WHERE ecole_id = ?)
                          ORDER BY b.borrow_date DESC
                          LIMIT 5");
    $stmt->execute([$ecole['id'], $ecole['id'], $ecole['id']]);
    $derniers_emprunts = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$pageTitle = "Tableau de bord - " . htmlspecialchars($ecole['nom_ecole']);
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
        /* Masquer la barre de défilement */
        -ms-overflow-style: none; /* IE/Edge */
        scrollbar-width: none; /* Firefox */
    }

    .main-container::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }

    .dashboard-content {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 20px;
    }

    .welcome-header {
        position: fixed;
        top: 70px; /* Juste sous le header */
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
        margin-top: 110px; /* Hauteur du .welcome-header (70px header + 40px padding) */
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

    .borrow-item .borrow-info strong {
        color: var(--text-color);
    }

    .borrow-meta {
        font-size: 0.9rem;
        color: var(--meta-text);
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        background-color: var(--badge-school-bg);
        color: var(--badge-school-text);
    }

    .badge-school {
        background-color: var(--badge-school-bg);
        color: var(--badge-school-text);
    }

    .badge-teacher {
        background-color: var(--badge-teacher-bg);
        color: var(--badge-teacher-text);
    }

    .badge-student {
        background-color: var(--badge-student-bg);
        color: var(--badge-student-text);
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
            margin-top: 100px; /* Ajusté pour écrans plus petits */
        }
    }
</style>

<div class="main-container">
    <div class="welcome-header">
        <h1><?= htmlspecialchars($ecole['nom_ecole']) ?></h1>
        <p class="lead">Gestion de votre établissement scolaire</p>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['enseignants'] ?></div>
                    <div>Enseignants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['eleves'] ?></div>
                    <div>Élèves</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['emprunts_actifs'] ?></div>
                    <div>Emprunts actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['retards'] ?></div>
                    <div>Retours en retard</div>
                </div>
            </div>

            <div class="action-grid">
                <a href="borrow_book.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <h3>Emprunter un livre</h3>
                    <p>Emprunter des livres pour votre école</p>
                </a>

                <a href="retourner_livre.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Retourner un livre</h3>
                    <p>Gérer les retours de livres</p>
                </a>

                <a href="gerer_enseignants.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Gérer les enseignants</h3>
                    <p>Ajouter/modifier vos enseignants</p>
                </a>

                <a href="gerer_eleves.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>Gérer les élèves</h3>
                    <p>Inscrire vos élèves par classe</p>
                </a>

                <a href="borrow_history.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>Historique des emprunts</h3>
                    <p>Consulter tous les emprunts</p>
                </a>
                
                <a href="gestion_livres.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Gérer les livres</h3>
                    <p>Répartir les livres aux élèves et enseignants</p>
                </a>
                
                <a href="statistiques.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Statistiques</h3>
                    <p>Analyser l'activité de l'école</p>
                </a>

                <a href="ecole_settings.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>Paramètres</h3>
                    <p>Modifier les paramètres de l'école</p>
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
                                    <?= htmlspecialchars($emprunt['author']) ?> • 
                                    <span class="badge <?= 
                                        $emprunt['emprunteur_type'] === 'École' ? 'badge-school' : 
                                        ($emprunt['emprunteur_type'] === 'Enseignant' ? 'badge-teacher' : 'badge-student') 
                                    ?>">
                                        <?= $emprunt['emprunteur_type'] ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <?= date('d/m/Y', strtotime($emprunt['borrow_date'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: right; margin-top: 15px;">
                        <a href="historique_emprunts.php">Voir tout l'historique →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>