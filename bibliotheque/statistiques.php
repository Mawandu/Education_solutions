<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id, nom_ecole FROM ecoles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$ecole = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ecole) {
    die("École non trouvée");
}

$ecole_id = $ecole['id'];

// Initialiser les statistiques
$stats = [
    'total_eleves' => 0,
    'total_enseignants' => 0,
    'emprunts_actifs' => 0,
    'retards' => 0,
    'par_niveau' => []
];

// Fonction pour exécuter les requêtes en toute sécurité
function safeFetchColumn($pdo, $query, $params = []) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return $result !== false ? (int)$result : 0;
}

try {
    // Compter les élèves
    $stats['total_eleves'] = safeFetchColumn($pdo, "SELECT COUNT(*) FROM eleves WHERE ecole_id = ?", [$ecole_id]);

    // Compter les enseignants
    $stats['total_enseignants'] = safeFetchColumn($pdo, "SELECT COUNT(*) FROM enseignants WHERE ecole_id = ?", [$ecole_id]);

    // Emprunts actifs
    $stats['emprunts_actifs'] = safeFetchColumn($pdo,
    "SELECT COUNT(*) FROM borrows 
     WHERE (user_id = ? 
         OR user_id IN (SELECT user_id FROM eleves WHERE ecole_id = ?) 
         OR user_id IN (SELECT user_id FROM enseignants WHERE ecole_id = ?)) 
     AND return_date IS NULL",
    [$_SESSION['user_id'], $ecole_id, $ecole_id]);
    // Retards
    $stats['retards'] = safeFetchColumn($pdo,
        "SELECT COUNT(*) FROM borrows 
         WHERE (user_id IN (SELECT user_id FROM eleves WHERE ecole_id = ?) 
             OR user_id IN (SELECT user_id FROM enseignants WHERE ecole_id = ?)) 
         AND return_date IS NULL 
         AND borrow_date < DATE_SUB(NOW(), INTERVAL 14 DAY)",
        [$ecole_id, $ecole_id]);

    // Statistiques par niveau
    $stmt = $pdo->prepare("SELECT DISTINCT niveau FROM eleves WHERE ecole_id = ?");
    $stmt->execute([$ecole_id]);
    $niveaux = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($niveaux as $niveau) {
        $stats['par_niveau'][$niveau] = [
            'eleves' => safeFetchColumn($pdo, "SELECT COUNT(*) FROM eleves WHERE ecole_id = ? AND niveau = ?", [$ecole_id, $niveau]),
            'emprunts' => safeFetchColumn($pdo,
                "SELECT COUNT(*) FROM borrows b 
                 JOIN eleves e ON b.user_id = e.user_id 
                 WHERE e.ecole_id = ? AND e.niveau = ? AND b.return_date IS NULL",
                [$ecole_id, $niveau])
        ];
    }

    // Activité récente
    $stmt = $pdo->prepare("
        SELECT b.borrow_date, bk.title, 
               CONCAT(COALESCE(e.prenom, en.prenom), ' ', COALESCE(e.nom, en.nom)) AS nom_emprunteur,
               CASE WHEN e.id IS NOT NULL THEN 'eleve' ELSE 'enseignant' END AS role
        FROM borrows b
        JOIN books bk ON b.book_id = bk.id
        LEFT JOIN eleves e ON b.user_id = e.user_id AND e.ecole_id = ?
        LEFT JOIN enseignants en ON b.user_id = en.user_id AND en.ecole_id = ?
        WHERE (e.ecole_id = ? OR en.ecole_id = ?)
        ORDER BY b.borrow_date DESC
        LIMIT 5");
    $stmt->execute([$ecole_id, $ecole_id, $ecole_id, $ecole_id]);
    $activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Préparer les données pour le graphique
$chart_niveaux = json_encode(array_keys($stats['par_niveau']));
$chart_eleves = json_encode(array_column($stats['par_niveau'], 'eleves'));

// Export PDF
if (isset($_POST['export_pdf'])) {
    require_once 'fpdf/fpdf.php';
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Statistiques de ' . $ecole['nom_ecole'], 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Statistiques generales', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Total eleves : " . $stats['total_eleves'], 0, 1);
    $pdf->Cell(0, 10, "Total enseignants : " . $stats['total_enseignants'], 0, 1);
    $pdf->Cell(0, 10, "Emprunts actifs : " . $stats['emprunts_actifs'], 0, 1);
    $pdf->Cell(0, 10, "Retards : " . $stats['retards'], 0, 1);
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Details par niveau', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, 'Niveau', 1);
    $pdf->Cell(50, 10, 'Eleves', 1);
    $pdf->Cell(50, 10, 'Emprunts actifs', 1);
    $pdf->Ln();
    foreach ($stats['par_niveau'] as $niveau => $data) {
        $pdf->Cell(50, 10, utf8_decode($niveau), 1);
        $pdf->Cell(50, 10, $data['eleves'], 1);
        $pdf->Cell(50, 10, $data['emprunts'], 1);
        $pdf->Ln();
    }

    $pdf_file = "statistiques_" . $ecole['nom_ecole'] . "_" . date('Ymd_His') . ".pdf";
    $pdf->Output('F', $pdf_file);
    header("Location: $pdf_file");
    exit();
}

$pageTitle = "Statistiques - " . htmlspecialchars($ecole['nom_ecole']);
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

    .button-container {
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
    }

    .back-button, .export-button {
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
        border-left: 4px solid var(--primary-color);
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

    .chart-container {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        margin-bottom: 40px;
        height: 400px;
    }

    .activity-list {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .activity-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-info strong {
        color: var(--text-color);
    }

    .activity-meta {
        font-size: 0.9rem;
        color: var(--meta-text);
    }

    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-eleve {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .badge-enseignant {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .chart-container {
            height: 300px;
        }
    }
</style>

<div class="main-container">
    <div class="welcome-header">
        <h1>Statistiques de <?= htmlspecialchars($ecole['nom_ecole']) ?></h1>
        <p class="lead">Aperçu de l'activité de votre établissement</p>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <div class="button-container">
                <a href="ecole_dashboard.php" class="back-button">
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
                    <div class="stat-value"><?= $stats['total_eleves'] ?></div>
                    <div>Élèves</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_enseignants'] ?></div>
                    <div>Enseignants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['emprunts_actifs'] ?></div>
                    <div>Emprunts actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['retards'] ?></div>
                    <div>Retards</div>
                </div>
            </div>

            <div class="chart-container">
                <h3>Répartition des élèves par niveau</h3>
                <canvas id="elevesChart"></canvas>
            </div>

            <div class="activity-list">
                <h3>Activité récente</h3>
                <?php if (empty($activites)): ?>
                    <p>Aucune activité récente</p>
                <?php else: ?>
                    <?php foreach ($activites as $activite): ?>
                        <div class="activity-item">
                            <div class="activity-info">
                                <strong><?= htmlspecialchars($activite['title']) ?></strong>
                                <div class="activity-meta">
                                    <?= htmlspecialchars($activite['nom_emprunteur']) ?>
                                    <span class="badge <?= $activite['role'] === 'eleve' ? 'badge-eleve' : 'badge-enseignant' ?>">
                                        <?= $activite['role'] === 'eleve' ? 'Élève' : 'Enseignant' ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <?= date('d/m/Y', strtotime($activite['borrow_date'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const niveaux = <?= $chart_niveaux ?>;
    const elevesData = <?= $chart_eleves ?>;

    new Chart(document.getElementById('elevesChart'), {
        type: 'bar',
        data: {
            labels: niveaux.map(n => n.charAt(0).toUpperCase() + n.slice(1)),
            datasets: [{
                label: 'Élèves',
                data: elevesData,
                backgroundColor: 'rgba(0, 121, 107, 0.7)',
                borderColor: 'rgba(0, 121, 107, 1)',
                borderWidth: 1,
                borderRadius: 5,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' élèves';
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>