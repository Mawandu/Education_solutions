<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT username, role FROM users WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Erreur : Utilisateur non trouvé.");
}

$pageTitle = "Tableau de bord Administrateur";
require_once 'header.php';
?>

<style>
    /* Styles globaux */
    .admin-dashboard {
        padding: 2rem 3rem;
        max-width: 1400px;
        margin: 100px auto 80px;
        width: 90%;
        color: #333;
    }
    
    /* En-tête */
    .dashboard-header {
        margin-bottom: 2.5rem;
        text-align: center;
    }
    
    .dashboard-header h1 {
        color: var(--primary);
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .dashboard-header p {
        color: var(--text-secondary);
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }
    
    /* Message de bienvenue */
    .welcome-message {
        background: rgba(76, 175, 80, 0.1);
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 3rem;
        border-left: 4px solid var(--primary);
    }
    
    .welcome-message h2 {
        color: var(--primary-dark);
        margin-top: 0;
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
    }
    
    .welcome-message p {
        color: var(--text);
        margin: 0;
        font-size: 1rem;
    }
    
    /* Grille de cartes */
    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-top: 1rem;
    }
    
    /* Cartes */
    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border-top: 4px solid;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    }
    
    .card h3 {
        font-size: 1.4rem;
        margin-top: 0;
        margin-bottom: 1.2rem;
        color: var(--primary-dark);
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .card h3 i {
        font-size: 1.6rem;
        color: inherit;
    }
    
    .card p {
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 2rem;
        flex-grow: 1;
    }
    
    .card .btn {
        align-self: flex-start;
        background: var(--primary);
        color: white;
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .card .btn:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    /* Couleurs des cartes */
    .card:nth-child(1) { border-color: var(--primary); }
    .card:nth-child(2) { border-color: var(--secondary); }
    .card:nth-child(3) { border-color: var(--accent); }
    
    .card:nth-child(1) h3 { color: var(--primary); }
    .card:nth-child(2) h3 { color: var(--secondary); }
    .card:nth-child(3) h3 { color: var(--accent); }
    
    /* Responsive */
    @media (max-width: 768px) {
        .admin-dashboard {
            padding: 1.5rem;
            margin: 80px auto 60px;
        }
        
        .card-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="admin-dashboard">
    <div class="dashboard-header">
        <h1>Tableau de bord Administrateur</h1>
        <p>Gérez l'ensemble de la plateforme Bibliothèque Virtuelle</p>
    </div>

    <div class="welcome-message">
        <h2>Bienvenue, <?= htmlspecialchars($user['username']) ?>!</h2>
        <p>Vous avez accès à tous les outils d'administration du système.</p>
    </div>

    <div class="card-grid">
        <!-- Carte 1 -->
        <div class="card">
            <h3><i class="fas fa-users-cog"></i> Gestion des utilisateurs</h3>
            <p>Créez, modifiez ou supprimez des comptes utilisateurs et gérez leurs permissions d'accès à la plateforme.</p>
            <a href="manage_users.php" class="btn">Accéder <i class="fas fa-arrow-right"></i></a>
        </div>

        <!-- Carte 2 -->
        <div class="card">
            <h3><i class="fas fa-check-circle"></i> Validation des entités</h3>
            <p>Validez les nouvelles écoles et bibliothèques souhaitant rejoindre la plateforme et accéder aux ressources.</p>
            <a href="validate_entities.php" class="btn">Accéder <i class="fas fa-arrow-right"></i></a>
        </div>

        <!-- Carte 3 -->
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Statistiques</h3>
            <p>Consultez les données d'utilisation, les indicateurs clés de performance et les tendances d'activité.</p>
            <a href="stats.php" class="btn">Accéder <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>