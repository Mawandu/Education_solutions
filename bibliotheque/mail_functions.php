<?php
function sendEmail($to, $subject, $message) {
    // Headers pour l'email
    $headers = "From: no-reply@bibliotheque-virtuelle.com\r\n";
    $headers .= "Reply-To: no-reply@bibliotheque-virtuelle.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Envoi de l'email
    return mail($to, $subject, $message, $headers);
}
