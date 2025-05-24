<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et a le rôle "ecole"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'école associée
$stmt = $pdo->prepare("SELECT * FROM ecoles WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$ecole = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ecole) {
    $error = "Aucune école associée. Veuillez configurer votre école.";
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_ecole = $_POST['nom_ecole'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $pays = $_POST['pays'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $date_creation = $_POST['date_creation'] ?? '';
    $type_ecole = $_POST['type_ecole'] ?? '';
    $nb_classes = $_POST['nb_classes'] ?? 0;
    $nb_eleves = $_POST['nb_eleves'] ?? 0;

    if ($nom_ecole && $adresse && $date_creation && $type_ecole) {
        if ($ecole) {
            // Mise à jour des informations existantes
            $query = "UPDATE ecoles SET 
                     nom_ecole = :nom_ecole, 
                     adresse = :adresse, 
                     contact = :contact, 
                     pays = :pays, 
                     ville = :ville, 
                     date_creation = :date_creation, 
                     type_ecole = :type_ecole, 
                     nb_classes = :nb_classes, 
                     nb_eleves = :nb_eleves 
                     WHERE user_id = :user_id";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'nom_ecole' => $nom_ecole,
                'adresse' => $adresse,
                'contact' => $contact,
                'pays' => $pays,
                'ville' => $ville,
                'date_creation' => $date_creation,
                'type_ecole' => $type_ecole,
                'nb_classes' => $nb_classes,
                'nb_eleves' => $nb_eleves,
                'user_id' => $user_id
            ]);
        } else {
            // Insertion d'une nouvelle école
            $query = "INSERT INTO ecoles (
                     user_id, nom_ecole, adresse, contact, pays, ville, 
                     date_creation, type_ecole, nb_classes, nb_eleves, validated
                     ) VALUES (
                     :user_id, :nom_ecole, :adresse, :contact, :pays, :ville, 
                     :date_creation, :type_ecole, :nb_classes, :nb_eleves, 0)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'user_id' => $user_id,
                'nom_ecole' => $nom_ecole,
                'adresse' => $adresse,
                'contact' => $contact,
                'pays' => $pays,
                'ville' => $ville,
                'date_creation' => $date_creation,
                'type_ecole' => $type_ecole,
                'nb_classes' => $nb_classes,
                'nb_eleves' => $nb_eleves
            ]);

            // Marquer l'utilisateur comme configuré
            $query = "UPDATE users SET configured = 1 WHERE id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
        }

        header("Location: ecole_dashboard.php?message=Paramètres mis à jour avec succès");
        exit();
    } else {
        $error = "Les champs obligatoires (Nom, Adresse, Date de création, Type) sont requis.";
    }
}

$pageTitle = "Paramètres de l'école";
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

    /* Structure identique à votre exemple */
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
    input[type="number"],
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
        <h1>Paramètres de l'école</h1>
        <div>
            <a href="ecole_dashboard.php" class="button">Retour au tableau de bord</a>
        </div>

        <!-- Section : Formulaire de configuration -->
        <div class="section">
            <h2>Informations de l'école</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-row">
                    <label>
                        Nom de l'école :
                        <input type="text" name="nom_ecole" value="<?php echo htmlspecialchars($ecole['nom_ecole'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Type d'école :
                        <select name="type_ecole" required>
                            <option value="public" <?php echo isset($ecole['type_ecole']) && $ecole['type_ecole'] === 'public' ? 'selected' : ''; ?>>Public</option>
                            <option value="prive" <?php echo isset($ecole['type_ecole']) && $ecole['type_ecole'] === 'prive' ? 'selected' : ''; ?>>Privé</option>
                        </select>
                    </label>
                </div>

                <label>
                    Adresse :
                    <input type="text" name="adresse" value="<?php echo htmlspecialchars($ecole['adresse'] ?? ''); ?>" required>
                </label>

                <div class="form-row">
                    <label>
                        Ville :
                        <input type="text" name="ville" value="<?php echo htmlspecialchars($ecole['ville'] ?? ''); ?>">
                    </label>
                    <label>
                        Pays :
                        <input type="text" name="pays" value="<?php echo htmlspecialchars($ecole['pays'] ?? ''); ?>">
                    </label>
                </div>

                <div class="form-row">
                    <label>
                        Contact (email/téléphone) :
                        <input type="text" name="contact" value="<?php echo htmlspecialchars($ecole['contact'] ?? ''); ?>">
                    </label>
                    <label>
                        Date de création :
                        <input type="date" name="date_creation" value="<?php echo htmlspecialchars($ecole['date_creation'] ?? ''); ?>" required>
                    </label>
                </div>

                <div class="form-row">
                    <label>
                        Nombre de classes :
                        <input type="number" name="nb_classes" min="1" value="<?php echo htmlspecialchars($ecole['nb_classes'] ?? 1); ?>">
                    </label>
                    <label>
                        Nombre d'élèves :
                        <input type="number" name="nb_eleves" min="0" value="<?php echo htmlspecialchars($ecole['nb_eleves'] ?? 0); ?>">
                    </label>
                </div>

                <input type="submit" value="Enregistrer les modifications">
            </form>
        </div>

        <!-- Section pour la gestion des photos -->
        <div class="section">
            <h2>Photos de l'école</h2>
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
