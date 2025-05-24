<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = "Contactez-nous";
$theme = $_COOKIE['theme'] ?? 'light';
require_once 'header.php';
?>

<style>
    .contact-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background-color: var(--light);
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .contact-title {
        color: var(--primary);
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .contact-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    .contact-info {
        background-color: rgba(76, 175, 80, 0.1);
        padding: 1.5rem;
        border-radius: 8px;
    }
    
    .contact-form input,
    .contact-form textarea {
        width: 100%;
        padding: 0.8rem;
        margin-bottom: 1rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .contact-form button {
        background-color: var(--primary);
        color: white;
        padding: 0.8rem 1.5rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .contact-icon {
        font-size: 1.5rem;
        color: var(--primary);
        margin-right: 10px;
    }
</style>

<div class="contact-container">
    <h1 class="contact-title">Contactez notre équipe</h1>
    
    <div class="contact-grid">
        <div class="contact-info">
            <h3><i class="fas fa-map-marker-alt contact-icon"></i>Adresse</h3>
            <p>123 Rue de la Bibliothèque, Ville, 75000</p>
            
            <h3><i class="fas fa-phone contact-icon"></i>Téléphone</h3>
            <p>+84827971093</p>
            
            <h3><i class="fas fa-envelope contact-icon"></i>Email</h3>
            <p>contact@bibliotheque-virtuelle.educ</p>
            
            <h3><i class="fas fa-clock contact-icon"></i>Horaires</h3>
            <p>Lundi-Vendredi : 9h-18h</p>
        </div>
        
        <div class="contact-form">
            <form action="process_contact.php" method="post">
                <input type="text" name="name" placeholder="Votre nom" required>
                <input type="email" name="email" placeholder="Votre email" required>
                <input type="text" name="subject" placeholder="Sujet">
                <textarea name="message" rows="5" placeholder="Votre message" required></textarea>
                <button type="submit"><i class="fas fa-paper-plane"></i> Envoyer</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
