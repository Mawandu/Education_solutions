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
    if (isset($_POST['ajouter_eleve'])) {
        $nom = htmlspecialchars($_POST['nom']);
        $prenom = htmlspecialchars($_POST['prenom']);
        $sexe = htmlspecialchars($_POST['sexe']);
        $date_naissance = htmlspecialchars($_POST['date_naissance']);
        $lieu_naissance = htmlspecialchars($_POST['lieu_naissance']);
        $niveau = htmlspecialchars($_POST['niveau']);
        $annee = htmlspecialchars($_POST['annee']);
        $classe = htmlspecialchars($_POST['classe']);
        $email = htmlspecialchars($_POST['email']);
    
        // Pour le niveau secondaire, l'option est obligatoire, pour le primaire, on stocke "Aucune"
        if ($niveau === 'secondaire') {
            if (empty($_POST['option'])) {
                $error = "L'option est obligatoire pour le niveau secondaire.";
            } else {
                $option = htmlspecialchars($_POST['option']);
            }
        } else {
            $option = "Aucune";
        }
    
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
    
                // Vérifier si la combinaison niveau/annee/classe/option existe dans niveaux_options
                if ($niveau === 'secondaire') {
                    $stmt = $pdo->prepare("SELECT id FROM niveaux_options WHERE niveau = ? AND annee = ? AND classe = ? AND option = ?");
                    $stmt->execute([$niveau, $annee, $classe, $option]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM niveaux_options WHERE niveau = ? AND annee = ? AND classe = ? AND option = ?");
                    // Pour le primaire, nous enregistrons "Aucune"
                    $stmt->execute([$niveau, $annee, $classe, $option]);
                }
                $niveau_option_id = $stmt->fetchColumn();
    
                if (!$niveau_option_id) {
                    // Créer une nouvelle entrée dans niveaux_options
                    if ($niveau === 'secondaire') {
                        $stmt = $pdo->prepare("INSERT INTO niveaux_options (niveau, annee, classe, option) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$niveau, $annee, $classe, $option]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO niveaux_options (niveau, annee, classe, option) VALUES (?, ?, ?, ?)");
                        // Pour le primaire, on insère explicitement "Aucune"
                        $stmt->execute([$niveau, $annee, $classe, $option]);
                    }
                    $niveau_option_id = $pdo->lastInsertId();
                }
    
                // Générer un mot de passe temporaire pour l'élève
                $password_temp = bin2hex(random_bytes(4));
                $hashed_password = password_hash($password_temp, PASSWORD_DEFAULT);
    
                // Insérer l'élève dans la table users avec role = 'eleve' et configured = 1
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role, entity_id, must_change_password, configured) VALUES (?, ?, 'eleve', ?, 1, 1)");
                $stmt->execute([$email, $hashed_password, $ecole_id]);
                $user_id = $pdo->lastInsertId();
    
                // Ajouter l'élève dans la table eleves
                $stmt = $pdo->prepare("INSERT INTO eleves (ecole_id, nom, prenom, sexe, date_naissance, lieu_naissance, classe, niveau, niveau_option_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$ecole_id, $nom, $prenom, $sexe, $date_naissance, $lieu_naissance, $classe, $niveau, $niveau_option_id, $user_id]);
    
                $pdo->commit();
                $success = "Élève ajouté avec succès. Identifiants : $email / $password_temp";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['changer_niveau'])) {
        $eleve_id = $_POST['eleve_id'];
        $niveau = htmlspecialchars($_POST['niveau']);
        $annee = htmlspecialchars($_POST['annee']);
        $classe = htmlspecialchars($_POST['classe']);
        if ($niveau === 'secondaire') {
            if (empty($_POST['option'])) {
                $error = "L'option est obligatoire pour le niveau secondaire.";
            } else {
                $option = htmlspecialchars($_POST['option']);
            }
        } else {
            $option = "Aucune";
        }
    
        try {
            $pdo->beginTransaction();
    
            // Vérifier si la combinaison niveau/annee/classe/option existe
            if ($niveau === 'secondaire') {
                $stmt = $pdo->prepare("SELECT id FROM niveaux_options WHERE niveau = ? AND annee = ? AND classe = ? AND option = ?");
                $stmt->execute([$niveau, $annee, $classe, $option]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM niveaux_options WHERE niveau = ? AND annee = ? AND classe = ? AND option = ?");
                $stmt->execute([$niveau, $annee, $classe, $option]);
            }
            $niveau_option_id = $stmt->fetchColumn();
    
            if (!$niveau_option_id) {
                if ($niveau === 'secondaire') {
                    $stmt = $pdo->prepare("INSERT INTO niveaux_options (niveau, annee, classe, option) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$niveau, $annee, $classe, $option]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO niveaux_options (niveau, annee, classe, option) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$niveau, $annee, $classe, $option]);
                }
                $niveau_option_id = $pdo->lastInsertId();
            }
    
            $stmt = $pdo->prepare("UPDATE eleves SET niveau = ?, classe = ?, niveau_option_id = ? WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$niveau, $classe, $niveau_option_id, $eleve_id, $ecole_id]);
    
            $pdo->commit();
            $success = "Niveau de l'élève mis à jour avec succès";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors du changement de niveau : " . $e->getMessage();
        }
    } elseif (isset($_POST['supprimer'])) {
        $eleve_id = $_POST['eleve_id'];
    
        try {
            $pdo->beginTransaction();
    
            // Récupérer le user_id de l'élève
            $stmt = $pdo->prepare("SELECT user_id FROM eleves WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$eleve_id, $ecole_id]);
            $user_id = $stmt->fetchColumn();
    
            if ($user_id) {
                // Supprimer l'élève
                $stmt = $pdo->prepare("DELETE FROM eleves WHERE id = ?");
                $stmt->execute([$eleve_id]);
    
                // Supprimer l'utilisateur associé
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
            }
    
            $pdo->commit();
            $success = "Élève supprimé avec succès";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupérer les filtres
$filtre_niveau = $_GET['niveau'] ?? '';
$filtre_annee = $_GET['annee'] ?? '';
$filtre_option = $_GET['option'] ?? '';

// Construire la requête avec filtres
$query = "SELECT e.*, no.niveau, no.annee, no.classe, no.option, u.email
          FROM eleves e
          JOIN niveaux_options no ON e.niveau_option_id = no.id
          JOIN users u ON e.user_id = u.id
          WHERE e.ecole_id = ?";
$params = [$ecole_id];

if ($filtre_niveau) {
    $query .= " AND no.niveau = ?";
    $params[] = $filtre_niveau;
}

if ($filtre_annee) {
    $query .= " AND no.annee = ?";
    $params[] = $filtre_annee;
}

if ($filtre_option && $filtre_niveau === 'secondaire') {
    $query .= " AND no.option = ?";
    $params[] = $filtre_option;
}

$query .= " ORDER BY no.niveau, no.annee, no.classe, e.nom, e.prenom";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = "Gérer les élèves";
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

    .student-card {
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        border-left: 5px solid var(--primary-color);
        transition: all 0.3s ease;
    }

    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .student-card .card-header {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .student-card .card-body {
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

    .niveau-badge {
        background-color: var(--badge-bg);
        color: var(--badge-text);
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.9rem;
    }

    .secondary-option {
        background-color: #d1e7ff;
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
            <h1><i class="fas fa-user-graduate me-2"></i>Gestion des élèves</h1>
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
                <h2 class="h5 mb-0"><i class="fas fa-filter me-1"></i>Filtrer les élèves</h2>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Niveau</label>
                        <select class="form-select" name="niveau" id="filter-niveau" onchange="updateFilterAnnees(this)">
                            <option value="">Tous les niveaux</option>
                            <option value="primaire" <?= $filtre_niveau === 'primaire' ? 'selected' : '' ?>>Primaire</option>
                            <option value="secondaire" <?= $filtre_niveau === 'secondaire' ? 'selected' : '' ?>>Secondaire</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Année</label>
                        <select class="form-select" name="annee" id="filter-annee">
                            <option value="">Toutes les années</option>
                            <?php
                            $max_annees = $filtre_niveau === 'secondaire' ? 4 : 8;
                            for ($i = 1; $i <= $max_annees; $i++):
                            ?>
                                <option value="<?= $i ?>" <?= $filtre_annee == $i ? 'selected' : '' ?>>
                                    <?= $i ?>ère année
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3" id="filter-option-container" style="<?= $filtre_niveau !== 'secondaire' ? 'display:none;' : '' ?>">
                        <label class="form-label">Option</label>
                        <select class="form-select" name="option">
                            <option value="">Toutes les options</option>
                            <?php 
                            $options = $pdo->query("SELECT DISTINCT option FROM niveaux_options WHERE niveau = 'secondaire' ORDER BY option")
                                          ->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($options as $opt): ?>
                                <option value="<?= $opt ?>" <?= $filtre_option === $opt ? 'selected' : '' ?>>
                                    <?= $opt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
            <!-- Formulaire d'ajout d'élève -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4 student-card">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="fas fa-user-plus me-1"></i>Ajouter un élève</h2>
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
                                <label class="form-label">Lieu de naissance</label>
                                <input type="text" class="form-control" name="lieu_naissance" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Niveau</label>
                                <select class="form-select" name="niveau" id="niveau" required onchange="updateFormFields(this)">
                                    <option value="">Sélectionner...</option>
                                    <option value="primaire">Primaire</option>
                                    <option value="secondaire">Secondaire</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Année</label>
                                <select class="form-select" name="annee" id="annee" required>
                                    <option value="">Sélectionner...</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Classe</label>
                                <select class="form-select" name="classe" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                    <option value="E">E</option>
                                </select>
                            </div>
                            <div class="mb-3" id="option-container" style="display: none;">
                                <label class="form-label">Option (obligatoire pour secondaire)</label>
                                <input type="text" class="form-control" name="option" id="option">
                            </div>
                            <button type="submit" name="ajouter_eleve" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Affichage de la liste des élèves -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0"><i class="fas fa-list me-1"></i>Liste des élèves</h2>
                        <span class="badge bg-light text-dark"><?= count($eleves) ?> élève(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($eleves) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nom & Prénom</th>
                                            <th>Informations</th>
                                            <th>Niveau/Classe</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eleves as $eleve): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($eleve['prenom']) ?> <?= htmlspecialchars($eleve['nom']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($eleve['email']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?= $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?><br>
                                                    Né(e) le <?= date('d/m/Y', strtotime($eleve['date_naissance'])) ?> à <?= htmlspecialchars($eleve['lieu_naissance']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge niveau-badge">
                                                        <?= ucfirst($eleve['niveau']) ?> <?= $eleve['annee'] ?><?= $eleve['classe'] ?>
                                                    </span>
                                                    <?php if ($eleve['niveau'] === 'secondaire'): ?>
                                                        <span class="badge secondary-option mt-1">
                                                            <?= $eleve['option'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <!-- Bouton qui va afficher/masquer les actions -->
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#actionsCollapse<?= $eleve['id'] ?>" aria-expanded="false" aria-controls="actionsCollapse<?= $eleve['id'] ?>">
                                                        Détails
                                                    </button>
                                                    
                                                    <!-- Conteneur collapsible qui contient les actions -->
                                                    <div class="collapse mt-2" id="actionsCollapse<?= $eleve['id'] ?>">
                                                        <div class="card card-body p-2">
                                                            <!-- Bouton Changer de niveau / ouvrir modal -->
                                                            <button class="btn btn-sm btn-outline-primary w-100 mb-1" data-bs-toggle="modal" data-bs-target="#changeNiveauModal<?= $eleve['id'] ?>">
                                                                Changer de niveau/classe
                                                            </button>
                                                            <!-- Formulaire de suppression -->
                                                            <form method="POST" class="mb-0">
                                                                <input type="hidden" name="eleve_id" value="<?= $eleve['id'] ?>">
                                                                <button type="submit" name="supprimer" class="btn btn-sm btn-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet élève ?')">
                                                                    Supprimer
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Modal pour changer de niveau/classe -->
                                                    <div class="modal fade" id="changeNiveauModal<?= $eleve['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Changer de niveau/classe</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Niveau</label>
                                                                            <select class="form-select" name="niveau" id="modal-niveau-<?= $eleve['id'] ?>" required onchange="updateModalFields(this, <?= $eleve['id'] ?>)">
                                                                                <option value="primaire" <?= $eleve['niveau'] === 'primaire' ? 'selected' : '' ?>>Primaire</option>
                                                                                <option value="secondaire" <?= $eleve['niveau'] === 'secondaire' ? 'selected' : '' ?>>Secondaire</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Année</label>
                                                                            <select class="form-select" name="annee" id="modal-annee-<?= $eleve['id'] ?>" required data-current="<?= $eleve['annee'] ?>">
                                                                                <?php
                                                                                $max_annees = $eleve['niveau'] === 'primaire' ? 8 : 4;
                                                                                for ($i = 1; $i <= $max_annees; $i++):
                                                                                ?>
                                                                                    <option value="<?= $i ?>" <?= $eleve['annee'] == $i ? 'selected' : '' ?>>
                                                                                        <?= $i ?>ère année
                                                                                    </option>
                                                                                <?php endfor; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Classe</label>
                                                                            <select class="form-select" name="classe" required>
                                                                                <option value="A" <?= $eleve['classe'] === 'A' ? 'selected' : '' ?>>A</option>
                                                                                <option value="B" <?= $eleve['classe'] === 'B' ? 'selected' : '' ?>>B</option>
                                                                                <option value="C" <?= $eleve['classe'] === 'C' ? 'selected' : '' ?>>C</option>
                                                                                <option value="D" <?= $eleve['classe'] === 'D' ? 'selected' : '' ?>>D</option>
                                                                                <option value="E" <?= $eleve['classe'] === 'E' ? 'selected' : '' ?>>E</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3" id="modal-option-container-<?= $eleve['id'] ?>" style="<?= $eleve['niveau'] !== 'secondaire' ? 'display:none;' : '' ?>">
                                                                            <label class="form-label">Option (obligatoire pour secondaire)</label>
                                                                            <input type="text" class="form-control" name="option" id="modal-option-<?= $eleve['id'] ?>" <?= $eleve['niveau'] === 'secondaire' ? 'required' : '' ?> data-current="<?= htmlspecialchars($eleve['option']) ?>" value="<?= htmlspecialchars($eleve['option']) ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <input type="hidden" name="eleve_id" value="<?= $eleve['id'] ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" name="changer_niveau" class="btn btn-primary">Enregistrer</button>
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
                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun élève trouvé</h5>
                                <p class="text-muted"><?= ($filtre_niveau || $filtre_annee || $filtre_option) ? 'Modifiez vos critères de recherche' : 'Commencez par ajouter un élève' ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFormFields(select) {
    const anneeSelect = document.getElementById('annee');
    const optionContainer = document.getElementById('option-container');
    const optionInput = document.getElementById('option');
    
    // Mettre à jour les années
    anneeSelect.innerHTML = '<option value="">Sélectionner...</option>';
    const niveau = select.value;
    const maxAnnees = niveau === 'primaire' ? 8 : (niveau === 'secondaire' ? 4 : 0);

    for (let i = 1; i <= maxAnnees; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + 'ère année';
        anneeSelect.appendChild(option);
    }

    // Gérer l'affichage du champ option
    if (niveau === 'secondaire') {
        optionContainer.style.display = 'block';
        optionInput.required = true;
    } else {
        optionContainer.style.display = 'none';
        optionInput.required = false;
        optionInput.value = '';
    }
}

function updateModalFields(select, eleveId) {
    const anneeSelect = document.getElementById('modal-annee-' + eleveId);
    const optionContainer = document.getElementById('modal-option-container-' + eleveId);
    const optionInput = document.getElementById('modal-option-' + eleveId);
    
    // Récupérer les valeurs précédentes si définies (via data-current)
    const currentAnnee = anneeSelect.dataset.current || anneeSelect.value;
    const currentOption = optionInput.dataset.current || optionInput.value;
    
    anneeSelect.innerHTML = '';
    const niveau = select.value;
    const maxAnnees = niveau === 'primaire' ? 8 : (niveau === 'secondaire' ? 4 : 0);

    for (let i = 1; i <= maxAnnees; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + 'ère année';
        if (parseInt(currentAnnee) === i) {
            option.selected = true;
        }
        anneeSelect.appendChild(option);
    }

    if (niveau === 'secondaire') {
        optionContainer.style.display = 'block';
        optionInput.required = true;
        if (currentOption) {
            optionInput.value = currentOption;
        }
    } else {
        optionContainer.style.display = 'none';
        optionInput.required = false;
        optionInput.value = '';
    }
}

function updateFilterAnnees(select) {
    const anneeSelect = document.getElementById('filter-annee');
    const optionContainer = document.getElementById('filter-option-container');
    anneeSelect.innerHTML = '<option value="">Toutes les années</option>';

    const niveau = select.value;
    const maxAnnees = niveau === 'primaire' ? 8 : (niveau === 'secondaire' ? 4 : 8);

    for (let i = 1; i <= maxAnnees; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i + 'ère année';
        anneeSelect.appendChild(option);
    }

    if (niveau === 'secondaire') {
        optionContainer.style.display = 'block';
    } else {
        optionContainer.style.display = 'none';
        optionContainer.querySelector('select').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const niveauSelect = document.getElementById('filter-niveau');
    updateFilterAnnees(niveauSelect);
});
</script>

<?php require_once 'footer.php'; ?>
