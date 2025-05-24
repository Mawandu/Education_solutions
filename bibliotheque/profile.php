<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Vérifier si l'utilisateur doit compléter son profil
$query = "SELECT configured FROM users WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user['configured'] && ($role === 'ecole' || $role === 'bibliotheque')) {
    if ($role === 'ecole') {
        header("Location: configure_ecole.php");
    } else {
        header("Location: configure_bibliotheque.php");
    }
    exit();
}

// Récupérer les informations de base
$query = "SELECT username, email FROM users WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    exit("Erreur : Utilisateur non trouvé.");
}

// Récupérer les informations spécifiques au rôle
$profile_info = [];
$message = '';

if ($role === 'ecole') {
    $query = "SELECT * FROM ecoles WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($role === 'bibliotheque') {
    $query = "SELECT * FROM bibliotheques WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'ecole' || $role === 'bibliotheque')) {
    if ($role === 'ecole') {
        $nom_ecole = $_POST['nom_ecole'];
        $adresse = $_POST['adresse'];
        $contact = $_POST['contact'];
        $pays = $_POST['pays'];
        $ville = $_POST['ville'];

        $query = "UPDATE ecoles SET nom_ecole = :nom, adresse = :adresse, contact = :contact, pays = :pays, ville = :ville WHERE user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'nom' => $nom_ecole,
            'adresse' => $adresse,
            'contact' => $contact,
            'pays' => $pays,
            'ville' => $ville,
            'user_id' => $user_id
        ]);
        $message = "Profil mis à jour avec succès!";
    } elseif ($role === 'bibliotheque') {
        $nom_bibliotheque = $_POST['nom_bibliotheque'];
        $adresse = $_POST['adresse'];
        $contact = $_POST['contact'];
        $pays = $_POST['pays'];
        $ville = $_POST['ville'];

        $query = "UPDATE bibliotheques SET nom_bibliotheque = :nom, adresse = :adresse, contact = :contact, pays = :pays, ville = :ville WHERE user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'nom' => $nom_bibliotheque,
            'adresse' => $adresse,
            'contact' => $contact,
            'pays' => $pays,
            'ville' => $ville,
            'user_id' => $user_id
        ]);
        $message = "Profil mis à jour avec succès!";
    }
    
    // Recharger les informations après mise à jour
    $query = "SELECT * FROM " . ($role === 'ecole' ? 'ecoles' : 'bibliotheques') . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $profile_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Images de fond par rôle
$background_images = [
    'admin' => '/bibliotheque/uploads/bibliotheque.jpg',
    'bibliotheque' => '/bibliotheque/uploads/couloir_bibliotheque.jpg',
    'ecole' => '/bibliotheque/uploads/garcon-assis-reception-bibliotheque_1048944-8084551.avif',
    'individu' => '/bibliotheque/uploads/livre-bibliotheque-manuel-ouvert_1150-5920.avif'
];

$background = $background_images[$role] ?? '/bibliotheque/uploads/bibliotheque.jpg';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo $background; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .profile-title {
            margin: 0;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-back {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background-color: #f44336;
            color: white;
        }
        
        .btn-logout:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
        }
        
        .profile-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .profile-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        
        .info-item {
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-item strong {
            display: inline-block;
            width: 150px;
            color: #555;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .success-message {
            color: #4CAF50;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
        }
        
        @media (max-width: 600px) {
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: space-between;
            }
            
            .btn {
                flex-grow: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Votre profil</h1>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-back">Retour à l'accueil</a>
                <a href="logout.php" class="btn btn-logout">Se déconnecter</a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="profile-section">
            <h2>Informations générales</h2>
            <div class="info-item">
                <strong>Nom d'utilisateur :</strong> <?php echo htmlspecialchars($user_info['username']); ?>
            </div>
            <div class="info-item">
                <strong>Email :</strong> <?php echo htmlspecialchars($user_info['email']); ?>
            </div>
        </div>
        
        <div class="profile-section">
            <h2>Profil <?php echo htmlspecialchars($role); ?></h2>
            
            <?php if ($role === 'admin'): ?>
                <p>Aucune information supplémentaire à modifier pour l'instant.</p>
            
            <?php elseif ($role === 'individu'): ?>
                <p>Vous êtes connecté en tant qu'individu.</p>
            
            <?php elseif ($role === 'ecole'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="nom_ecole">Nom de l'école</label>
                        <input type="text" id="nom_ecole" name="nom_ecole" value="<?php echo htmlspecialchars($profile_info['nom_ecole'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($profile_info['adresse'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact">Contact</label>
                        <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($profile_info['contact'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pays">Pays</label>
                        <input type="text" id="pays" name="pays" value="<?php echo htmlspecialchars($profile_info['pays'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($profile_info['ville'] ?? ''); ?>" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">Mettre à jour</button>
                </form>
            
            <?php elseif ($role === 'bibliotheque'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="nom_bibliotheque">Nom de la bibliothèque</label>
                        <input type="text" id="nom_bibliotheque" name="nom_bibliotheque" value="<?php echo htmlspecialchars($profile_info['nom_bibliotheque'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($profile_info['adresse'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact">Contact</label>
                        <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($profile_info['contact'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pays">Pays</label>
                        <input type="text" id="pays" name="pays" value="<?php echo htmlspecialchars($profile_info['pays'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($profile_info['ville'] ?? ''); ?>" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">Mettre à jour</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
