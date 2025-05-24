<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Récupération des utilisateurs groupés par rôle
$query = "SELECT id, username, email, role, created_at FROM users ORDER BY role, created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par rôle
$usersByRole = [
    'admin' => [],
    'ecole' => [],
    'bibliotheque' => [],
    'individu' => []
];

foreach ($allUsers as $user) {
    $usersByRole[$user['role']][] = $user;
}

$pageTitle = "Gestion des Utilisateurs";
require_once 'header.php';
?>

<style>
    body {
        margin: 0;
        padding: 0;
    }
    
    .management-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        min-height: calc(100vh - 140px);
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .page-title {
        color: var(--primary);
        margin: 0;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-back {
        background: var(--secondary);
        color: white;
    }
    
    .btn-add {
        background: var(--primary);
        color: white;
    }
    
    .role-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }
    
    .role-tab {
        padding: 10px 20px;
        cursor: pointer;
        border-bottom: 3px solid transparent;
    }
    
    .role-tab.active {
        border-bottom-color: var(--primary);
        color: var(--primary);
        font-weight: bold;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .users-table th {
        background: var(--primary-light);
        padding: 12px;
        text-align: left;
    }
    
    .users-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .action-btn {
        padding: 5px 10px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    .btn-edit {
        background: var(--accent-light);
        color: var(--accent-dark);
    }
    
    .btn-delete {
        background: #ffebee;
        color: #c62828;
    }
    
    .admin-contact {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid #eee;
        font-size: 0.9rem;
    }
</style>

<div class="management-container">
    <div class="page-header">
        <h1 class="page-title">Gestion des Utilisateurs</h1>
        <div class="action-buttons">
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <a href="add_user.php" class="btn btn-add">
                <i class="fas fa-plus"></i> Ajouter
            </a>
        </div>
    </div>
    
    <div class="role-tabs">
        <div class="role-tab active" data-tab="admin">Administrateurs (<?= count($usersByRole['admin']) ?>)</div>
        <div class="role-tab" data-tab="ecole">Écoles (<?= count($usersByRole['ecole']) ?>)</div>
        <div class="role-tab" data-tab="bibliotheque">Bibliothèques (<?= count($usersByRole['bibliotheque']) ?>)</div>
        <div class="role-tab" data-tab="individu">Individus (<?= count($usersByRole['individu']) ?>)</div>
    </div>
    
    <?php foreach ($usersByRole as $role => $users): ?>
    <div class="tab-content <?= $role === 'admin' ? 'active' : '' ?>" id="tab-<?= $role ?>">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Inscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="action-btn btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="delete_user.php?id=<?= $user['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Confirmer la suppression ?')">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    
</div>

<script>
    // Gestion des onglets
    document.querySelectorAll('.role-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            document.getElementById(`tab-${tab.dataset.tab}`).classList.add('active');
        });
    });
</script>

<?php require_once 'footer.php'; ?>
