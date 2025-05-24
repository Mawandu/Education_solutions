<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a le rôle "individu" ou "ecole"
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['individu', 'ecole'])) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Déterminer le tableau de bord de retour en fonction du rôle
$dashboard_url = $role === 'ecole' ? 'ecole_dashboard.php' : 'user_dashboard.php';
$page_title = "Emprunter un livre - " . ($role === 'ecole' ? 'École' : 'Individu');

// Charger les domaines depuis la table domains
$stmt = $pdo->prepare("SELECT name FROM domains ORDER BY name");
$stmt->execute();
$domains = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

// Récupérer les livres disponibles avec filtres
$search_title = isset($_GET['title']) ? trim($_GET['title']) : '';
$search_domain = isset($_GET['domain']) && $_GET['domain'] !== 'Tous' ? $_GET['domain'] : '';

$query = "SELECT b.id, b.title, b.domain, b.author, l.name AS library_name
          FROM books b
          JOIN libraries l ON b.library_id = l.id
          WHERE b.available = 1";
$params = [];

if ($search_title) {
    $query .= " AND b.title LIKE :title";
    $params['title'] = "%$search_title%";
}

if ($search_domain) {
    $query .= " AND b.domain = :domain";
    $params['domain'] = $search_domain;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $page_title;
require_once 'header.php';
?>

<style>
    /* Variables de thème */
    :root {
        --primary-color: #00796b;
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

    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
        font-family: Arial, sans-serif;
        background-color: var(--background);
        color: var(--text);
    }

    .borrow-container {
        margin: 0;
        padding: 20px;
        height: calc(100vh - 120px);
        overflow-y: scroll;
        -ms-overflow-style: none;
        scrollbar-width: none;
        box-sizing: border-box;
        width: 100%;
    }

    .borrow-container::-webkit-scrollbar {
        display: none;
    }

    h1, h2 {
        color: var(--text);
        margin: 0 0 20px;
    }

    .button {
        display: inline-block;
        padding: 10px 20px;
        margin: 5px;
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

    .button-borrow {
        background-color: var(--secondary);
    }

    .button-borrow:hover {
        background-color: #1976D2;
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

    form {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    label {
        font-weight: 500;
        color: var(--text);
    }

    input[type="text"],
    select {
        padding: 8px;
        margin-right: 10px;
        border: 1px solid var(--input-border);
        border-radius: 5px;
        background-color: var(--input-bg);
        color: var(--text);
        font-size: 1rem;
        flex: 1;
        min-width: 150px;
    }

    button[type="submit"] {
        background-color: var(--primary);
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button[type="submit"]:hover {
        background-color: #45a049;
    }
</style>

<main>
    <div class="borrow-container">
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
                    </tr>
                    <?php foreach ($borrowed_books_to_display as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['library_name']); ?></td>
                            <td><?php echo htmlspecialchars($book['borrow_date']); ?></td>
                            <td><?php echo $book['days_remaining']; ?> jours (jusqu'au <?php echo htmlspecialchars($book['due_date']); ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Vous n'avez aucun livre emprunté actuellement.</p>
            <?php endif; ?>
        </div>

        <!-- Section : Livres disponibles -->
        <div class="section">
            <h2>Livres disponibles</h2>
            <form method="get">
                <label>Filtres</label>
                <input type="text" name="title" placeholder="Titre :" value="<?php echo htmlspecialchars($search_title); ?>">
                <select name="domain">
                    <option value="Tous" <?php echo $search_domain === 'Tous' || $search_domain === '' ? 'selected' : ''; ?>>Domaine : Tous</option>
                    <?php foreach ($domains as $domain): ?>
                        <option value="<?php echo htmlspecialchars($domain); ?>" <?php echo $search_domain === $domain ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($domain); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filtrer</button>
            </form>

            <?php if (count($available_books) > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Domaine</th>
                        <th>Auteur</th>
                        <th>Bibliothèque</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($available_books as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['id']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['domain']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['library_name']); ?></td>
                            <td>
                                <!-- Le lien pointe vers borrow.php pour traiter l'emprunt -->
                                <a href="borrow.php?book_id=<?php echo $book['id']; ?>" class="button button-borrow">Emprunter</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Aucun livre disponible correspondant à vos critères.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>
