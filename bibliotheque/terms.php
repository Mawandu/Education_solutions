<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = "Conditions d'utilisation";
$theme = $_COOKIE['theme'] ?? 'light';
require_once 'header.php';
?>

<style>
    .terms-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background-color: var(--light);
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .terms-title {
        color: var(--primary);
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--primary);
    }
    
    .terms-section {
        margin-bottom: 2rem;
    }
    
    .section-title {
        color: var(--secondary);
        margin: 1.5rem 0 1rem;
    }
    
    .terms-list {
        padding-left: 1.5rem;
        margin: 1rem 0;
    }
    
    .highlight {
        background-color: rgba(76, 175, 80, 0.1);
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
    }
    
    @media (max-width: 768px) {
        .terms-container {
            margin: 1rem;
            padding: 1.5rem;
        }
    }
</style>

<div class="terms-container">
    <h1 class="terms-title">Conditions Générales d'Utilisation</h1>
    
    <div class="terms-section">
        <p>Dernière mise à jour : <?= date('d/m/Y') ?></p>
        <p>En utilisant la Bibliothèque Virtuelle, vous acceptez les présentes conditions d'utilisation.</p>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">1. Accès au service</h2>
        <ul class="terms-list">
            <li>L'utilisation nécessite la création d'un compte</li>
            <li>Vous devez fournir des informations exactes lors de l'inscription</li>
            <li>Les comptes sont personnels et non transférables</li>
        </ul>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">2. Utilisation des ressources</h2>
        <ul class="terms-list">
            <li>Les documents sont fournis à des fins éducatives uniquement</li>
            <li>Toute reproduction commerciale est strictement interdite</li>
            <li>Le <span class="highlight">prêt numérique</span> est limité à 7 jours par document</li>
        </ul>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">3. Responsabilités</h2>
        <p>L'utilisateur s'engage à :</p>
        <ul class="terms-list">
            <li>Ne pas contourner les mesures de protection des œuvres</li>
            <li>Ne pas perturber le fonctionnement du service</li>
            <li>Respecter les droits d'auteur</li>
        </ul>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">4. Données personnelles</h2>
        <p>Conformément au RGPD, nous collectons :</p>
        <ul class="terms-list">
            <li>Nom et prénom</li>
            <li>Adresse email</li>
            <li>Historique des emprunts (conservé 6 mois)</li>
        </ul>
        <p>Ces données ne sont pas partagées avec des tiers.</p>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">5. Modifications</h2>
        <p>Nous nous réservons le droit de modifier ces conditions. Les utilisateurs en seront informés par email.</p>
    </div>
    
    <div class="terms-section">
        <h2 class="section-title">6. Contact</h2>
        <p>Pour toute question : <span class="highlight">contact@bibliotheque-virtuelle.educ</span></p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
