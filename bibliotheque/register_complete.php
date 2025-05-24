<?php
session_start();
require_once 'db_connect.php';

// Vérification de l'étape précédente
if (!isset($_SESSION['register_email']) || !isset($_SESSION['register_role'])) {
    header("Location: register.php");
    exit();
}

$role = $_SESSION['register_role'];
$email = $_SESSION['register_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des champs obligatoires
        $required_fields = [];
        if ($role === 'individu') {
            $required_fields = ['prenom', 'nom', 'adresse', 'date_naissance', 'sexe', 'telephone'];
        } elseif ($role === 'ecole') {
            $required_fields = ['nom_ecole', 'adresse', 'contact', 'pays', 'ville', 'type_ecole', 'nb_classes', 'nb_eleves'];
        } elseif ($role === 'bibliotheque') {
            $required_fields = ['nom_bibliotheque', 'adresse', 'contact', 'pays', 'ville'];
        }

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est requis.");
            }
        }

        if ($role === 'individu') {
            $prenom = trim($_POST['prenom']);
            $nom = trim($_POST['nom']);
            $username = "$prenom $nom";
            $adresse = trim($_POST['adresse']);
            $date_naissance = $_POST['date_naissance'];
            $sexe = $_POST['sexe'];
            $telephone = trim($_POST['telephone']);
            $contact = trim($_POST['contact'] ?? '');
            $pays = trim($_POST['pays'] ?? '');
            $ville = trim($_POST['ville'] ?? '');

            // Mise à jour de l'utilisateur
            $stmt = $pdo->prepare("UPDATE users SET username = ?, configured = 1 WHERE email = ?");
            $stmt->execute([$username, $email]);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user_id = $stmt->fetchColumn();

            // Insertion dans la table individus
            $stmt = $pdo->prepare("INSERT INTO individus 
                                 (user_id, adresse, date_naissance, sexe, telephone, contact, pays, ville) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $adresse, $date_naissance, $sexe, $telephone, $contact, $pays, $ville]);

        } elseif ($role === 'ecole') {
            $nom_ecole = trim($_POST['nom_ecole']);
            $adresse = trim($_POST['adresse']);
            $contact = trim($_POST['contact']);
            $pays = trim($_POST['pays']);
            $ville = trim($_POST['ville']);
            $type_ecole = $_POST['type_ecole'] ?? 'public';
            $nb_classes = (int)($_POST['nb_classes'] ?? 0);
            $nb_eleves = (int)($_POST['nb_eleves'] ?? 0);
            $date_creation = date('Y-m-d');

            // Mise à jour de l'utilisateur
            $stmt = $pdo->prepare("UPDATE users SET username = ?, configured = 1 WHERE email = ?");
            $stmt->execute([$nom_ecole, $email]);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user_id = $stmt->fetchColumn();

            // Insertion dans la table ecoles
            $stmt = $pdo->prepare("INSERT INTO ecoles 
                                 (user_id, nom_ecole, adresse, contact, pays, ville, date_creation, type_ecole, nb_classes, nb_eleves, validated) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $nom_ecole, $adresse, $contact, $pays, $ville, $date_creation, $type_ecole, $nb_classes, $nb_eleves]);
            $entity_id = $pdo->lastInsertId();

            // Mise à jour du entity_id
            $stmt = $pdo->prepare("UPDATE users SET entity_id = ? WHERE id = ?");
            $stmt->execute([$entity_id, $user_id]);

        } elseif ($role === 'bibliotheque') {
            $nom_biblio = trim($_POST['nom_bibliotheque']);
            $adresse = trim($_POST['adresse']);
            $contact = trim($_POST['contact']);
            $pays = trim($_POST['pays']);
            $ville = trim($_POST['ville']);

            // Mise à jour de l'utilisateur
            $stmt = $pdo->prepare("UPDATE users SET username = ?, configured = 1 WHERE email = ?");
            $stmt->execute([$nom_biblio, $email]);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user_id = $stmt->fetchColumn();

            // Insertion dans la table bibliotheques
            $stmt = $pdo->prepare("INSERT INTO bibliotheques 
                                 (user_id, nom_bibliotheque, adresse, contact, pays, ville, valide) 
                                 VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $nom_biblio, $adresse, $contact, $pays, $ville]);
            $entity_id = $pdo->lastInsertId();

            // Mise à jour du entity_id
            $stmt = $pdo->prepare("UPDATE users SET entity_id = ? WHERE id = ?");
            $stmt->execute([$entity_id, $user_id]);
        }

        // Nettoyage et redirection
        unset($_SESSION['register_email']);
        unset($_SESSION['register_role']);
        $_SESSION['success'] = "Inscription complétée avec succès !";
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

