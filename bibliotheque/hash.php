<?php
$mot_de_passe = 'admin@123';
$hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
echo "Hash généré : " . $hash;
?>
