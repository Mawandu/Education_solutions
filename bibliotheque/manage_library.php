<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a le rôle "bibliotheque"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bibliotheque') {
    $_SESSION['error'] = "Accès refusé.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer l'ID de la bibliothèque associée
$stmt = $pdo->prepare("SELECT id FROM libraries WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$library_id = $stmt->fetchColumn();

if (!$library_id) {
    $_SESSION['error'] = "Aucune bibliothèque associée à cet utilisateur.";
    header("Location: bibliotheque_dashboard.php");
    exit;
}

// Retirer un livre (fonctionnalité existante)
if (isset($_POST['remove_book'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de sécurité invalide";
        header("Location: manage_library.php");
        exit;
    }

    $book_id = $_POST['book_id'];
    $stmt = $pdo->prepare("SELECT pdf_path FROM books WHERE id = :book_id AND library_id = :library_id");
    $stmt->execute(['book_id' => $book_id, 'library_id' => $library_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($book) {
        // Suppression du fichier
        if ($book['pdf_path'] && file_exists($book['pdf_path'])) {
            unlink($book['pdf_path']);
        }

        // Suppression en base
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM borrows WHERE book_id = :book_id");
            $stmt->execute(['book_id' => $book_id]);

            $stmt = $pdo->prepare("DELETE FROM books WHERE id = :book_id AND library_id = :library_id");
            $stmt->execute(['book_id' => $book_id, 'library_id' => $library_id]);

            $pdo->commit();
            $_SESSION['success'] = "Livre retiré avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    header("Location: manage_library.php");
    exit;
}

// Liste des livres de la bibliothèque
$stmt = $pdo->prepare("SELECT id, title, domain, author, available, borrow_duration FROM books WHERE library_id = :library_id");
$stmt->execute(['library_id' => $library_id]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des emprunts actifs
$stmt = $pdo->prepare("
    SELECT b.id, b.title, b.domain, b.author, u.username, u.role, bor.borrow_date, b.borrow_duration
    FROM books b
    JOIN borrows bor ON b.id = bor.book_id
    JOIN users u ON bor.user_id = u.id
    JOIN (
        SELECT book_id, MAX(borrow_date) AS max_borrow_date
        FROM borrows
        WHERE return_date IS NULL
        GROUP BY book_id
    ) latest_borrow ON bor.book_id = latest_borrow.book_id AND bor.borrow_date = latest_borrow.max_borrow_date
    WHERE b.library_id = :library_id AND bor.return_date IS NULL
");
$stmt->execute(['library_id' => $library_id]);
$current_borrows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le temps écoulé et restant
$now = new DateTime();
$borrows_to_display = [];
foreach ($current_borrows as $borrow) {
    $borrow_date = new DateTime($borrow['borrow_date']);
    $loan_duration_days = $borrow['borrow_duration'];
    
    $due_date = (clone $borrow_date)->modify("+$loan_duration_days days");
    $interval = $now->diff($borrow_date);
    $days_elapsed = $interval->days;
    $interval_remaining = $now->diff($due_date);
    $days_remaining = $interval_remaining->invert ? 0 : $interval_remaining->days;

    if ($interval_remaining->invert) {
        $stmt = $pdo->prepare("UPDATE borrows SET return_date = NOW() WHERE book_id = :book_id AND return_date IS NULL");
        $stmt->execute(['book_id' => $borrow['id']]);
        $stmt = $pdo->prepare("UPDATE books SET available = 1 WHERE id = :book_id");
        $stmt->execute(['book_id' => $borrow['id']]);
        continue;
    }

    $borrow['days_elapsed'] = $days_elapsed;
    $borrow['days_remaining'] = $days_remaining;
    $borrows_to_display[$borrow['id']] = $borrow;
}

$pageTitle = "Gérer votre bibliothèque";
require_once 'header.php';

// Afficher les messages
if (isset($_SESSION['error'])): ?>
    <div class="error-message"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="success-message"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<style>
    /* Variables de thème */
    :root {
        --background: #e6f3fa;
        --text: #333;
        --section-bg: white;
        --section-border: #ddd;
        --table-header-bg: #f2f2f2;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text: #f5f5f5;
        --section-bg: #3a3a3a;
        --section-border: #555;
        --table-header-bg: #4a4a4a;
    }

    /* Forcer le header à rester fixe */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px;
        z-index: 1000;
        background: #00796b; /* Couleur du header (vert foncé) */
    }

    /* Forcer le footer à rester fixe */
    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        z-index: 1000;
        background: #00796b; /* Couleur du footer (vert foncé) */
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
    .manage-container {
        margin: 0; /* Supprimer la marge supérieure pour coller au header */
        padding: 20px;
        height: calc(100vh - 140px); /* Hauteur totale moins header et footer */
        overflow-y: scroll;
        -ms-overflow-style: none; /* Masquer la barre de défilement sur IE/Edge */
        scrollbar-width: none; /* Masquer la barre de défilement sur Firefox */
        box-sizing: border-box;
        width: 100%; /* Occupe toute la largeur */
    }

    /* Masquer la barre de défilement sur Chrome/Safari */
    .manage-container::-webkit-scrollbar {
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
        background-color: #4CAF50;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .button:hover {
        background-color: #45a049;
    }

    .button-remove {
        background-color: #f44336;
    }

    .button-remove:hover {
        background-color: #da190b;
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
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid var(--section-border);
    }

    th {
        background-color: var(--table-header-bg);
    }

    form {
        display: inline;
    }

    .error-message {
        padding: 15px;
        margin: 20px 0;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        color: #721c24;
    }

    .success-message {
        padding: 15px;
        margin: 20px 0;
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
        color: #155724;
    }

    .button-danger {
        background-color: #dc3545;
    }

    .button-danger:hover {
        background-color: #bb2d3b;
    }
</style>

<main>
    <div class="manage-container">
        <h1>Gérer votre bibliothèque</h1>
        <div>
            <a href="bibliotheque_dashboard.php" class="button">Retour</a>
        </div>

        <!-- Section : Liste des livres -->
        <div class="section">
            <h2>Vos livres</h2>
            
            <?php if (count($books) > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Domaine</th>
                        <th>Auteur</th>
                        <th>Disponible</th>
                        <th>Durée d'emprunt</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($books as $book): ?>
                     
                        <tr>
                            <td><?php echo htmlspecialchars($book['id']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['domain']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo $book['available'] ? 'Oui' : 'Non'; ?></td>
                            <td><?php echo htmlspecialchars($book['borrow_duration']); ?> jours</td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" name="remove_book" class="button button-remove">Retirer</button>
                                </form>
                                <form method="post" action="delete_book.php" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" class="button button-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Vous n'avez aucun livre dans votre bibliothèque.</p>
            <?php endif; ?>
        </div>

        <!-- Section : Livres en location -->
        <div class="section">
            <h2>Livres en location</h2>
            <?php if (count($borrows_to_display) > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Domaine</th>
                        <th>Auteur</th>
                        <th>Emprunté par</th>
                        <th>Type</th>
                        <th>Date d'emprunt</th>
                        <th>Temps écoulé</th>
                        <th>Temps restant</th>
                    </tr>
                    <?php foreach ($borrows_to_display as $borrow): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($borrow['id']); ?></td>
                            <td><?php echo htmlspecialchars($borrow['title']); ?></td>
                            <td><?php echo htmlspecialchars($borrow['domain']); ?></td>
                            <td><?php echo htmlspecialchars($borrow['author']); ?></td>
                            <td><?php echo htmlspecialchars($borrow['username']); ?></td>
                            <td><?php echo htmlspecialchars($borrow['role'] === 'ecole' ? 'École' : 'Individu'); ?></td>
                            <td><?php echo htmlspecialchars($borrow['borrow_date']); ?></td>
                            <td><?php echo $borrow['days_elapsed']; ?> jours</td>
                            <td><?php echo $borrow['days_remaining']; ?> jours</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Aucun livre en location actuellement.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>