<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et a le rôle "bibliotheque"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bibliotheque') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de la bibliothèque associée
$stmt = $pdo->prepare("SELECT * FROM libraries WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$library = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$library) {
    // Si aucune bibliothèque n'existe, on pourrait rediriger ou afficher un message
    $error = "Aucune bibliothèque associée. Veuillez configurer votre bibliothèque.";
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_bibliotheque = $_POST['nom_bibliotheque'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $type_bibliotheque = $_POST['type_bibliotheque'] ?? '';
    $date_creation = $_POST['date_creation'] ?? '';

    if ($nom_bibliotheque && $adresse && $type_bibliotheque && $date_creation) {
        if ($library) {
            // Mise à jour des informations existantes
            $query = "UPDATE libraries SET name = :name, location = :location, type_bibliotheque = :type_bibliotheque, date_creation = :date_creation WHERE user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'name' => $nom_bibliotheque,
                'location' => $adresse,
                'type_bibliotheque' => $type_bibliotheque,
                'date_creation' => $date_creation,
                'user_id' => $user_id
            ]);
        } else {
            // Insertion d'une nouvelle bibliothèque (cas où aucune bibliothèque n'existe)
            $query = "INSERT INTO libraries (user_id, name, location, type_bibliotheque, date_creation, validated) 
                      VALUES (:user_id, :name, :location, :type_bibliotheque, :date_creation, 0)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'user_id' => $user_id,
                'name' => $nom_bibliotheque,
                'location' => $adresse,
                'type_bibliotheque' => $type_bibliotheque,
                'date_creation' => $date_creation
            ]);

            // Marquer l'utilisateur comme configuré
            $query = "UPDATE users SET configured = 1 WHERE id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
        }

        header("Location: bibliotheque_dashboard.php?message=Paramètres mis à jour avec succès");
        exit();
    } else {
        $error = "Tous les champs obligatoires sont requis.";
    }
}

$pageTitle = "Paramètres de la bibliothèque";
require_once 'header.php';
?>

<style>
    /* Variables de thème */
    :root {
        --background: #e6f3fa;
        --text: #333;
        --section-bg: white;
        --section-border: #ddd;
        --primary: #4CAF50;
        --danger: #f44336;
        --input-bg: #f9f9f9;
        --input-border: #ddd;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text: #f5f5f5;
        --section-bg: #3a3a3a;
        --section-border: #555;
        --primary: #4CAF50;
        --danger: #d32f2f;
        --input-bg: #4a4a4a;
        --input-border: #666;
    }

    /* Forcer le header à rester fixe */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px;
        z-index: 1000;
        background: #00796b;
    }

    /* Forcer le footer à rester fixe */
    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        z-index: 1000;
        background: #00796b;
    }

    /* Ajuster le body */
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
        font-family: Arial, sans-serif;
        background-color: var(--background);
        color: var(--text);
    }

    /* Conteneur principal avec défilement */
    .settings-container {
        margin: 0; /* Pas de marges */
        padding: 20px;
        height: calc(100vh - 140px); /* Hauteur totale moins header et footer */
        overflow-y: scroll;
        -ms-overflow-style: none; /* Masquer la barre de défilement sur IE/Edge */
        scrollbar-width: none; /* Masquer la barre de défilement sur Firefox */
        box-sizing: border-box;
        width: 100%; /* Occupe toute la largeur */
    }

    /* Masquer la barre de défilement sur Chrome/Safari */
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
        background-color: #45a049;
    }

    .section {
        background-color: var(--section-bg);
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        max-width: 600px; /* Limite la largeur pour une meilleure lisibilité */
        margin: 0 auto 20px; /* Centré horizontalement */
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

    label {
        display: flex;
        flex-direction: column;
        font-weight: 500;
        color: var(--text);
    }

    input[type="text"],
    input[type="date"],
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
        background-color: #45a049;
    }
</style>

<main>
    <div class="settings-container">
        <h1>Paramètres de la bibliothèque</h1>
        <div>
            <a href="bibliotheque_dashboard.php" class="button">Retour</a>
        </div>

        <!-- Section : Formulaire de configuration -->
        <div class="section">
            <h2>Modifier les paramètres</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>
                    Nom de la bibliothèque :
                    <input type="text" name="nom_bibliotheque" value="<?php echo htmlspecialchars($library['name'] ?? ''); ?>" required>
                </label>
                <label>
                    Adresse physique :
                    <input type="text" name="adresse" value="<?php echo htmlspecialchars($library['location'] ?? ''); ?>" required>
                </label>
                <label>
                    Type de bibliothèque :
                    <select name="type_bibliotheque" required>
                        <option value="public" <?php echo isset($library['type_bibliotheque']) && $library['type_bibliotheque'] === 'public' ? 'selected' : ''; ?>>Public</option>
                        <option value="prive" <?php echo isset($library['type_bibliotheque']) && $library['type_bibliotheque'] === 'prive' ? 'selected' : ''; ?>>Privé</option>
                    </select>
                </label>
                <label>
                    Date de création :
                    <input type="date" name="date_creation" value="<?php echo htmlspecialchars($library['date_creation'] ?? ''); ?>" required>
                </label>
                <input type="submit" value="Mettre à jour">
            </form>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>
