<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['ecole', 'individu'])) {
    $_SESSION['error'] = "Vous n'avez pas accès à cette page.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Récupérer les filtres
$filtre_statut = $_GET['statut'] ?? '';

// Récupérer l'historique des emprunts
$query = "
    SELECT br.id, b.title, b.author, br.borrow_date, br.return_date
    FROM borrows br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = ?
";
$params = [$user_id];

if ($filtre_statut) {
    if ($filtre_statut === 'en_cours') {
        $query .= " AND br.return_date IS NULL AND DATE_ADD(br.borrow_date, INTERVAL 14 DAY) >= NOW()";
    } elseif ($filtre_statut === 'en_retard') {
        $query .= " AND br.return_date IS NULL AND DATE_ADD(br.borrow_date, INTERVAL 14 DAY) < NOW()";
    } elseif ($filtre_statut === 'retourne') {
        $query .= " AND br.return_date IS NOT NULL";
    }
}

$query .= " ORDER BY br.borrow_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$borrows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Calculer le statut pour chaque emprunt
foreach ($borrows as &$borrow) {
    if ($borrow['return_date']) {
        $borrow['statut'] = 'retourne';
    } elseif (strtotime($borrow['borrow_date']) + (14 * 24 * 60 * 60) < time()) {
        $borrow['statut'] = 'en_retard';
    } else {
        $borrow['statut'] = 'en_cours';
    }
}
unset($borrow);

// Gestion de l'exportation
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="historique_emprunts.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Titre', 'Auteur', 'Date d\'emprunt', 'Date de retour', 'Statut']);
        
        foreach ($borrows as $borrow) {
            fputcsv($output, [
                $borrow['id'],
                $borrow['title'],
                $borrow['author'],
                date('d/m/Y H:i', strtotime($borrow['borrow_date'])),
                $borrow['return_date'] ? date('d/m/Y H:i', strtotime($borrow['return_date'])) : '-',
                ucfirst(str_replace('_', ' ', $borrow['statut']))
            ]);
        }
        
        fclose($output);
        exit();
    } elseif ($format === 'pdf') {
        require_once 'fpdf/fpdf.php'; // Inclure FPDF
        
        class PDF extends FPDF {
            function Header() {
                $this->SetFont('Arial', 'B', 16);
                $this->Cell(0, 10, 'Historique des emprunts', 0, 1, 'C');
                $this->Ln(10);
                
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(20, 10, 'ID', 1);
                $this->Cell(50, 10, 'Titre', 1);
                $this->Cell(40, 10, 'Auteur', 1);
                $this->Cell(30, 10, "Date d'emprunt", 1);
                $this->Cell(30, 10, 'Date de retour', 1);
                $this->Cell(30, 10, 'Statut', 1);
                $this->Ln();
            }
            
            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }
        }
        
        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 10);
        
        foreach ($borrows as $borrow) {
            $pdf->Cell(20, 10, $borrow['id'], 1);
            $pdf->Cell(50, 10, $borrow['title'], 1);
            $pdf->Cell(40, 10, $borrow['author'], 1);
            $pdf->Cell(30, 10, date('d/m/Y H:i', strtotime($borrow['borrow_date'])), 1);
            $pdf->Cell(30, 10, $borrow['return_date'] ? date('d/m/Y H:i', strtotime($borrow['return_date'])) : '-', 1);
            $pdf->Cell(30, 10, ucfirst(str_replace('_', ' ', $borrow['statut'])), 1);
            $pdf->Ln();
        }
        
        $pdf->Output('D', 'historique_emprunts.pdf');
        exit();
    }
}

$pageTitle = "Historique des emprunts";
require_once 'header.php'; // Utiliser le header complet (avec navigation)
?>

