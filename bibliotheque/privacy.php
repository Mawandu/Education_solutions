<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = "Politique de confidentialité";
$theme = $_COOKIE['theme'] ?? 'light';
require_once 'header.php';
?>

<style>
    .policy-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background-color: var(--light);
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        line-height: 1.7;
    }
    
    .policy-title {
        color: var(--primary);
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--primary);
    }
    
    .policy-section {
        margin-bottom: 2rem;
    }
    
    .section-title {
        color: var(--secondary);
        margin: 1.5rem 0 1rem;
    }
    
    .contact-info {
        background-color: rgba(76, 175, 80, 0.1);
        padding: 1rem;
        border-radius: 6px;
        margin-top: 2rem;
    }
</style>

<div class="policy-container">
    <h1 class="policy-title">Politique de Confidentialité</h1>
    
    <div class="policy-section">
        <p><strong>Dernière mise à jour :</strong> <?= date('d/m/Y') ?></p>
        <p>Cette politique explique comment nous collectons, utilisons et protégeons vos informations personnelles.</p>
    </div>
    
    <div class="policy-section">
        <h2 class="section-title">1. Données collectées</h2>
        <p>Nous collectons :</p>
        <ul>
            <li>Informations d'inscription (nom, email)</li>
            <li>Historique des emprunts</li>
            <li>Données de connexion (adresse IP, type de navigateur)</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2 class="section-title">2. Utilisation des données</h2>
        <p>Vos données servent à :</p>
        <ul>
            <li>Fournir et personnaliser nos services</li>
            <li>Améliorer l'expérience utilisateur</li>
            <li>Envoyer des notifications importantes</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2 class="section-title">3. Protection des données</h2>
        <p>Nous mettons en œuvre :</p>
        <ul>
            <li>Chiffrement SSL/TLS</li>
            <li>Stockage sécurisé</li>
            <li>Accès restreint au personnel autorisé</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2 class="section-title">4. Cookies</h2>
        <p>Nous utilisons des cookies pour :</p>
        <ul>
            <li>Maintenir les sessions utilisateurs</li>
            <li>Mémoriser les préférences</li>
            <li>Analyser l'usage du site</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2 class="section-title">5. Vos droits</h2>
        <p>Conformément au RGPD, vous pouvez :</p>
        <ul>
            <li>Accéder à vos données</li>
            <li>Demander leur rectification</li>
            <li>Demander leur suppression</li>
        </ul>
    </div>
    
    <div class="contact-info">
        <h3>Contact Délégué à la Protection des Données :</h3>
        <p><i class="fas fa-envelope"></i> privacy@bibliotheque-virtuelle.educ</p>
        <p><i class="fas fa-phone"></i> +848 2797 1093</p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
