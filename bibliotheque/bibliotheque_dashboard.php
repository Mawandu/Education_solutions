<?php
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('memory_limit', '128M');
session_start();
require_once 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bibliotheque') {
    header("Location: login.php");
    exit();
}

try {
    // Récupérer l'utilisateur bibliothèque
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'bibliotheque'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Accès refusé : vous n'êtes pas une bibliothèque");
    }

    // Récupérer la bibliothèque associée
    $stmt = $pdo->prepare("SELECT * FROM libraries WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $library = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$library) {
        throw new Exception("Profil bibliothèque non trouvé");
    }

    $library_id = $library['id']; // ID de la bibliothèque pour l'ajout de livre

    // Récupérer les statistiques
    $stats = [
        'total_books' => 0,
        'available_books' => 0,
        'borrowed_books' => 0
    ];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE library_id = ?");
    $stmt->execute([$library['id']]);
    $stats['total_books'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE library_id = ? AND available = 1");
    $stmt->execute([$library['id']]);
    $stats['available_books'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE library_id = ? AND available = 0");
    $stmt->execute([$library['id']]);
    $stats['borrowed_books'] = $stmt->fetchColumn();




    // Traitement de l'ajout de livre
    if (isset($_POST['add_book'])) {
        error_log("Début du traitement de l'ajout de livre");
        $title = trim($_POST['title'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $error_message = '';
        $pdf_path = null;

        // Validation des champs
        if (empty($title) || empty($domain) || empty($author) || $duration <= 0) {
            $error_message = "Tous les champs obligatoires doivent être remplis.";
            error_log("Erreur de validation : $error_message");
        } else {
            error_log("Validation des champs OK : title=$title, domain=$domain, author=$author, duration=$duration");
            // Gestion du domaine personnalisé
            if ($domain === 'Autre' && !empty($_POST['custom_domain'])) {
                $custom_domain = trim($_POST['custom_domain']);
                $stmt = $pdo->prepare("SELECT id FROM domains WHERE name = :name");
                $stmt->execute(['name' => $custom_domain]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO domains (name) VALUES (:name)");
                    $stmt->execute(['name' => $custom_domain]);
                    error_log("Nouveau domaine ajouté : $custom_domain");
                }
                $domain = $custom_domain;
            }

            // Gérer l'upload du fichier PDF
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == UPLOAD_ERR_OK) {
                error_log("Fichier PDF détecté : " . $_FILES['pdf_file']['name']);
                // Vérifier le type MIME
                $file_type = mime_content_type($_FILES['pdf_file']['tmp_name']);
                if ($file_type !== 'application/pdf') {
                    $error_message = "Le fichier doit être un PDF valide.";
                    error_log("Erreur : Fichier non PDF, type=$file_type");
                } else {
                    $upload_dir = 'books/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $error_message = "Impossible de créer le dossier de stockage.";
                            error_log("Erreur : Impossible de créer le dossier $upload_dir");
                        }
                    }

                    if (!$error_message) {
                        $file_name = preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['pdf_file']['name']));
                        $pdf_path = $upload_dir . time() . '_' . $file_name;
                        error_log("Tentative d'upload vers : $pdf_path");

                        if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path)) {
                            $error_message = "Erreur lors de l'upload du fichier.";
                            error_log("Erreur : Échec de move_uploaded_file vers $pdf_path");
                        } else {
                            error_log("Fichier uploadé avec succès : $pdf_path");
                        }
                    }
                }
            } else {
                $error_message = "Veuillez sélectionner un fichier PDF valide. Code erreur : " . ($_FILES['pdf_file']['error'] ?? 'Aucun fichier');
                error_log("Erreur : Pas de fichier PDF ou erreur upload : " . ($_FILES['pdf_file']['error'] ?? 'Aucun fichier'));
            }

            // Insertion dans la base de données si pas d'erreur
            if (!$error_message && $pdf_path) {
                try {
                    error_log("Tentative d'insertion SQL : library_id=$library_id, title=$title, domain=$domain, author=$author, pdf_path=$pdf_path, duration=$duration");
                    $stmt = $pdo->prepare("INSERT INTO books (library_id, title, domain, author, pdf_path, file_path, available, borrow_duration) 
                                        VALUES (:library_id, :title, :domain, :author, :pdf_path, :file_path, 1, :duration)");
                    $stmt->execute([
                        'library_id' => $library_id,
                        'title' => $title,
                        'domain' => $domain,
                        'author' => $author,
                        'pdf_path' => $pdf_path,
                        'file_path' => $pdf_path,
                        'duration' => $duration
                    ]);

                    error_log("Livre ajouté avec succès : ID=" . $pdo->lastInsertId());
                    header("Location: manage_library.php?success=1");
                    exit;
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de l'ajout du livre : " . $e->getMessage();
                    error_log("Erreur SQL : " . $e->getMessage());
                }
            } else {
                error_log("Insertion non effectuée : error_message=$error_message, pdf_path=" . ($pdf_path ?? 'null'));
            }
        }

        // Afficher l'erreur avant de continuer (pour débogage)
        if ($error_message) {
            echo "<div style='color: red; text-align: center;'>$error_message</div>";
        }
    }
    

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

