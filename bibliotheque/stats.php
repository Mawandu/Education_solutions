<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Gestion du thème
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Statistiques utilisateurs
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques écoles (basées sur la table users)
$stmt = $pdo->query("SELECT COUNT(*) as total_ecoles FROM users WHERE role = 'ecole'");
$total_ecoles = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) as validated_ecoles FROM users WHERE role = 'ecole' AND configured = 1");
$validated_ecoles = $stmt->fetchColumn();
$non_validated_ecoles = $total_ecoles - $validated_ecoles;

// Statistiques bibliothèques (basées sur la table users)
$stmt = $pdo->query("SELECT COUNT(*) as total_libraries FROM users WHERE role = 'bibliotheque'");
$total_libraries = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) as validated_libraries FROM users WHERE role = 'bibliotheque' AND configured = 1");
$validated_libraries = $stmt->fetchColumn();
$non_validated_libraries = $total_libraries - $validated_libraries;

// Statistiques emprunts
$stmt = $pdo->query("SELECT COUNT(*) as active_borrows FROM borrows WHERE return_date IS NULL");
$active_borrows = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) as past_borrows FROM borrows WHERE return_date IS NOT NULL");
$past_borrows = $stmt->fetchColumn();
$total_borrows = $active_borrows + $past_borrows;

// Générer un PDF si le bouton est cliqué
if (isset($_POST['generate_pdf'])) {
    if (!file_exists('fpdf/fpdf.php')) {
        die("Erreur : fpdf.php introuvable dans le dossier fpdf/.");
    }
    require_once 'fpdf/fpdf.php';

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Titre
    $pdf->Cell(0, 10, utf8_decode('Statistiques du système'), 0, 1, 'C');
    $pdf->Ln(10);

    // Utilisateurs
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Utilisateurs', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($user_stats as $stat) {
        $pdf->Cell(0, 10, utf8_decode('Rôle: ' . $stat['role'] . ' - Nombre: ' . $stat['count']), 0, 1);
    }
    $pdf->Ln(5);

    // Écoles
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('Écoles'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Total: ' . $total_ecoles, 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Validées: ' . $validated_ecoles), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Non validées: ' . $non_validated_ecoles), 0, 1);
    $pdf->Ln(5);

    // Bibliothèques
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('Bibliothèques'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Total: ' . $total_libraries, 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Validées: ' . $validated_libraries), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Non validées: ' . $non_validated_libraries), 0, 1);
    $pdf->Ln(5);

    // Emprunts
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Emprunts', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Actifs (non rendus): ' . $active_borrows, 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Passés (rendus): ' . $past_borrows), 0, 1);
    $pdf->Cell(0, 10, 'Total: ' . $total_borrows, 0, 1);

    // Sortie du PDF
    $pdf->Output('D', 'statistiques.pdf');
    exit();
}

$pageTitle = "Statistiques | Bibliothèque Virtuelle";
require_once 'header.php';
?>

<style>
    /* Forcer le header à rester fixe */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px; /* Ajustez selon la hauteur réelle de votre header */
        z-index: 1000;
        background: #fff; /* Ajoutez une couleur si nécessaire */
    }

    /* Forcer le footer à rester fixe */
    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px; /* Ajustez selon la hauteur réelle de votre footer */
        z-index: 1000;
        background: #fff; /* Ajoutez une couleur si nécessaire */
    }

    /* Ajuster le body pour éviter le chevauchement */
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; /* Empêche le défilement global */
    }

    /* Conteneur principal avec défilement */
    .stats-container {
        max-width: 1200px;
        margin: 80px auto 60px; /* Marges pour header (80px) et footer (60px) */
        padding: 2rem;
        height: calc(100vh - 140px); /* Hauteur totale moins header et footer */
        overflow-y: auto; /* Défilement vertical uniquement pour ce conteneur */
        box-sizing: border-box;
        background-color: var(--light);
    }

    /* Styles repris de votre code original */
    :root {
        --primary: #4CAF50;
        --secondary: #00796b;
        --dark: #2E7D32;
        --light: #f5f5f5;
        --text: #333;
        --card-bg: white;
        --danger: #f44336;
        --warning: #FFC107;
        --info: #2196F3;
    }

    [data-theme="dark"] {
        --primary: #4CAF50;
        --secondary: #005a4c;
        --dark: #1B5E20;
        --light: #2d2d2d;
        --text: #f5f5f5;
        --card-bg: #2d2d2d;
        --danger: #d32f2f;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 1.5rem;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: 8px;
        padding: 1.5rem;
        margin: 1rem 0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin: 1rem 0;
    }

    .btn {
        display: inline-block;
        padding: 0.6rem 1.2rem;
        background-color: var(--primary);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 500;
        margin: 0.5rem 0;
    }

    .btn:hover {
        opacity: 0.9;
    }

    .btn-export {
        background-color: var(--info);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        background: var(--card-bg);
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }

    th {
        background-color: var(--primary);
        color: white;
    }