$pageTitle = "Inscription - Étape 2";
require_once 'header_minimal.php'; // Utiliser le header minimaliste
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #e0e0e0;
        --error: #dc3545;
        --secondary: #2196F3;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --error: #dc3545;
        --secondary: #1976D2;
    }

    /* Styles généraux */
    main {
        position: absolute;
        top: 60px; /* Hauteur du header minimal */
        bottom: 40px; /* Hauteur du footer minimal */
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, rgba(0,121,107,0.1) 0%, rgba(76,175,80,0.05) 100%);
        overflow-y: auto;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    main::-webkit-scrollbar {
        display: none;
    }

    .auth-card {
        width: 100%;
        max-width: 600px;
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        margin: 2rem;
        transition: all 0.3s ease;
    }

    .auth-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .auth-header {
        background: var(--primary-color);
        color: white;
        padding: 1.5rem;
        text-align: center;
    }

    .auth-header h1 {
        margin: 0;
        font-size: 1.8rem;
    }

    .progress-steps {
        display: flex;
        justify-content: center;
        margin-top: 1rem;
    }

    .step {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .step.completed {
        background: white;
        color: var(--primary-color);
    }

    .step.active {
        background: white;
        color: var(--primary-color);
    }

    .step-divider {
        width: 50px;
        height: 2px;
        background: rgba(255,255,255,0.5);
        margin: 0 5px;
        align-self: center;
    }

    .auth-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }

    .auth-body {
        padding: 2rem;
    }

    .alert-error {
        background: rgba(244, 67, 54, 0.1);
        color: var(--error);
        padding: 0.8rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--error);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        flex: 1;
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-color);
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 1rem;
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
        outline: none;
    }

    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1em;
    }

    button[type="submit"] {
        width: 100%;
        padding: 1rem;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 121, 107, 0.2);
    }

    button[type="submit"]:hover {
        background: var(--primary-light);
        box-shadow: 0 6px 8px rgba(0, 121, 107, 0.3);
    }
</style>

<main>
    <div class="auth-card">
        <div class="auth-header">
            <h1><i class="fas fa-user-edit"></i> Compléter votre profil</h1>
            <div class="progress-steps">
                <div class="step completed"><i class="fas fa-check"></i></div>
                <div class="step-divider"></div>
                <div class="step active">2</div>
            </div>
            <p>Type de compte : <strong><?= ucfirst($role) ?></strong></p>
        </div>

        <div class="auth-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <?php if ($role === 'individu'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prénom*</label>
                            <input type="text" name="prenom" required>
                        </div>
                        <div class="form-group">
                            <label>Nom*</label>
                            <input type="text" name="nom" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Adresse*</label>
                        <input type="text" name="adresse" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de naissance*</label>
                            <input type="date" name="date_naissance" required>
                        </div>
                        <div class="form-group">
                            <label>Sexe*</label>
                            <select name="sexe" required>
                                <option value="">-- Sélectionnez --</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Téléphone*</label>
                            <input type="tel" name="telephone" required>
                        </div>
                        <div class="form-group">
                            <label>Contact secondaire</label>
                            <input type="text" name="contact">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pays</label>
                            <input type="text" name="pays">
                        </div>
                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="ville">
                        </div>
                    </div>

                <?php elseif ($role === 'ecole'): ?>
                    <div class="form-group">
                        <label>Nom de l’établissement*</label>
                        <input type="text" name="nom_ecole" required>
                    </div>
                    <div class="form-group">
                        <label>Adresse*</label>
                        <input type="text" name="adresse" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ville*</label>
                            <input type="text" name="ville" required>
                        </div>
                        <div class="form-group">
                            <label>Pays*</label>
                            <input type="text" name="pays" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Contact*</label>
                        <input type="text" name="contact" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Type d’école*</label>
                            <select name="type_ecole" required>
                                <option value="public">Public</option>
                                <option value="prive">Privé</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nombre de classes*</label>
                            <input type="number" name="nb_classes" min="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nombre d’élèves*</label>
                        <input type="number" name="nb_eleves" min="1" required>
                    </div>

                <?php elseif ($role === 'bibliotheque'): ?>
                    <div class="form-group">
                        <label>Nom de la bibliothèque*</label>
                        <input type="text" name="nom_bibliotheque" required>
                    </div>
                    <div class="form-group">
                        <label>Adresse*</label>
                        <input type="text" name="adresse" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ville*</label>
                            <input type="text" name="ville" required>
                        </div>
                        <div class="form-group">
                            <label>Pays*</label>
                            <input type="text" name="pays" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Contact*</label>
                        <input type="text" name="contact" required>
                    </div>
                <?php endif; ?>

                <button type="submit">
                    Finaliser l’inscription <i class="fas fa-check"></i>
                </button>
            </form>
        </div>
    </div>
</main>

<?php require_once 'footer_minimal.php'; // Utiliser le footer minimaliste ?>