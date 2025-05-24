<?php
require_once 'db_connect.php';
require_once 'mail_functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Traitement de la validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_id'])) {
    $id = (int)$_POST['validate_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && in_array($user['role'], ['ecole', 'bibliotheque'])) {
        // Valider l'utilisateur
        $stmt = $pdo->prepare("UPDATE users SET configured = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Si c'est une bibliothèque, valider aussi dans la table bibliotheques
        if ($user['role'] === 'bibliotheque') {
            $stmt = $pdo->prepare("UPDATE libraries SET valide = 1 WHERE user_id = ?");
            $stmt->execute([$id]);
        }
        
        // Envoi d'email
        $subject = "Votre compte a été validé - Bibliothèque Virtuelle";
        $message = "Bonjour " . $user['username'] . ",\n\n";
        $message .= "Votre compte " . $user['role'] . " sur la Bibliothèque Virtuelle a été validé.\n";
        $message .= "Vous pouvez maintenant accéder à toutes les fonctionnalités.\n\n";
        $message .= "Cordialement,\nL'équipe de la Bibliothèque Virtuelle";
        
        sendEmail($user['email'], $subject, $message);
        
        $_SESSION['success'] = "Entité validée avec succès!";
        header("Location: validate_entities.php");
        exit;
    }
}

// Récupération des entités
$schools = $pdo->query("SELECT * FROM users WHERE role = 'ecole' AND configured = 0")->fetchAll();
$libraries = $pdo->query("SELECT * FROM users WHERE role = 'bibliotheque' AND configured = 0")->fetchAll();
$schools_validated = $pdo->query("SELECT * FROM users WHERE role = 'ecole' AND configured = 1")->fetchAll();
$libraries_validated = $pdo->query("SELECT * FROM users WHERE role = 'bibliotheque' AND configured = 1")->fetchAll();

$pageTitle = "Validation des Entités";
require_once 'header.php';
?>

<style>
    /* Forcer le header à rester fixe */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px; /* Ajustez cette hauteur selon votre header réel */
        z-index: 1000; /* Assure que le header reste au-dessus du contenu */
        background: #fff; /* Ajoutez une couleur de fond si nécessaire */
    }

    /* Forcer le footer à rester fixe */
    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px; /* Ajustez cette hauteur selon votre footer réel */
        z-index: 1000; /* Assure que le footer reste au-dessus du contenu */
        background: #fff; /* Ajoutez une couleur de fond si nécessaire */
    }

    /* Ajuster le body pour éviter le chevauchement */
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; /* Empêche le défilement global */
    }

    /* Conteneur principal avec défilement */
    .validation-container {
        max-width: 1200px;
        margin: 80px auto 60px; /* Marges égales à la hauteur du header et footer */
        padding: 2rem;
        height: calc(100vh - 140px); /* Hauteur totale moins header et footer */
        overflow-y: auto; /* Défilement vertical uniquement pour ce conteneur */
        box-sizing: border-box;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    .page-title {
        color: var(--primary);
        margin: 0;
        font-size: 1.8rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.2rem;
        background: var(--secondary);
        color: white;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
    }
    
    .section-title {
        color: var(--primary-dark);
        margin: 2.5rem 0 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .entity-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .entity-table th {
        background: var(--primary-light);
        color: var(--primary-dark);
        padding: 1rem;
        text-align: left;
    }
    
    .entity-table td {
        padding: 1rem;
        border-bottom: 1px solid #eee;
    }
    
    .entity-table tr:hover {
        background: rgba(76, 175, 80, 0.05);
    }
    
    .btn-validate {
        background: var(--success);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .empty-message {
        padding: 1.5rem;
        text-align: center;
        color: var(--text-secondary);
        font-style: italic;
    }
    
    .alert-success {
        background: rgba(76, 175, 80, 0.1);
        color: var(--success);
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--success);
    }
</style>

<main>
    <div class="validation-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i> Validation des Entités
            </h1>
            <a href="admin_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <h2 class="section-title">
            <i class="fas fa-school"></i> Écoles en attente de validation
        </h2>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schools)): ?>
                    <tr>
                        <td colspan="4" class="empty-message">Aucune école en attente de validation</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schools as $school): ?>
                    <tr>
                        <td><?= htmlspecialchars($school['id']) ?></td>
                        <td><?= htmlspecialchars($school['username']) ?></td>
                        <td><?= htmlspecialchars($school['email']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="validate_id" value="<?= $school['id'] ?>">
                                <button type="submit" class="btn-validate">
                                    <i class="fas fa-check"></i> Valider
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2 class="section-title">
            <i class="fas fa-book"></i> Bibliothèques en attente de validation
        </h2>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($libraries)): ?>
                    <tr>
                        <td colspan="4" class="empty-message">Aucune bibliothèque en attente de validation</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($libraries as $library): ?>
                    <tr>
                        <td><?= htmlspecialchars($library['id']) ?></td>
                        <td><?= htmlspecialchars($library['username']) ?></td>
                        <td><?= htmlspecialchars($library['email']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="validate_id" value="<?= $library['id'] ?>">
                                <button type="submit" class="btn-validate">
                                    <i class="fas fa-check"></i> Valider
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2 class="section-title">
            <i class="fas fa-school"></i> Écoles validées
        </h2>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schools_validated)): ?>
                    <tr>
                        <td colspan="3" class="empty-message">Aucune école validée</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schools_validated as $school): ?>
                    <tr>
                        <td><?= htmlspecialchars($school['id']) ?></td>
                        <td><?= htmlspecialchars($school['username']) ?></td>
                        <td><?= htmlspecialchars($school['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2 class="section-title">
            <i class="fas fa-book"></i> Bibliothèques validées
        </h2>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($libraries_validated)): ?>
                    <tr>
                        <td colspan="3" class="empty-message">Aucune bibliothèque validée</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($libraries_validated as $library): ?>
                    <tr>
                        <td><?= htmlspecialchars($library['id']) ?></td>
                        <td><?= htmlspecialchars($library['username']) ?></td>
                        <td><?= htmlspecialchars($library['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once 'footer.php'; ?>