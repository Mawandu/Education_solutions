<?php
session_start();
require_once 'db_connect.php';

$pageTitle = "À propos";
require_once 'header.php';
?>

<main class="main-content">
    <div class="about-container" style="
        max-width: 1000px;
        margin: 0 auto;
        padding: 2rem 1rem;
    ">
        <section class="about-section" style="margin-bottom: 3rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">À propos de la Bibliothèque Virtuelle</h1>
            <p style="font-size: 1.1rem; line-height: 1.6;">
                La Bibliothèque Virtuelle est une plateforme innovante qui connecte les écoles, 
                les bibliothèques et les individus dans un écosystème de partage de connaissances.
            </p>
        </section>

        <section class="about-section" style="margin-bottom: 3rem;">
            <h2 style="font-size: 2rem; margin-bottom: 1rem; color: var(--primary);">Notre Mission</h2>
            <p style="margin-bottom: 1rem; font-size: 1.1rem;">
                Faciliter l'accès aux ressources éducatives et promouvoir la lecture à travers une plateforme collaborative où :
            </p>
            <ul style="padding-left: 2rem; font-size: 1.1rem; line-height: 1.8;">
                <li>Les écoles peuvent emprunter des livres en gros</li>
                <li>Les bibliothèques peuvent gérer efficacement leurs collections</li>
                <li>Les individus peuvent découvrir de nouvelles lectures</li>
            </ul>
        </section>

        <section class="about-section">
            <h2 style="font-size: 2rem; margin-bottom: 1.5rem; color: var(--primary);">Notre Engagement</h2>
            <p style="font-size: 1.1rem; line-height: 1.6; margin-bottom: 1rem;">
                Nous nous engageons à démocratiser l'accès à la lecture pour tous, en particulier dans les écoles africaines situées dans des zones dépourvues de bibliothèques physiques. Grâce à notre plateforme numérique, nous fournissons :
            </p>
            <ul style="padding-left: 2rem; font-size: 1.1rem; line-height: 1.8;">
                <li>Des ressources éducatives accessibles en ligne pour les élèves et enseignants</li>
                <li>Une bibliothèque virtuelle adaptée aux besoins des écoles rurales</li>
                <li>Des partenariats avec des organisations pour enrichir notre catalogue et soutenir l'alphabétisation</li>
                <li>Un accès gratuit ou à faible coût pour garantir l'inclusion éducative</li>
            </ul>
            <p style="font-size: 1.1rem; line-height: 1.6; margin-top: 1rem;">
                Notre vision est de faire de la lecture un droit universel, en connectant chaque école d'Afrique à un monde de connaissances, où qu'elle se trouve.
            </p>
        </section>
    </div>
</main>

<?php require_once 'footer.php'; ?>