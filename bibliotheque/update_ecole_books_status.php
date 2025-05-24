<?php
require_once 'db_connect.php';

try {
    // Mettre à jour le statut des livres dont l'échéance est dépassée
    $stmt = $pdo->prepare("
        UPDATE ecole_books 
        SET status = 'expired' 
        WHERE echeance_date < NOW() AND status = 'active'
    ");
    $stmt->execute();

    // Supprimer les entrées correspondantes dans ecole_lecture
    $stmt = $pdo->prepare("
        DELETE FROM ecole_lecture 
        WHERE ecole_book_id IN (
            SELECT id FROM ecole_books WHERE status = 'expired'
        )
    ");
    $stmt->execute();

    echo "Mise à jour des statuts effectuée avec succès.\n";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>
