<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleve') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les livres disponibles depuis ecole_lecture
$stmt = $pdo->prepare("
    SELECT el.book_id, b.title, b.author, el.disponibilite_date, el.echeance_date
    FROM ecole_lecture el
    JOIN books b ON el.book_id = b.id
    WHERE el.role = 'eleve'
      AND (el.user_id = :user_id OR el.user_id IS NULL)
      AND el.echeance_date > NOW()
");
$stmt->execute(['user_id' => $user_id]);
$livres_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Tableau de bord - Élève";
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
        --meta-text: #666;
    }
    
    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --meta-text: #bbb;
    }
    
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--background);
        color: var(--text-color);
    }
    
    header.navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background-color: var(--primary-color) !important;
        color: white !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: flex;
        align-items: center;
        padding: 0 20px;
    }
    
    footer.footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50px;
        background-color: var(--primary-color) !important;
        color: white !important;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .main-container {
        position: absolute;
        top: 70px;
        bottom: 50px;
        left: 0;
        right: 0;
        overflow-y: auto;
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
        padding: 20px;
    }
    
    .welcome-header {
        position: fixed;
        top: 70px;
        left: 0;
        right: 0;
        background-color: var(--background);
        z-index: 999;
        text-align: center;
        padding: 20px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .welcome-header h1 {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .content-wrapper {
        margin-top: 110px;
    }
    
    .livres-table {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    
    th {
        background-color: var(--primary-color);
        color: white;
    }
    
    .read-btn {
        padding: 8px 15px;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }
    
    .read-btn:hover {
        background-color: var(--primary-light);
    }
</style>

<div class="main-container">
    <div class="welcome-header">
        <h1>Votre tableau de bord</h1>
        <p class="lead">Livres disponibles pour lecture</p>
    </div>
    
    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="livres-table">
                <h3>Vos livres disponibles</h3>
                <?php if (empty($livres_disponibles)): ?>
                    <p>Aucun livre disponible pour le moment</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Date de disponibilité</th>
                                <th>Échéance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres_disponibles as $livre): ?>
                                <tr>
                                    <td><?= htmlspecialchars($livre['title']) ?></td>
                                    <td><?= htmlspecialchars($livre['author']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($livre['disponibilite_date'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($livre['echeance_date'])) ?></td>
                                    <td>
                                        <a href="read_book.php?id=<?= $livre['book_id'] ?>" class="read-btn">Lire</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