<style>
    :root {
        --primary-color: #00796b;
        --primary-light: #4db6ac;
        --background: #f8f9fa;
        --text-color: #333;
        --card-bg: #fff;
        --border-color: #dee2e6;
        --badge-bg: #e3f2fd;
        --badge-text: #1976d2;
        --danger: #dc3545;
        --success: #28a745;
        --warning: #ffc107;
    }

    [data-theme="dark"] {
        --background: #2d2d2d;
        --text-color: #f5f5f5;
        --card-bg: #3a3a3a;
        --border-color: #555;
        --badge-bg: #1976d2;
        --badge-text: #e3f2fd;
        --danger: #dc3545;
        --success: #28a745;
        --warning: #ffc107;
    }

    body {
        background-color: var(--background);
        color: var(--text-color);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
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

    .main-container {
        position: absolute;
        top: 70px;
        bottom: 50px;
        left: 0;
        right: 0;
        overflow-y: auto;
        padding: 0 20px 20px 20px; /* Réduire le padding-top à 0 */
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
    }

    .page-header {
        position: sticky;
        top: 70px; /* Juste en dessous du header principal */
        background: var(--background);
        z-index: 900;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0; /* Supprimer toute marge */
        padding: 10px 0; /* Réduire le padding vertical */
        border-bottom: 2px solid var(--border-color);
    }

    .page-header h1 {
        color: var(--primary-color);
        font-weight: 600;
        font-size: 2rem;
        margin: 0;
    }

    .filters-card {
        position: sticky;
        top: 122px; /* 70px (navbar) + 52px (hauteur de .page-header) */
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin: 0 0 20px 0; /* Supprimer la marge supérieure */
        z-index: 800;
    }

    .filters-card .card-header {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .filters-card .card-body {
        padding: 20px;
    }

    .history-card {
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .history-card .card-header {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .history-card .card-body {
        padding: 20px;
    }

    .table {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 5px;
    }

    .table th {
        background: #f8f9fa;
        color: var(--text-color);
        font-weight: 600;
    }

    [data-theme="dark"] .table th {
        background: #444;
    }

    .table td {
        vertical-align: middle;
    }

    .table-hover tbody tr:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    [data-theme="dark"] .table-hover tbody tr:hover {
        background: #444;
    }

    .statut-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.9rem;
    }

    .statut-badge.en_cours {
        background-color: var(--badge-bg);
        color: var(--badge-text);
    }

    .statut-badge.retourne {
        background-color: rgba(40, 167, 69, 0.1);
        color: var(--success);
    }

    .statut-badge.en_retard {
        background-color: rgba(220, 53, 69, 0.1);
        color: var(--danger);
    }

    .form-label {
        font-weight: 500;
        color: var(--text-color);
    }

    .form-select {
        border-radius: 5px;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
    }

    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background-color: var(--primary-light);
    }

    .btn-outline-primary {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }

    .btn-export {
        margin-left: 10px;
    }
</style>

<div class="main-container">
    <div class="dashboard-content">
        <div class="page-header">
            <h1><i class="fas fa-history me-2"></i>Historique des emprunts</h1>
            <div>
                <a href="borrow_history.php?export=csv" class="btn btn-outline-primary btn-export">
                    <i class="fas fa-file-csv me-1"></i> Exporter en CSV
                </a>
                <a href="borrow_history.php?export=pdf" class="btn btn-outline-primary btn-export">
                    <i class="fas fa-file-pdf me-1"></i> Exporter en PDF
                </a>
                <a href="<?= $role === 'ecole' ? 'ecole_dashboard.php' : 'user_dashboard.php' ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Retour au tableau de bord
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4 filters-card">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="fas fa-filter me-1"></i>Filtrer les emprunts</h2>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="en_cours" <?= $filtre_statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="retourne" <?= $filtre_statut === 'retourne' ? 'selected' : '' ?>>Retourné</option>
                            <option value="en_retard" <?= $filtre_statut === 'en_retard' ? 'selected' : '' ?>>En retard</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Appliquer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm history-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><i class="fas fa-list me-1"></i>Liste des emprunts</h2>
                <span class="badge bg-light text-dark"><?= count($borrows) ?> emprunt(s)</span>
            </div>
            <div class="card-body">
                <?php if (count($borrows) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">ID</th>
                                    <th style="width: 20%;">Titre</th>
                                    <th style="width: 20%;">Auteur</th>
                                    <th style="width: 20%;">Date d'emprunt</th>
                                    <th style="width: 20%;">Date de retour</th>
                                    <th style="width: 10%;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrows as $borrow): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($borrow['id']) ?></td>
                                        <td><?= htmlspecialchars($borrow['title']) ?></td>
                                        <td><?= htmlspecialchars($borrow['author']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($borrow['borrow_date'])) ?></td>
                                        <td>
                                            <?= $borrow['return_date'] 
                                                ? date('d/m/Y H:i', strtotime($borrow['return_date'])) 
                                                : '-' ?>
                                        </td>
                                        <td>
                                            <span class="statut-badge <?= htmlspecialchars($borrow['statut']) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $borrow['statut'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun emprunt trouvé</h5>
                        <p class="text-muted"><?= $filtre_statut ? 'Modifiez vos critères de recherche' : 'Vous n\'avez pas encore effectué d\'emprunt' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>