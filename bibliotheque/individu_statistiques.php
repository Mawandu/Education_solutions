<?php
session_start();
require_once 'db_connect.php';

// Vérification du rôle individu
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'individu') {
    header("Location: login.php");
    exit();
}

// Récupérer les statistiques de l'utilisateur
try {
    $user_id = $_SESSION['user_id'];

    // Statistiques générales
    $stats = [
        'emprunts_actifs' => 0,
        'retards' => 0,
        'livres_lus' => 0,
        'total_emprunts' => 0
    ];

    // Emprunts actifs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id = ? AND return_date IS NULL");
    $stmt->execute([$user_id]);
    $stats['emprunts_actifs'] = $stmt->fetchColumn();

    // Retards
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows 
                          WHERE user_id = ? 
                          AND return_date IS NULL 
                          AND borrow_date < DATE_SUB(NOW(), INTERVAL 14 DAY)");
    $stmt->execute([$user_id]);
    $stats['retards'] = $stmt->fetchColumn();

    // Livres lus (rendus)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id = ? AND return_date IS NOT NULL");
    $stmt->execute([$user_id]);
    $stats['livres_lus'] = $stmt->fetchColumn();

    // Total des emprunts (actifs + rendus)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrows WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_emprunts'] = $stmt->fetchColumn();

    // Liste des emprunts (pour tableau détaillé)
    $stmt = $pdo->prepare("
        SELECT b.title, b.author, br.borrow_date, br.return_date
        FROM borrows br
        JOIN books b ON br.book_id = b.id
        WHERE br.user_id = ?
        ORDER BY br.borrow_date DESC
    ");
    $stmt->execute([$user_id]);
    $emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer le nom de l'utilisateur pour le PDF
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $username = $stmt->fetchColumn();

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Gestion de l'export PDF
if (isset($_POST['export_pdf'])) {
    require_once 'fpdf/fpdf.php'; // Chemin vers FPDF dans ton dossier

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Statistiques de ' . $username, 0, 1, 'C');
    $pdf->Ln(10);

    // Statistiques générales
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Statistiques generales', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Emprunts actifs : " . $stats['emprunts_actifs'], 0, 1);
    $pdf->Cell(0, 10, "Retards : " . $stats['retards'], 0, 1);
    $pdf->Cell(0, 10, "Livres lus : " . $stats['livres_lus'], 0, 1);
    $pdf->Cell(0, 10, "Total des emprunts : " . $stats['total_emprunts'], 0, 1);
    $pdf->Ln(10);

    // Tableau des emprunts
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Historique des emprunts', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 10, 'Titre', 1);
    $pdf->Cell(40, 10, 'Auteur', 1);
    $pdf->Cell(40, 10, 'Date d\'emprunt', 1);
    $pdf->Cell(40, 10, 'Date de retour', 1);
    $pdf->Ln();

    foreach ($emprunts as $emprunt) {
        $pdf->Cell(60, 10, utf8_decode($emprunt['title']), 1);
        $pdf->Cell(40, 10, utf8_decode($emprunt['author']), 1);
        $pdf->Cell(40, 10, date('d/m/Y', strtotime($emprunt['borrow_date'])), 1);
        $pdf->Cell(40, 10, $emprunt['return_date'] ? date('d/m/Y', strtotime($emprunt['return_date'])) : 'Non rendu', 1);
        $pdf->Ln();
    }

    // Nom du fichier avec timestamp pour unicité
    $pdf_file = "statistiques_" . $username . "_" . date('Ymd_His') . ".pdf";
    $pdf->Output('F', $pdf_file);
    header("Location: $pdf_file"); // Redirige vers le fichier généré
    exit();
}

$pageTitle = "Statistiques - " . htmlspecialchars($username ?? 'Utilisateur');
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
        padding-bottom: 20px;
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
        margin: 0;
    }

    .welcome-header h1 {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 10px;
    }

    .content-wrapper {
        margin-top: 110px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        text-align: center;
        border-top: 4px solid var(--primary-color);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-value {
        font-size: 2.5rem;
        color: var(--primary-color);
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-card div {
        color: var(--text-color);
    }

    .history-table {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .history-table h3 {
        color: var(--text-color);
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    th {
        background-color: var(--primary-color);
        color: white;
    }

    tr:hover {
        background-color: rgba(0, 121, 107, 0.05);
    }

    .button-container {
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
    }

    .back-button, .export-button {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }

    .back-button {
        background-color: var(--primary-color);
        color: white;
    }

    .back-button:hover {
        background-color: var(--primary-light);
        color: white;
    }

    .export-button {
        background-color: #0288d1;
        color: white;
        border: none;
        cursor: pointer;
    }

    .export-button:hover {
        background-color: #039be5;
    }

    @media (max-width: 768px) {
        header.navbar {
            height: 60px;
        }
        
        .main-container {
            top: 60px;
        }

        .welcome-header {
            top: 60px;
        }

        .content-wrapper {
            margin-top: 100px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="main-container">
    <div class="welcome-header">
        <h1>Vos statistiques</h1>
        <p class="lead">Résumé de votre activité de lecture</p>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="button-container">
                <a href="user_dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
                <form method="post">
                    <button type="submit" name="export_pdf" class="export-button">
                        <i class="fas fa-file-pdf"></i> Exporter en PDF
                    </button>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['emprunts_actifs'] ?></div>
                    <div>Emprunts actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['retards'] ?></div>
                    <div>Retours en retard</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['livres_lus'] ?></div>
                    <div>Livres lus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_emprunts'] ?></div>
                    <div>Total des emprunts</div>
                </div>
            </div>

            <div class="history-table">
                <h3>Historique des emprunts</h3>
                <?php if (empty($emprunts)): ?>
                    <p>Vous n'avez aucun historique d'emprunt</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Date d'emprunt</th>
                                <th>Date de retour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emprunts as $emprunt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($emprunt['title']) ?></td>
                                    <td><?= htmlspecialchars($emprunt['author']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($emprunt['borrow_date'])) ?></td>
                                    <td><?= $emprunt['return_date'] ? date('d/m/Y', strtotime($emprunt['return_date'])) : 'Non rendu' ?></td>
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
