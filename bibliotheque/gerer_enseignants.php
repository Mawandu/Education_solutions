<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM ecoles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$ecole = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ecole) {
    die("École non trouvée");
}

$ecole_id = $ecole['id'];

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_enseignant'])) {
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $email = htmlspecialchars($_POST['email']);
        $telephone = htmlspecialchars($_POST['telephone']);
        $sexe = htmlspecialchars($_POST['sexe']);
        $date_naissance = htmlspecialchars($_POST['date_naissance']);
        $matieres = htmlspecialchars($_POST['matieres']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez entrer une adresse e-mail valide.";
        } else {
            try {
                $pdo->beginTransaction();

                // Vérifier si l'e-mail est déjà utilisé
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cet e-mail est déjà utilisé.");
                }

                // Générer un mot de passe temporaire pour l'enseignant
                $password_temp = bin2hex(random_bytes(4));
                $hashed_password = password_hash($password_temp, PASSWORD_DEFAULT);

                // Insérer l'enseignant dans la table users avec role = 'enseignant' et configured = 1
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role, entity_id, must_change_password, configured) VALUES (?, ?, 'enseignant', ?, 1, 1)");
                $stmt->execute([$email, $hashed_password, $ecole_id]);
                $user_id = $pdo->lastInsertId();

                // Ajouter l'enseignant dans la table enseignants
                $stmt = $pdo->prepare("INSERT INTO enseignants (ecole_id, nom, prenom, email, telephone, sexe, date_naissance, matieres, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$ecole_id, $nom, $prenom, $email, $telephone, $sexe, $date_naissance, $matieres, $user_id]);

                $pdo->commit();
                $success = "Enseignant ajouté avec succès. Identifiants : $email / $password_temp";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['modifier_enseignant'])) {
        $enseignant_id = $_POST['enseignant_id'];
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $email = htmlspecialchars($_POST['email']);
        $telephone = htmlspecialchars($_POST['telephone']);
        $sexe = htmlspecialchars($_POST['sexe']);
        $date_naissance = htmlspecialchars($_POST['date_naissance']);
        $matieres = htmlspecialchars($_POST['matieres']);

        try {
            $pdo->beginTransaction();

            // Vérifier si l'e-mail est déjà utilisé par un autre utilisateur
            $stmt = $pdo->prepare("SELECT user_id FROM enseignants WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$enseignant_id, $ecole_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Cet e-mail est déjà utilisé par un autre utilisateur.");
                }

                // Mettre à jour l'e-mail dans la table users
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $user_id]);
            }

            // Mettre à jour les informations de l'enseignant
            $stmt = $pdo->prepare("UPDATE enseignants SET nom = ?, prenom = ?, email = ?, telephone = ?, sexe = ?, date_naissance = ?, matieres = ? WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$nom, $prenom, $email, $telephone, $sexe, $date_naissance, $matieres, $enseignant_id, $ecole_id]);

            $pdo->commit();
            $success = "Enseignant mis à jour avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } elseif (isset($_POST['supprimer'])) {
        $enseignant_id = $_POST['enseignant_id'];

        try {
            $pdo->beginTransaction();

            // Récupérer le user_id de l'enseignant
            $stmt = $pdo->prepare("SELECT user_id FROM enseignants WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$enseignant_id, $ecole_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                // Supprimer l'enseignant
                $stmt = $pdo->prepare("DELETE FROM enseignants WHERE id = ?");
                $stmt->execute([$enseignant_id]);

                // Supprimer l'utilisateur associé
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
            }

            $pdo->commit();
            $success = "Enseignant supprimé avec succès";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupérer les filtres
$filtre_matieres = $_GET['matieres'] ?? '';

// Construire la requête avec filtres
$query = "SELECT * FROM enseignants WHERE ecole_id = ?";
$params = [$ecole_id];

if ($filtre_matieres) {
    $query .= " AND matieres LIKE ?";
    $params[] = "%$filtre_matieres%";
}

$query .= " ORDER BY nom, prenom";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = "Gérer les enseignants";
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
        --badge-bg: #e3f2fd;
        --badge-text: #1976d2;
        --danger: #dc3545;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --badge-bg: #1976d2;
        --badge-text: #e3f2fd;
        --danger: #dc3545;
    }

    body {
        background-color: var(--background);
        color: var(--text-color);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        padding: 20px;
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
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--border-color);
    }

    .page-header h1 {
        color: var(--primary-color);
        font-weight: 600;
        font-size: 2rem;
        margin: 0;
    }

    .filters-card {
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .filters-card .card-header {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .filters-card .card-body {
        padding: 20px;
    }

    .teacher-card {
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        border-left: 5px solid var(--primary-color);
        transition: all 0.3s ease;
    }

    .teacher-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .teacher-card .card-header {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .teacher-card .card-body {
        padding: 25px;
    }

    .form-label {
        font-weight: 500;
        color: var(--text-color);
    }

    .form-control, .form-select {
        border-radius: 5px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
    }

    .table {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 5px;
    }

    .table th {
        background: #f8f9fa;
        color: var(--text-color);
        font-weight: 600;
    }

    [data-theme="dark"] .table th {
        background: #444;
    }

    .table td {
        vertical-align: middle;
    }

    .table-hover tbody tr:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    [data-theme="dark"] .table-hover tbody tr:hover {
        background: #444;
    }

    .matieres-badge {
        background-color: var(--badge-bg);
        color: var(--badge-text);
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.9rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background-color: var(--primary-light);
    }

    .btn-outline-primary {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-danger {
        background-color: var(--danger);
        border: none;
        transition: all 0.3s ease;
    }

    .btn-danger:hover {
        background-color: #c82333;
    }

    .alert {
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<div class="main-container">
    <div class="dashboard-content">
        <div class="page-header">
            <h1><i class="fas fa-chalkboard-teacher me-2"></i>Gestion des enseignants</h1>
            <a href="ecole_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Retour au tableau de bord
            </a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4 filters-card">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="fas fa-filter me-1"></i>Filtrer les enseignants</h2>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Matières</label>
                        <input type="text" class="form-control" name="matieres" value="<?= htmlspecialchars($filtre_matieres) ?>" placeholder="Ex: Mathématiques">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Appliquer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4 teacher-card">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="fas fa-user-plus me-1"></i>Ajouter un enseignant</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input type="text" class="form-control" name="nom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="prenom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="text" class="form-control" name="telephone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sexe</label>
                                <select class="form-select" name="sexe" required>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" name="date_naissance" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Matières enseignées</label>
                                <input type="text" class="form-control" name="matieres" required placeholder="Ex: Mathématiques, Physique">
                            </div>
                            <button type="submit" name="ajouter_enseignant" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0"><i class="fas fa-list me-1"></i>Liste des enseignants</h2>
                        <span class="badge bg-light text-dark"><?= count($enseignants) ?> enseignant(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($enseignants) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nom & Prénom</th>
                                            <th>Informations</th>
                                            <th>Matières</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enseignants as $enseignant): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($enseignant['prenom']) ?> <?= htmlspecialchars($enseignant['nom']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($enseignant['email']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?= $enseignant['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?><br>
                                                    Né(e) le <?= date('d/m/Y', strtotime($enseignant['date_naissance'])) ?><br>
                                                    Tél: <?= htmlspecialchars($enseignant['telephone']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge matieres-badge">
                                                        <?= htmlspecialchars($enseignant['matieres']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="dropdown d-inline">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-cog me-1"></i> Actions
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editEnseignantModal<?= $enseignant['id'] ?>">
                                                                    <i class="fas fa-edit me-1"></i> Modifier
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="enseignant_id" value="<?= $enseignant['id'] ?>">
                                                                    <button type="submit" name="supprimer" class="dropdown-item text-danger" 
                                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet enseignant ?')">
                                                                        <i class="fas fa-trash-alt me-1"></i> Supprimer
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <!-- Modal pour modifier l'enseignant -->
                                                    <div class="modal fade" id="editEnseignantModal<?= $enseignant['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Modifier l'enseignant</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Nom</label>
                                                                            <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($enseignant['nom']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Prénom</label>
                                                                            <input type="text" class="form-control" name="prenom" value="<?= htmlspecialchars($enseignant['prenom']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">E-mail</label>
                                                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($enseignant['email']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Téléphone</label>
                                                                            <input type="text" class="form-control" name="telephone" value="<?= htmlspecialchars($enseignant['telephone']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Sexe</label>
                                                                            <select class="form-select" name="sexe" required>
                                                                                <option value="M" <?= $enseignant['sexe'] === 'M' ? 'selected' : '' ?>>Masculin</option>
                                                                                <option value="F" <?= $enseignant['sexe'] === 'F' ? 'selected' : '' ?>>Féminin</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Date de naissance</label>
                                                                            <input type="date" class="form-control" name="date_naissance" value="<?= htmlspecialchars($enseignant['date_naissance']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Matières enseignées</label>
                                                                            <input type="text" class="form-control" name="matieres" value="<?= htmlspecialchars($enseignant['matieres']) ?>" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <input type="hidden" name="enseignant_id" value="<?= $enseignant['id'] ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" name="modifier_enseignant" class="btn btn-primary">Enregistrer</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun enseignant trouvé</h5>
                                <p class="text-muted"><?= $filtre_matieres ? 'Modifiez vos critères de recherche' : 'Commencez par ajouter un enseignant' ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>