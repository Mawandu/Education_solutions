<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a le rôle "bibliotheque"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bibliotheque') {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de la bibliothèque associée à l'utilisateur via user_id
$stmt = $pdo->prepare("SELECT id FROM libraries WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$library_id = $stmt->fetchColumn();

if (!$library_id) {
    echo "Aucune bibliothèque associée à cet utilisateur.";
    exit;
}

// Statistiques : Nombre total de livres
$stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE library_id = :library_id");
$stmt->execute(['library_id' => $library_id]);
$total_books = $stmt->fetchColumn();

// Statistiques : Nombre de livres disponibles
$stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE library_id = :library_id AND available = 1");
$stmt->execute(['library_id' => $library_id]);
$available_books = $stmt->fetchColumn();

// Statistiques : Nombre total d'emprunts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows bor JOIN books b ON bor.book_id = b.id WHERE b.library_id = :library_id");
$stmt->execute(['library_id' => $library_id]);
$total_borrows = $stmt->fetchColumn();

// Statistiques : Nombre d'emprunts actifs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows bor JOIN books b ON bor.book_id = b.id WHERE b.library_id = :library_id AND bor.return_date IS NULL");
$stmt->execute(['library_id' => $library_id]);
$active_borrows = $stmt->fetchColumn();

$pageTitle = "Statistiques de la bibliothèque";
require_once 'header.php';
?>

<style>
    /* Variables de thème */
    :root {
        --background: #e6f3fa;
        --text: #333;
        --section-bg: white;
        --section-border: #ddd;
        --table-header-bg: #f2f2f2;
        --primary: #4CAF50;
        --danger: #f44336;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text: #f5f5f5;
        --section-bg: #3a3a3a;
        --section-border: #555;
        --table-header-bg: #4a4a4a;
        --primary: #4CAF50;
        --danger: #d32f2f;
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
    .stats-container {
        margin: 0; /* Pas de marges latérales ni supérieures */
        padding: 20px;
        height: calc(100vh - 140px); /* Hauteur totale moins header et footer */
        overflow-y: scroll;
        -ms-overflow-style: none; /* Masquer la barre de défilement sur IE/Edge */
        scrollbar-width: none; /* Masquer la barre de défilement sur Firefox */
        box-sizing: border-box;
        width: 100%; /* Occupe toute la largeur */
    }

    /* Masquer la barre de défilement sur Chrome/Safari */
    .stats-container::-webkit-scrollbar {
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
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--section-border);
    }

    th {
        background-color: var(--table-header-bg);
        font-weight: bold;
    }

    td {
        font-size: 1rem;
    }
</style>

<main>
    <div class="stats-container">
        <h1>Statistiques de la bibliothèque</h1>
        <div>
            <a href="bibliotheque_dashboard.php" class="button">Retour</a>
        </div>

        <!-- Section : Statistiques générales -->
        <div class="section">
            <h2>Statistiques générales</h2>
            <table>
                <tr>
                    <th>Métrique</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Nombre total de livres</td>
                    <td><?php echo htmlspecialchars($total_books); ?></td>
                </tr>
                <tr>
                    <td>Nombre de livres disponibles</td>
                    <td><?php echo htmlspecialchars($available_books); ?></td>
                </tr>
                <tr>
                    <td>Nombre total d'emprunts</td>
                    <td><?php echo htmlspecialchars($total_borrows); ?></td>
                </tr>
                <tr>
                    <td>Nombre d'emprunts actifs</td>
                    <td><?php echo htmlspecialchars($active_borrows); ?></td>
                </tr>
            </table>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>