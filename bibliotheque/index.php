<?php
session_start();
require_once 'db_connect.php';

$stats = [
    'individus' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'individu'")->fetchColumn(),
    'ecoles' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ecole'")->fetchColumn(),
    'bibliotheques' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bibliotheque'")->fetchColumn(),
    'enseignants' => $pdo ->query("SELECT COUNT(*) FROM users WHERE role = 'enseignant'")->fetchColumn(),
    'eleves' => $pdo->query("SELECT COUNT(*) FROM users WHERE role ='eleve'")->fetchColumn(),
    'visites' => $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn(),
    'connectes' => $pdo->query("SELECT COUNT(*) FROM user_activity WHERE last_activity >= (NOW() - INTERVAL 10 MINUTE)")->fetchColumn()
];

$pageTitle = "Bibliothèque Virtuelle";
require_once 'header.php';
?>

<section style="
    background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('/bibliotheque/uploads/biblio.jpg');
    background-size: cover;
    background-position: center;
    color: white;
    width: 100%;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1rem;
    text-align: center;
">
    <h1 style="font-size: 2.2rem; margin-bottom: 0.8rem;">Bienvenue sur la Bibliothèque Virtuelle</h1>
    <p style="font-size: 1.1rem; margin-bottom: 1.5rem;">Plateforme collaborative pour les écoles et bibliothèques</p>
    
    <div style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.8rem;
        width: 100%;
        max-width: 800px;
        padding: 0.5rem;
    ">
        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['individus'] ?></div>
            <div>Individus inscrits</div>
        </div>
        
        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['enseignants'] ?></div>
            <div>Enseignants inscrits</div>
        </div>

        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['eleves'] ?></div>
            <div>Élèves inscrits</div>
        </div>

        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['ecoles'] ?></div>
            <div>Écoles partenaires</div>
        </div>
        
        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['bibliotheques'] ?></div>
            <div>Bibliothèques</div>
        </div>
        
        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['visites'] ?></div>
            <div>Visites</div>
        </div>
        
        <div style="background-color: rgba(255,255,255,0.1); padding: 1.2rem; border-radius: 6px; backdrop-filter: blur(4px);">
            <div style="font-size: 2rem; font-weight: bold;"><?= $stats['connectes'] ?></div>
            <div>Connectés</div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
