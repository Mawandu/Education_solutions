<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email et mot de passe sont requis.";
    } else {
        $query = "SELECT id, username, password, role FROM users WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <style>
        /* Styles spécifiques à login.php */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../uploads/biblotheque.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-box {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 20px;
                margin: 0 15px;
            }
        }

        .login-box:hover {
            transform: translateY(-5px);
        }

        .login-box h1 {
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 28px;
        }

        .login-box form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .login-box label {
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            display: block;
        }

        .login-box input[type="email"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-size: 16px;
        }

        .login-box input[type="email"]:focus,
        .login-box input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }

        .login-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            margin-top: 10px;
        }

        .login-btn:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        .error-message {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }

        .register-link {
            margin-top: 25px;
            color: #7f8c8d;
            font-size: 15px;
        }

        .register-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Connexion</h1>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div>
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div>
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Se connecter</button>
            </form>
            <p class="register-link">Pas de compte ? <a href="register.php">Inscrivez-vous</a></p>
        </div>
    </div>
</body>
</html>
