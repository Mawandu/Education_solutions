<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et a le rôle "individu"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'individu') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'individu associé
$stmt = $pdo->prepare("SELECT i.*, u.email FROM individus i JOIN users u ON i.user_id = u.id WHERE i.user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$individu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$individu) {
    $error = "Aucune information personnelle trouvée. Veuillez configurer votre profil.";
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $email = $_POST['email'] ?? '';

    if ($nom && $prenom && $sexe && $date_naissance) {
        if ($individu) {
            // Mise à jour des informations existantes
            $query = "UPDATE individus SET 
                     nom = :nom, 
                     prenom = :prenom, 
                     sexe = :sexe, 
                     date_naissance = :date_naissance, 
                     telephone = :telephone, 
                     adresse = :adresse 
                     WHERE user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'sexe' => $sexe,
                'date_naissance' => $date_naissance,
                'telephone' => $telephone,
                'adresse' => $adresse,
                'user_id' => $user_id
            ]);

            // Mise à jour de l'email dans la table users
            $query = "UPDATE users SET email = :email WHERE id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'email' => $email,
                'user_id' => $user_id
            ]);
        } else {
            // Insertion d'un nouvel individu
            $query = "INSERT INTO individus (
                     user_id, nom, prenom, sexe, date_naissance, telephone, adresse
                     ) VALUES (
                     :user_id, :nom, :prenom, :sexe, :date_naissance, :telephone, :adresse)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'user_id' => $user_id,
                'nom' => $nom,
                'prenom' => $prenom,
                'sexe' => $sexe,
                'date_naissance' => $date_naissance,
                'telephone' => $telephone,
                'adresse' => $adresse
            ]);

            // Mise à jour de l'email dans la table users
            $query = "UPDATE users SET email = :email, configured = 1 WHERE id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'email' => $email,
                'user_id' => $user_id
            ]);
        }

        header("Location: user_dashboard.php?message=Paramètres mis à jour avec succès");
        exit();
    } else {
        $error = "Les champs obligatoires (Nom, Prénom, Sexe, Date de naissance) sont requis.";
    }
}

$pageTitle = "Paramètres du profil";
require_once 'header.php';
?>

<style>
    :root {
        --background: #f0f8ff;
        --text: #333;
        --section-bg: white;
        --section-border: #ddd;
        --primary: #3f51b5;
        --danger: #f44336;
        --input-bg: #f9f9f9;
        --input-border: #ddd;
    }

    [data-theme="dark"] {
        --background: #1a237e;
        --text: #f5f5f5;
        --section-bg: #303f9f;
        --section-border: #5c6bc0;
        --primary: #7986cb;
        --danger: #d32f2f;
        --input-bg: #3949ab;
        --input-border: #5c6bc0;
    }

    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px;
        z-index: 1000;
        background: #1a237e;
    }

    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        z-index: 1000;
        background: #1a237e;
    }

    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
        font-family: Arial, sans-serif;
        background-color: var(--background);
        color: var(--text);
    }

    .settings-container {
        margin: 0;
        padding: 20px;
        height: calc(100vh - 140px);
        overflow-y: scroll;
        -ms-overflow-style: none;
        scrollbar-width: none;
        box-sizing: border-box;
        width: 100%;
    }

    .settings-container::-webkit-scrollbar {
        display: none;
    }

    h1, h2 {
        color: var(--text);
        margin: 0 0 20px;
    }

    .button {
        display: inline-block;
        padding: 10px 20px;
        margin: 5px 5px 5px 0;
        background-color: var(--primary);
        color: white;
        text-decoration: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .button:hover {
        background-color: #303f9f;
    }

    .section {
        background-color: var(--section-bg);
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        max-width: 800px;
        margin: 0 auto 20px;
    }

    .error {
        color: var(--danger);
        margin-bottom: 15px;
        font-size: 0.9rem;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .form-row {
        display: flex;
        gap: 15px;
    }

    .form-row > label {
        flex: 1;
    }

    label {
        display: flex;
        flex-direction: column;
        font-weight: 500;
        color: var(--text);
    }

    input[type="text"],
    input[type="date"],
    input[type="email"],
    select {
        padding: 10px;
        border: 1px solid var(--input-border);
        border-radius: 5px;
        background-color: var(--input-bg);
        color: var(--text);
        font-size: 1rem;
        margin-top: 5px;
    }

    input[type="submit"] {
        background-color: var(--primary);
        color: white;
        padding: 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.3s ease;
    }

    input[type="submit"]:hover {
        background-color: #303f9f;
    }
</style>

<main>
    <div class="settings-container">
        <h1>Paramètres du profil</h1>
        <div>
            <a href="user_dashboard.php" class="button">Retour au tableau de bord</a>
        </div>

        <!-- Section : Formulaire de configuration -->
        <div class="section">
            <h2>Informations personnelles</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-row">
                    <label>
                        Nom :
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($individu['nom'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Prénom :
                        <input type="text" name="prenom" value="<?php echo htmlspecialchars($individu['prenom'] ?? ''); ?>" required>
                    </label>
                </div>

                <div class="form-row">
                    <label>
                        Sexe :
                        <select name="sexe" required>
                            <option value="M" <?php echo isset($individu['sexe']) && $individu['sexe'] === 'M' ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?php echo isset($individu['sexe']) && $individu['sexe'] === 'F' ? 'selected' : ''; ?>>Féminin</option>
                        </select>
                    </label>
                    <label>
                        Date de naissance :
                        <input type="date" name="date_naissance" value="<?php echo htmlspecialchars($individu['date_naissance'] ?? ''); ?>" required>
                    </label>
                </div>

                <label>
                    Adresse :
                    <input type="text" name="adresse" value="<?php echo htmlspecialchars($individu['adresse'] ?? ''); ?>">
                </label>

                <div class="form-row">
                    <label>
                        Téléphone :
                        <input type="text" name="telephone" value="<?php echo htmlspecialchars($individu['telephone'] ?? ''); ?>">
                    </label>
                    <label>
                        Email :
                        <input type="email" name="email" value="<?php echo htmlspecialchars($individu['email'] ?? ''); ?>" required>
                    </label>
                </div>

                <input type="submit" value="Enregistrer les modifications">
            </form>
        </div>

        <!-- Section pour la gestion des photos -->
        <div class="section">
            <h2>Photos du profil</h2>
            <form action="upload_photos.php" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <label>
                        Photo de profil :
                        <input type="file" name="photo_profil" accept="image/*">
                    </label>
                    <label>
                        Photo de couverture :
                        <input type="file" name="photo_couverture" accept="image/*">
                    </label>
                </div>
                <input type="submit" value="Mettre à jour les photos">
            </form>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>
