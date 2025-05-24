<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et que son rôle est autorisé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['individu', 'eleve', 'enseignant'])) {
    header('HTTP/1.1 403 Forbidden');
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// L'ID du livre est passé via GET "id"
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo "ID de livre invalide.";
    exit;
}
$book_id = (int)$_GET['id'];

// Déterminer la page de redirection en fonction du rôle
switch($role) {
    case 'individu':
        $redirect_page = 'user_dashboard.php';
        break;
    case 'eleve':
        $redirect_page = 'eleve_dashboard.php';
        break;
    case 'enseignant':
        $redirect_page = 'enseignant_dashboard.php';
        break;
    default:
        $redirect_page = 'index.php';
        break;
}

try {
    if ($role === 'individu') {
        // Pour individu, on utilise la table borrows
        $stmt = $pdo->prepare("
            SELECT b.pdf_path 
            FROM books b
            JOIN borrows bor ON b.id = bor.book_id
            WHERE bor.user_id = :user_id 
              AND bor.book_id = :book_id 
              AND bor.return_date IS NULL
        ");
        $params = ['user_id' => $user_id, 'book_id' => $book_id];
    } else {
        // Pour eleve et enseignant, on vérifie dans ecole_lecture
        // On accepte l'enregistrement si c'est individuel (user_id = :user_id)
        // OU groupé (user_id IS NULL)
        $stmt = $pdo->prepare("
            SELECT b.pdf_path 
            FROM books b
            JOIN ecole_lecture el ON b.id = el.book_id
            WHERE el.book_id = :book_id 
              AND (el.user_id = :user_id OR el.user_id IS NULL)
        ");
        $params = ['book_id' => $book_id, 'user_id' => $user_id];
    }
    $stmt->execute($params);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book || empty($book['pdf_path'])) {
        header('HTTP/1.1 404 Not Found');
        echo "Vous n'avez pas accès à ce livre ou le fichier PDF est introuvable.";
        exit;
    }

    $file_path = __DIR__ . '/' . $book['pdf_path'];
    if (!file_exists($file_path)) {
        header('HTTP/1.1 404 Not Found');
        echo "Fichier PDF introuvable sur le serveur.";
        exit;
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Erreur lors de l'accès au livre : " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Lire un livre</title>
    <style>
        body {
            font-family: Arial, sans-serif; margin: 0; padding: 0; overflow: hidden;
            -webkit-user-select: none; user-select: none;
        }
        .viewer-container {
            height: 100vh; width: 100vw;
            display: flex; flex-direction: column; align-items: center;
        }
        .controls {
            position: fixed; top: 0; width: 100%; text-align: center;
            background: #f0f0f0; padding: 10px; z-index: 1000;
        }
        .controls button {
            padding: 8px 16px; margin: 0 5px;
            border: none; border-radius: 5px; cursor: pointer;
        }
        .zoom-btn { background-color: #4CAF50; color: white; }
        .zoom-btn:hover { background-color: #45a049; }
        .close-btn { background-color: #f44336; color: white; }
        .close-btn:hover { background-color: #da190b; }
        #pdfViewer {
            margin-top: 60px; width: 100%;
            height: calc(100vh - 60px);
            overflow-y: auto; display: flex; flex-direction: column; align-items: center;
        }
        .page-container { margin-bottom: 10px; box-shadow: 0 0 5px rgba(0,0,0,0.3); }
        .error-message { color: red; text-align: center; margin-top: 100px; }
        @media print { body { display: none !important; } }
    </style>
    <!-- Chargement de PDF.js via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
    </script>
</head>
<body>
    <div class="viewer-container">
        <div class="controls">
            <button class="zoom-btn" onclick="zoomIn()">Zoom +</button>
            <button class="zoom-btn" onclick="zoomOut()">Zoom -</button>
            <button class="close-btn" onclick="window.location.href='<?= $redirect_page; ?>'">Quitter la lecture</button>
        </div>
        <div id="pdfViewer"></div>
    </div>
    <script>
        // Désactiver clic droit et certains raccourcis
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && (e.key.toLowerCase() === 'p' || e.key.toLowerCase() === 's')) {
                e.preventDefault();
                alert("L'impression et la sauvegarde sont désactivées.");
            }
        });

        let pdfDoc = null;
        let scale = 1.5;

        async function loadPdf() {
            try {
                const pdfUrl = 'get_pdf.php?book_id=<?= $book_id; ?>';
                pdfDoc = await pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise;
                renderPages();
            } catch (error) {
                console.error('Erreur de chargement du PDF:', error);
                document.getElementById('pdfViewer').innerHTML = `<div class="error-message">Erreur lors du chargement du PDF: ${error.message}</div>`;
            }
        }

        async function renderPages() {
            const viewer = document.getElementById('pdfViewer');
            viewer.innerHTML = '';
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                try {
                    const page = await pdfDoc.getPage(pageNum);
                    const viewport = page.getViewport({ scale: scale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    const renderContext = { canvasContext: context, viewport: viewport };
                    await page.render(renderContext).promise;
                    const pageContainer = document.createElement('div');
                    pageContainer.className = 'page-container';
                    pageContainer.appendChild(canvas);
                    viewer.appendChild(pageContainer);
                } catch (error) {
                    console.error(`Erreur lors du rendu de la page ${pageNum}:`, error);
                }
            }
        }

        function zoomIn() {
            if (scale < 3.0) { scale += 0.25; if (pdfDoc) renderPages(); }
        }

        function zoomOut() {
            if (scale > 0.5) { scale -= 0.25; if (pdfDoc) renderPages(); }
        }

        window.onload = loadPdf;
    </script>
</body>
</html>