</style>

<main>
    <div class="stats-container" data-theme="<?php echo $theme; ?>">
        <a href="admin_dashboard.php" class="btn">
            <i class="fas fa-arrow-left"></i> Retour
        </a>

        <button id="exportPdf" class="btn btn-export">
            <i class="fas fa-file-pdf"></i> Exporter en PDF
        </button>

        <div class="stats-grid">
            <!-- Carte Utilisateurs -->
            <div class="stat-card">
                <h2><i class="fas fa-users"></i> Utilisateurs</h2>
                <div class="chart-container">
                    <canvas id="userChart"></canvas>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rôle</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['role']); ?></td>
                                <td><?php echo htmlspecialchars($stat['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Carte Écoles -->
            <div class="stat-card">
                <h2><i class="fas fa-school"></i> Écoles</h2>
                <div class="chart-container">
                    <canvas id="ecolesChart"></canvas>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Statut</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total</td>
                            <td><?php echo $total_ecoles; ?></td>
                        </tr>
                        <tr>
                            <td>Validées</td>
                            <td><?php echo $validated_ecoles; ?></td>
                        </tr>
                        <tr>
                            <td>Non validées</td>
                            <td><?php echo $non_validated_ecoles; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Carte Bibliothèques -->
            <div class="stat-card">
                <h2><i class="fas fa-book"></i> Bibliothèques</h2>
                <div class="chart-container">
                    <canvas id="librariesChart"></canvas>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Statut</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total</td>
                            <td><?php echo $total_libraries; ?></td>
                        </tr>
                        <tr>
                            <td>Validées</td>
                            <td><?php echo $validated_libraries; ?></td>
                        </tr>
                        <tr>
                            <td>Non validées</td>
                            <td><?php echo $non_validated_libraries; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Carte Emprunts -->
            <div class="stat-card">
                <h2><i class="fas fa-exchange-alt"></i> Emprunts</h2>
                <div class="chart-container">
                    <canvas id="borrowsChart"></canvas>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Actifs</td>
                            <td><?php echo $active_borrows; ?></td>
                        </tr>
                        <tr>
                            <td>Passés</td>
                            <td><?php echo $past_borrows; ?></td>
                        </tr>
                        <tr>
                            <td>Total</td>
                            <td><?php echo $total_borrows; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Scripts nécessaires pour Chart.js et PDF -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { enabled: true, mode: 'index', intersect: false }
        }
    };

    new Chart(document.getElementById('userChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($user_stats as $stat) { echo "'" . $stat['role'] . "',"; } ?>],
            datasets: [{
                label: 'Utilisateurs',
                data: [<?php foreach ($user_stats as $stat) { echo $stat['count'] . ","; } ?>],
                backgroundColor: ['#4CAF50', '#2196F3', '#FFC107', '#9C27B0'],
                borderColor: ['#388E3C', '#1976D2', '#FFA000', '#7B1FA2'],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('ecolesChart'), {
        type: 'doughnut',
        data: {
            labels: ['Validées', 'Non validées'],
            datasets: [{
                data: [<?php echo $validated_ecoles; ?>, <?php echo $non_validated_ecoles; ?>],
                backgroundColor: ['#4CAF50', '#F44336'],
                borderColor: ['#388E3C', '#D32F2F'],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('librariesChart'), {
        type: 'doughnut',
        data: {
            labels: ['Validées', 'Non validées'],
            datasets: [{
                data: [<?php echo $validated_libraries; ?>, <?php echo $non_validated_libraries; ?>],
                backgroundColor: ['#4CAF50', '#F44336'],
                borderColor: ['#388E3C', '#D32F2F'],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('borrowsChart'), {
        type: 'bar',
        data: {
            labels: ['Actifs', 'Passés', 'Total'],
            datasets: [{
                label: 'Emprunts',
                data: [<?php echo $active_borrows; ?>, <?php echo $past_borrows; ?>, <?php echo $total_borrows; ?>],
                backgroundColor: ['#FFC107', '#2196F3', '#4CAF50'],
                borderColor: ['#FFA000', '#1976D2', '#388E3C'],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    document.getElementById('exportPdf').addEventListener('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        const element = document.querySelector('.stats-grid');
        
        html2canvas(element, { scale: 2, logging: false, useCORS: true }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = doc.internal.pageSize.getWidth() - 40;
            const imgHeight = canvas.height * imgWidth / canvas.width;
            
            doc.setFontSize(18);
            doc.text('Statistiques - Bibliothèque Virtuelle', 20, 30);
            doc.setFontSize(12);
            doc.text(new Date().toLocaleDateString(), 20, 50);
            
            doc.addImage(imgData, 'PNG', 20, 70, imgWidth, imgHeight);
            doc.save('statistiques.pdf');
        });
    });
</script>

<?php require_once 'footer.php'; ?>