$pageTitle = "Tableau de bord - " . htmlspecialchars($library['name']);
require_once 'header.php';
?>

<style>
    /* Forcer le header à rester fixe */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 80px;
        z-index: 1000;
        background: #fff;
    }

    /* Forcer le footer à rester fixe */
    footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        z-index: 1000;
        background: #fff;
    }

    /* Ajuster le body */
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    /* Conteneur principal */
    .dashboard-container {
        max-width: 1200px;
        margin: 80px auto 60px;
        padding: 20px;
        height: calc(100vh - 140px);
        overflow-y: auto;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    .welcome-header {
        text-align: center;
    }
    
    .welcome-header h1 {
        color: var(--primary);
        margin-bottom: 10px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        width: 100%;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
        height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .stat-value {
        font-size: 2rem;
        color: var(--primary);
        margin: 10px 0;
        font-weight: bold;
    }
    
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        width: 100%;
        flex-grow: 1;
    }
    
    .action-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        text-align: center;
        transition: all 0.3s ease;
        color: inherit;
        text-decoration: none;
        border-top: 4px solid var(--primary);
        height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        cursor: pointer;
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .action-card h3 {
        color: var(--primary-dark);
        margin: 10px 0;
    }
    
    .action-icon {
        font-size: 2.5rem;
        color: var(--primary);
        margin-bottom: 15px;
    }

    /* Styles pour la boîte de dialogue */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        width: 90%;
        max-width: 500px;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        position: relative;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    
    .modal-header h2 {
        margin: 0;
        color: var(--primary);
    }
    
    .modal-close {
        font-size: 1.5rem;
        color: #666;
        cursor: pointer;
        background: none;
        border: none;
    }
    
    .modal-close:hover {
        color: var(--danger);
    }
    
    .modal-body form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .modal-body label {
        font-weight: 500;
        color: var(--text);
    }
    
    .modal-body input, .modal-body select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .modal-body .error {
        color: var(--danger);
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    .modal-body button[type="submit"] {
        background: var(--primary);
        color: white;
        padding: 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .modal-body button[type="submit"]:hover {
        background: var(--primary-dark);
    }
</style>

<main>
    <div class="dashboard-container">
        <div class="welcome-header">
            <h1>Tableau de bord - <?= htmlspecialchars($library['name']) ?></h1>
            <p>Bienvenue dans votre espace de gestion de bibliothèque</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_books'] ?></div>
                <div>Livres au catalogue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['available_books'] ?></div>
                <div>Livres disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['borrowed_books'] ?></div>
                <div>Livres empruntés</div>
            </div>
        </div>
        
        <div class="action-grid">
            <div class="action-card" onclick="openModal()">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3>Ajouter un livre</h3>
                <p>Ajouter un nouvel ouvrage à votre catalogue</p>
            </div>
            
            <a href="manage_library.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3>Gérer les livres</h3>
                <p>Modifier ou supprimer des livres existants</p>
            </a>
            
            <a href="library_stats.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Statistiques</h3>
                <p>Consulter les données d'utilisation</p>
            </a>
            
            <a href="library_settings.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Paramètres</h3>
                <p>Configurer votre bibliothèque</p>
            </a>
        </div>
    </div>
</main>

<!-- Boîte de dialogue pour ajouter un livre -->
<div id="addBookModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un livre</h2>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <label for="title">Titre *</label>
                <input type="text" id="title" name="title" required>
                
                <label for="domain">Domaine *</label>
                <select id="domain" name="domain" onchange="toggleCustomDomain(this)" required>
                    <option value="">-- Sélectionnez --</option>
                    <!-- Remplir dynamiquement les domaines existants -->
                    <?php
                    $domains = $pdo->query("SELECT name FROM domains")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($domains as $d) {
                        echo "<option value='" . htmlspecialchars($d) . "'>" . htmlspecialchars($d) . "</option>";
                    }
                    ?>
                    <option value="Autre">Autre</option>
                </select>
                
                <div id="customDomainField" style="display: none;">
                    <label for="custom_domain">Nouveau domaine</label>
                    <input type="text" id="custom_domain" name="custom_domain">
                </div>
                
                <label for="author">Auteur *</label>
                <input type="text" id="author" name="author" required>
                
                <label for="duration">Durée d'emprunt (jours) *</label>
                <input type="number" id="duration" name="duration" min="1" required>
                
                <label for="pdf_file">Fichier PDF *</label>
                <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" required>
                
                <?php if (isset($error_message) && $error_message): ?>
                    <div class="error"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                
                <button type="submit" name="add_book">Ajouter</button>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('addBookModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('addBookModal').style.display = 'none';
    }

    function toggleCustomDomain(select) {
        const customField = document.getElementById('customDomainField');
        customField.style.display = (select.value === 'Autre') ? 'block' : 'none';
    }

    // Fermer la modal si on clique en dehors
    window.onclick = function(event) {
        const modal = document.getElementById('addBookModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php require_once 'footer.php'; ?>