<?php
require_once 'db_connect.php';
session_start();

// Vérifier que l'utilisateur est connecté et a un rôle valide (ecole ou individu)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['ecole', 'individu'])) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = $_GET['book_id'] ?? '';
$role = $_GET['role'] ?? $_SESSION['role']; // Récupérer le rôle pour la redirection

// Déterminer la page de retour en fonction du rôle
$return_page = $role === 'ecole' ? 'manage_books.php' : 'borrow_book.php';

// Récupérer le chemin du fichier PDF à partir de la colonne pdf_path
$stmt = $pdo->prepare("
    SELECT b.pdf_path 
    FROM books b 
    JOIN borrows bor ON b.id = bor.book_id 
    WHERE b.id = :book_id 
    AND bor.user_id = :user_id 
    AND bor.return_date IS NULL
");
$stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

$base_dir = __DIR__ . '/';
$file_path = $base_dir . $book['pdf_path'];

if (!$book || !$book['pdf_path'] || !file_exists($file_path)) {
    http_response_code(404);
    echo "Livre non trouvé ou non accessible.";
    exit;
}

$file_url = "http://localhost/bibliotheque/stream_book.php?book_id=$book_id&user_id=$user_id";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Visionnage sécurisé</title>
    <style>
        body { 
            margin: 0; 
            -webkit-user-select: none; 
            user-select: none; 
        }
        #controls {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: #f0f0f0;
            padding: 5px;
            border-radius: 5px;
        }
        #pdf-viewer { 
            width: 100%; 
            overflow-y: auto; 
            height: 100vh; 
            padding-top: 50px; 
        }
        canvas { 
            display: block; 
            margin: 0 auto; 
        }
        @media print { 
            body { display: none !important; } 
        }
        button {
            padding: 5px 10px;
            margin: 0 5px;
            cursor: pointer;
        }
        .close-button {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 5px;
        }
        .close-button:hover {
            background-color: #da190b;
        }
    </style>
    <script type="module">
        import * as pdfjsLib from '/bibliotheque/pdfjs/build/pdf.mjs';

        window.pdfjsLib = pdfjsLib;
        pdfjsLib.GlobalWorkerOptions.workerSrc = '/bibliotheque/pdfjs/build/pdf.worker.mjs';

        // Bloquer clic droit
        document.addEventListener('contextmenu', (e) => e.preventDefault());

        // Bloquer impression et sauvegarde
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && (e.key === 'p' || e.key === 's')) e.preventDefault();
        });

        // Tenter de décourager les captures d'écran (limité, mais peut gêner)
        document.addEventListener('keyup', (e) => {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText(''); // Vider le presse-papiers
                alert('Les captures d’écran sont désactivées.');
            }
        });

        const url = <?php echo json_encode($file_url); ?>;
        let pdfDoc = null;
        let scale = 1.5;

        function renderPages() {
            const viewer = document.getElementById('pdf-viewer');
            viewer.innerHTML = '';
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                pdfDoc.getPage(pageNum).then(page => {
                    const canvas = document.createElement('canvas');
                    viewer.appendChild(canvas);
                    const context = canvas.getContext('2d');
                    const viewport = page.getViewport({ scale: scale });
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    page.render({ canvasContext: context, viewport: viewport });
                });
            }
        }

        const loadingTask = pdfjsLib.getDocument({ 
            url: url,
            withCredentials: true 
        });
        loadingTask.promise.then(pdf => {
            pdfDoc = pdf;
            renderPages();
        }).catch(error => {
            console.error('Erreur de chargement du PDF:', error);
            document.getElementById('pdf-viewer').innerHTML = 'Erreur de chargement du PDF : ' + (error.message || 'Vérifiez votre connexion ou les permissions.');
        });

        window.zoomIn = function() {
            scale += 0.25;
            if (pdfDoc) renderPages();
        };

        window.zoomOut = function() {
            if (scale > 0.5) {
                scale -= 0.25;
                if (pdfDoc) renderPages();
            }
        };

        window.closeReading = function() {
            window.location.href = '<?php echo $return_page; ?>';
        };
    </script>
</head>
<body>
    <div id="controls">
        <button onclick="zoomIn()">Zoom +</button>
        <button onclick="zoomOut()">Zoom -</button>
        <button onclick="closeReading()" class="close-button">Fermer la lecture</button>
    </div>
    <div id="pdf-viewer"></div>
</body>
</html>
