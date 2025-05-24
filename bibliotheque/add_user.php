<?php
session_start();
require_once 'db_connect.php';

// Seuls les admins peuvent ajouter des utilisateurs
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$rolesAllowed = ['admin', 'ecole', 'bibliotheque']; // Rôles autorisés

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        // Insertion de l'utilisateur
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, configured) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $email, $password, $role]);
        
        $userId = $pdo->lastInsertId();
        
        // Si c'est une école ou bibliothèque, créer l'entité correspondante
        if ($role === 'ecole') {
            $nomEcole = trim($_POST['nom_ecole']);
            $stmt = $pdo->prepare("INSERT INTO ecoles (user_id, nom_ecole, validated) VALUES (?, ?, 1)");
            $stmt->execute([$userId, $nomEcole]);
            $entityId = $pdo->lastInsertId();
            
            // Mettre à jour l'entity_id dans users
            $pdo->prepare("UPDATE users SET entity_id = ? WHERE id = ?")->execute([$entityId, $userId]);
        } 
        elseif ($role === 'bibliotheque') {
            $nomBiblio = trim($_POST['nom_bibliotheque']);
            $stmt = $pdo->prepare("INSERT INTO bibliotheques (user_id, nom_bibliotheque, valide) VALUES (?, ?, 1)");
            $stmt->execute([$userId, $nomBiblio]);
            $entityId = $pdo->lastInsertId();
            
            // Mettre à jour l'entity_id dans users
            $pdo->prepare("UPDATE users SET entity_id = ? WHERE id = ?")->execute([$entityId, $userId]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Utilisateur créé avec succès!";
        header("Location: manage_users.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = "Ajouter un Utilisateur";
require_once 'header.php';
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 100px auto 80px;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
    }
    
    .form-title {
        color: var(--primary);
        margin-top: 0;
        margin-bottom: 1.5rem;
        font-size: 1.8rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-control {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }
    
    .role-selector {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .role-option {
        flex: 1;
        border: 2px solid #eee;
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .role-option.selected {
        border-color: var(--primary);
        background: rgba(76, 175, 80, 0.1);
    }
    
    .role-option input {
        display: none;
    }
    
    .role-specific {
        display: none;
    }
    
    .btn-submit {
        background: var(--primary);
        color: white;
        padding: 0.8rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .btn-submit:hover {
        background: var(--primary-dark);
    }
</style>

<div class="form-container">
    <h1 class="form-title"><i class="fas fa-user-plus"></i> Ajouter un Utilisateur</h1>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Nom complet</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        
        <div class="form-group">
            <label>Rôle</label>
            <div class="role-selector">
                <?php foreach ($rolesAllowed as $role): ?>
                <label class="role-option">
                    <input type="radio" name="role" value="<?= $role ?>" required>
                    <strong><?= ucfirst($role) ?></strong>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Champs spécifiques aux écoles -->
        <div class="role-specific" id="ecole-fields">
            <div class="form-group">
                <label>Nom de l'école</label>
                <input type="text" name="nom_ecole" class="form-control">
            </div>
        </div>
        
        <!-- Champs spécifiques aux bibliothèques -->
        <div class="role-specific" id="bibliotheque-fields">
            <div class="form-group">
                <label>Nom de la bibliothèque</label>
                <input type="text" name="nom_bibliotheque" class="form-control">
            </div>
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="fas fa-save"></i> Créer l'utilisateur
        </button>
    </form>
</div>

<script>
    // Gestion de la sélection des rôles
    document.querySelectorAll('.role-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
            
            // Afficher les champs spécifiques
            document.querySelectorAll('.role-specific').forEach(field => field.style.display = 'none');
            
            const selectedRole = this.querySelector('input').value;
            if (selectedRole === 'ecole') {
                document.getElementById('ecole-fields').style.display = 'block';
            } else if (selectedRole === 'bibliotheque') {
                document.getElementById('bibliotheque-fields').style.display = 'block';
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>
