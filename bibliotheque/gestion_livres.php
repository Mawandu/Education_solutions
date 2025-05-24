<?php
session_start();
require_once 'db_connect.php';

// Vérifier que l'utilisateur est connecté et qu'il a le rôle "ecole"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ecole') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mise à jour automatique des enregistrements d'ecole_books expirés
$stmt = $pdo->prepare("UPDATE ecole_books SET status = 'expired' WHERE status = 'active' AND echeance_date < NOW()");
$stmt->execute();

// Récupérer l'école associée à l'utilisateur
$stmt = $pdo->prepare("SELECT id, nom_ecole FROM ecoles WHERE user_id = ?");
$stmt->execute([$user_id]);
$ecole = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ecole) {
    die("École non trouvée");
}

// Récupérer les livres empruntés non répartis
$stmt = $pdo->prepare("
    SELECT eb.id AS ecole_book_id, eb.book_id, b.title, b.author, eb.borrow_date, eb.echeance_date
    FROM ecole_books eb
    JOIN books b ON eb.book_id = b.id
    WHERE eb.ecole_id = ? AND eb.status = 'active'
      AND NOT EXISTS (SELECT 1 FROM ecole_lecture el WHERE el.ecole_book_id = eb.id)
");
$stmt->execute([$ecole['id']]);
$livres_empruntes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les livres répartis pour élèves
// Pour une distribution de groupe, user_id sera NULL et on utilisera la colonne destinataire_info
$stmt = $pdo->prepare("
    SELECT el.ecole_book_id, el.book_id, b.title, b.author, el.disponibilite_date, el.echeance_date,
       CASE 
           WHEN el.user_id IS NULL THEN SUBSTRING_INDEX(el.destinataire_info, ' - ', 1)
           ELSE ''
       END AS niveau,
       CASE 
           WHEN el.user_id IS NULL THEN SUBSTRING_INDEX(el.destinataire_info, ' - ', -1)
           ELSE CONCAT(e.prenom, ' ', e.nom, ' (', e.classe, ')')
       END AS destinataires,
       el.id AS lecture_id
    FROM ecole_lecture el
    JOIN books b ON el.book_id = b.id
    LEFT JOIN eleves e ON el.user_id = e.user_id
    WHERE el.ecole_id = ? AND el.role = 'eleve'
");
$stmt->execute([$ecole['id']]);
$livres_eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les livres répartis pour enseignants
// Pour une distribution de groupe, user_id sera NULL et destinataire_info contiendra "Tous"
$stmt = $pdo->prepare("
    SELECT el.id AS lecture_id, el.book_id, b.title, b.author, el.disponibilite_date, el.echeance_date,
       CASE 
           WHEN el.user_id IS NULL THEN el.destinataire_info
           ELSE CONCAT(e.prenom, ' ', e.nom)
       END AS destinataire
    FROM ecole_lecture el
    JOIN books b ON el.book_id = b.id
    LEFT JOIN enseignants e ON el.user_id = e.user_id
    WHERE el.ecole_id = ? AND el.role = 'enseignant'
");
$stmt->execute([$ecole['id']]);
$livres_enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des enseignants pour le formulaire
$stmt = $pdo->prepare("SELECT id, user_id, CONCAT(prenom, ' ', nom) AS nom_complet FROM enseignants WHERE ecole_id = ? AND user_id IS NOT NULL");
$stmt->execute([$ecole['id']]);
$enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'repartir' && isset($_POST['ecole_book_id'])) {
            $ecole_book_id = $_POST['ecole_book_id'];
            $destinataire_type = trim($_POST['destinataire_type'] ?? '');
            
            if (empty($destinataire_type)) {
                throw new Exception("Veuillez sélectionner un destinataire.");
            }
            
            // Récupérer le livre emprunté dans ecole_books
            $stmt = $pdo->prepare("SELECT book_id, echeance_date FROM ecole_books WHERE id = ? AND ecole_id = ? AND status = 'active'");
            $stmt->execute([$ecole_book_id, $ecole['id']]);
            $livre = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$livre) {
                throw new Exception("Livre emprunté introuvable dans la gestion.");
            }
            
            if ($destinataire_type === 'eleve' && isset($_POST['niveau']) && !empty($_POST['annees'])) {
                $niveau = $_POST['niveau']; // Par exemple "primaire" ou "secondaire"
                // Ici, on attend que $_POST['annees'] soit un tableau contenant des années (numériques)
                $annees = $_POST['annees'];
            
                if (!is_array($annees) || count($annees) === 0) {
                    throw new Exception("Veuillez sélectionner au moins une année.");
                }
                
                // Pour chaque année sélectionnée, on vérifie qu'il existe bien des élèves
                foreach ($annees as $annee_value) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM eleves e 
                        JOIN niveaux_options no ON e.niveau_option_id = no.id 
                        WHERE e.ecole_id = ? AND no.niveau = ? AND no.annee = ?
                    ");
                    $stmt->execute([$ecole['id'], $niveau, $annee_value]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception("Aucun élève trouvé pour le niveau " . $niveau . " et la " . $annee_value . "ème année.");
                    }
                }
                
                // Préparer la chaîne de description des années (par exemple "2" pour primaire ou "3,4" pour secondaire)
                $annees_str = implode(', ', $annees);
                $destInfo = $niveau . " - " . $annees_str;
            
                // Insertion en distribution groupée dans ecole_lecture pour les élèves
                $stmt = $pdo->prepare("
                    INSERT INTO ecole_lecture 
                     (ecole_id, ecole_book_id, user_id, book_id, disponibilite_date, echeance_date, role, destinataire_info)
                    VALUES 
                     (?, ?, NULL, ?, NOW(), ?, 'eleve', ?)
                ");
                $stmt->execute([
                    $ecole['id'], 
                    $ecole_book_id, 
                    $livre['book_id'], 
                    $livre['echeance_date'],
                    $destInfo
                ]);
                
                $_SESSION['message'] = "Livre réparti aux élèves avec succès";
            }
             elseif ($destinataire_type === 'enseignant') {
                $enseignant_id = trim($_POST['enseignant_id'] ?? '');
                if (empty($enseignant_id)) {
                    throw new Exception("Veuillez sélectionner un enseignant ou choisir 'Tous'.");
                }
                
                if ($enseignant_id === 'all') {
                    // Insertion unique pour une distribution de groupe aux enseignants
                    $stmt = $pdo->prepare("
                        INSERT INTO ecole_lecture 
                          (ecole_id, ecole_book_id, user_id, book_id, disponibilite_date, echeance_date, role, destinataire_info)
                        VALUES 
                          (?, ?, NULL, ?, NOW(), ?, 'enseignant', 'Tous')
                    ");
                    $stmt->execute([
                        $ecole['id'], 
                        $ecole_book_id, 
                        $livre['book_id'], 
                        $livre['echeance_date']
                    ]);
                    
                    $_SESSION['message'] = "Livre réparti à tous les enseignants";
                } else {
                    if (!ctype_digit($enseignant_id)) {
                        throw new Exception("Identifiant d'enseignant invalide.");
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO ecole_lecture 
                          (ecole_id, ecole_book_id, user_id, book_id, disponibilite_date, echeance_date, role)
                        VALUES 
                          (?, ?, ?, ?, NOW(), ?, 'enseignant')
                    ");
                    $stmt->execute([
                        $ecole['id'], 
                        $ecole_book_id, 
                        $enseignant_id,
                        $livre['book_id'], 
                        $livre['echeance_date']
                    ]);
                    
                    $_SESSION['message'] = "Livre réparti à l'enseignant";
                }
            } else {
                throw new Exception("Type de destinataire inconnu.");
            }
            
            header("Location: gestion_livres.php");
            exit();
        } elseif ($_POST['action'] === 'retirer' && isset($_POST['lecture_id'])) {
            $stmt = $pdo->prepare("DELETE FROM ecole_lecture WHERE id = ? AND ecole_id = ?");
            $stmt->execute([$_POST['lecture_id'], $ecole['id']]);
            
            $_SESSION['message'] = "Livre retiré avec succès";
            header("Location: gestion_livres.php");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erreur base de données : " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$pageTitle = "Gestion des livres - " . htmlspecialchars($ecole['nom_ecole']);
require_once 'header.php';
?>

<div class="main-container">
    <div class="welcome-header">
        <h1>Gestion des livres - <?= htmlspecialchars($ecole['nom_ecole']) ?></h1>
    </div>

    <div class="dashboard-content">
        <div class="content-wrapper">
            <a href="ecole_dashboard.php" class="back-button">← Retour</a>

            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php elseif (isset($_SESSION['message'])): ?>
                <div class="success"><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Section : Livres empruntés non répartis -->
            <div class="livres-table">
                <h3>Livres empruntés</h3>
                <?php if (empty($livres_empruntes)): ?>
                    <p>Aucun livre à répartir</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Date emprunt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres_empruntes as $livre): ?>
                            <tr>
                                <td><?= htmlspecialchars($livre['title']) ?></td>
                                <td><?= htmlspecialchars($livre['author']) ?></td>
                                <td><?= date('d/m/Y', strtotime($livre['borrow_date'])) ?></td>
                                <td>
                                    <form method="POST" class="transfer-form" novalidate>
                                        <input type="hidden" name="action" value="repartir">
                                        <input type="hidden" name="ecole_book_id" value="<?= $livre['ecole_book_id'] ?>">
                                        
                                        <select name="destinataire_type" required onchange="toggleOptions(this)">
                                            <option value="">Choisir...</option>
                                            <option value="eleve">Élèves</option>
                                            <option value="enseignant">Enseignants</option>
                                        </select>
                                        
                                        <div class="eleve-options" style="display:none">
                                            <select name="niveau" onchange="updateAnnees(this)">
                                                <option value="">Niveau</option>
                                                <option value="primaire">Primaire</option>
                                                <option value="secondaire">Secondaire</option>
                                            </select>
                                            <div id="annees-<?= $livre['ecole_book_id'] ?>" class="annees-selection"></div>
                                        </div>
                                        
                                        <div class="enseignant-options" style="display:none">
                                            <select name="enseignant_id">
                                                <option value="">Enseignant</option>
                                                <option value="all">Tous</option>
                                                <?php foreach ($enseignants as $ens): ?>
                                                <option value="<?= $ens['user_id'] ?>"><?= htmlspecialchars($ens['nom_complet']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" class="transfer-btn">Valider</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Section : Livres répartis pour élèves -->
            <div class="livres-table">
                <h3>Pour élèves</h3>
                <?php if (empty($livres_eleves)): ?>
                    <p>Aucun livre</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Niveau</th>
                                <th>Destinataire(s)</th>
                                <th>Date mise à disposition</th>
                                <th>Date échéance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres_eleves as $livre): ?>
                            <tr>
                                <td><?= htmlspecialchars($livre['title']) ?></td>
                                <td><?= htmlspecialchars($livre['author']) ?></td>
                                <td><?= htmlspecialchars($livre['niveau']) ?></td>
                                <td><?= htmlspecialchars($livre['destinataires']) ?></td>
                                <td><?= date('d/m/Y', strtotime($livre['disponibilite_date'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($livre['echeance_date'])) ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="retirer">
                                        <input type="hidden" name="lecture_id" value="<?= $livre['lecture_id'] ?>">
                                        <button type="submit" class="remove-btn">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Section : Livres répartis pour enseignants -->
            <div class="livres-table">
                <h3>Pour enseignants</h3>
                <?php if (empty($livres_enseignants)): ?>
                    <p>Aucun livre</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Destinataire(s)</th>
                                <th>Date mise à disposition</th>
                                <th>Date échéance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres_enseignants as $livre): ?>
                            <tr>
                                <td><?= htmlspecialchars($livre['title']) ?></td>
                                <td><?= htmlspecialchars($livre['author']) ?></td>
                                <td><?= htmlspecialchars($livre['destinataire']) ?></td>
                                <td><?= date('d/m/Y', strtotime($livre['disponibilite_date'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($livre['echeance_date'])) ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="retirer">
                                        <input type="hidden" name="lecture_id" value="<?= $livre['lecture_id'] ?>">
                                        <button type="submit" class="remove-btn">Retirer</button>
                                    </form>
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

<!-- Bloc JavaScript -->
<script>
function toggleOptions(select) {
    var form = select.closest('form');
    var eleveDiv = form.querySelector('.eleve-options');
    var enseignantDiv = form.querySelector('.enseignant-options');
    
    // Masquer les deux sections
    eleveDiv.style.display = 'none';
    enseignantDiv.style.display = 'none';
    
    // Désactiver l'attribut "required" sur tous les sélecteurs du formulaire
    var allSelects = form.querySelectorAll('select');
    allSelects.forEach(function(s) {
        s.required = false;
    });
    
    if (select.value === 'eleve') {
        eleveDiv.style.display = 'block';
        var niveauSelect = form.querySelector('select[name="niveau"]');
        if (niveauSelect) {
            niveauSelect.required = true;
        }
    } else if (select.value === 'enseignant') {
        enseignantDiv.style.display = 'block';
        var enseignantSelect = form.querySelector('select[name="enseignant_id"]');
        if (enseignantSelect) {
            enseignantSelect.required = true;
        }
    }
}

function updateAnnees(select) {
    var form = select.closest('form');
    var ecoleBookId = form.querySelector('input[name="ecole_book_id"]').value;
    var anneesDiv = document.getElementById('annees-' + ecoleBookId);
    anneesDiv.innerHTML = '';
    
    var niveau = select.value;
    var maxAnnees = (niveau === 'primaire') ? 8 : 4;
    
    for (var i = 1; i <= maxAnnees; i++) {
        var label = document.createElement('label');
        label.style.marginRight = '10px';
        label.innerHTML = '<input type="checkbox" name="annees[]" value="' + i + '"> Année ' + i;
        anneesDiv.appendChild(label);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var destSelects = document.querySelectorAll('select[name="destinataire_type"]');
    destSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            toggleOptions(select);
        });
        // Initialiser l'affichage
        toggleOptions(select);
    });
    
    var forms = document.querySelectorAll('form.transfer-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var destinataire = form.querySelector('select[name="destinataire_type"]').value;
            if (destinataire === 'eleve') {
                var checkboxes = form.querySelectorAll('input[name="annees[]"]:checked');
                if (checkboxes.length === 0) {
                    alert("Veuillez sélectionner au moins une année.");
                    e.preventDefault();
                }
            }
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>
