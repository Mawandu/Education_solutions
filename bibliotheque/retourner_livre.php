<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a le rôle "individu" ou "ecole"
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['individu', 'ecole'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Déterminer le tableau de bord de retour en fonction du rôle
$dashboard_url = $role === 'ecole' ? 'ecole_dashboard.php' : 'user_dashboard.php';
$page_title = "Retourner un livre - " . ($role === 'ecole' ? 'École' : 'Individu');

// Récupérer les livres empruntés par l'utilisateur
$stmt = $pdo->prepare("
    SELECT b.id, b.title, b.author, b.domain, l.name AS library_name, bor.borrow_date, b.borrow_duration
    FROM books b
    JOIN borrows bor ON b.id = bor.book_id
    JOIN libraries l ON b.library_id = l.id
    WHERE bor.user_id = :user_id AND bor.return_date IS NULL
");
$stmt->execute(['user_id' => $user_id]);
$borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le temps restant pour les livres empruntés
$now = new DateTime();
$borrowed_books_to_display = [];
foreach ($borrowed_books as $book) {
    $borrow_date = new DateTime($book['borrow_date']);
    $due_date = (clone $borrow_date)->modify("+{$book['borrow_duration']} days");
    $interval = $now->diff($due_date);
    $days_remaining = $interval->invert ? 0 : $interval->days;

    // Si le délai est dépassé, marquer automatiquement comme rendu
    if ($interval->invert) {
        $stmt = $pdo->prepare("UPDATE borrows SET return_date = NOW() WHERE book_id = :book_id AND user_id = :user_id AND return_date IS NULL");
        $stmt->execute(['book_id' => $book['id'], 'user_id' => $user_id]);
        $stmt = $pdo->prepare("UPDATE books SET available = 1 WHERE id = :book_id");
        $stmt->execute(['book_id' => $book['id']]);
        continue;
    }

    $book['days_remaining'] = $days_remaining;
    $book['due_date'] = $due_date->format('Y-m-d H:i:s');
    $borrowed_books_to_display[] = $book;
}

// Gestion de l'action de rendre un livre
if (isset($_GET['return_book_id'])) {
    $book_id = $_GET['return_book_id'];

    // Vérifier que le livre appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM borrows WHERE book_id = :book_id AND user_id = :user_id AND return_date IS NULL");
    $stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
    $borrow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($borrow) {
        // Marquer le livre comme rendu
        $stmt = $pdo->prepare("UPDATE borrows SET return_date = NOW() WHERE book_id = :book_id AND user_id = :user_id AND return_date IS NULL");
        $stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);

        // Rendre le livre disponible
        $stmt = $pdo->prepare("UPDATE books SET available = 1 WHERE id = :book_id");
        $stmt->execute(['book_id' => $book_id]);

        // Rediriger pour éviter un rechargement de l'action
        header("Location: retourner_livre.php");
        exit;
    }
}

$pageTitle = $page_title;
require_once 'header.php';
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b; /* Même couleur que dans ecole_dashboard.php */
        --primary-light: #4db6ac;
        --background: #e6f3fa;
        --text: #333;
        --section-bg: white;
        --section-border: #ddd;
        --table-header-bg: #f2f2f2;
        --primary: #4CAF50;
        --danger: #f44336;
        --secondary: #2196F3;
        --input-bg: #f9f9f9;
        --input-border: #ddd;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text: #f5f5f5;
        --section-bg: #3a3a3a;
        --section-border: #555;
        --table-header-bg: #4a4a4a;
        --primary: #4CAF50;
        --danger: #d32f2f;
        --secondary: #1976D2;
        --input-bg: #4a4a4a;
        --input-border: #666;
    }

    /* Forcer le header (.navbar) à rester fixe avec la bonne couleur */
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

    /* Forcer le footer à rester fixe avec la bonne couleur */
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
    .return-container {
        margin: 0;
        padding: 20px;
        height: calc(100vh - 120px); /* Hauteur totale moins header (70px) et footer (50px) */
        overflow-y: scroll;
        -ms-overflow-style: none; /* Masquer la barre de défilement sur IE/Edge */
        scrollbar-width: none; /* Masquer la barre de défilement sur Firefox */
        box-sizing: border-box;
        width: 100%;
    }

    /* Masquer la barre de défilement sur Chrome/Safari */
    .return-container::-webkit-scrollbar {
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

    .button-return {
        background-color: var(--danger);
    }

    .button-return:hover {
        background-color: #d32f2f;
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
    <div class="return-container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <div>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="button">Retour</a>
        </div>

        <!-- Section : Livres empruntés -->
        <div class="section">
            <h2>Livres empruntés</h2>
            <?php if (count($borrowed_books_to_display) > 0): ?>
                <table>
                    <tr>
                        <th>Titre</th>
                        <th>Auteur</th>
                        <th>Bibliothèque</th>
                        <th>Date d'emprunt</th>
                        <th>Temps restant</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($borrowed_books_to_display as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['library_name']); ?></td>
                            <td><?php echo htmlspecialchars($book['borrow_date']); ?></td>
                            <td><?php echo $book['days_remaining']; ?> jours (jusqu'au <?php echo htmlspecialchars($book['due_date']); ?>)</td>
                            <td>
                                <a href="retourner_livre.php?return_book_id=<?php echo $book['id']; ?>" class="button button-return">Rendre</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Vous n'avez aucun livre emprunté actuellement.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>
