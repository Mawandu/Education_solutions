<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Bibliothèque Virtuelle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
    </style>
</head>
<body>
    <header class="minimal-header" style="
        background: #00796b;
        color: white;
        padding: 1rem;
        text-align: center;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
    ">
        <a href="index.php" style="
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        ">
            <i class="fas fa-arrow-left me-1"></i> Retour à l'accueil
        </a>
        <div class="logo" style="
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        ">
            <i class="fas fa-book"></i> Bibliothèque Virtuelle
        </div>
        <div style="width: 80px;"></div> <!-- Espace pour équilibrer la disposition -->
    </header>
