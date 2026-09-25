<?php
// CHANGE CE CHEMIN POUR TESTER
$cheminATester = ''; // <--- Mets ton chemin ici

echo "<h2>Test d'accès au dossier : $cheminATester</h2>";

if (is_dir($cheminATester)) {
    echo "<h3 style='color:green'>✅ BINGO ! PHP voit bien ce dossier.</h3>";

    $fichiers = scandir($cheminATester);
    echo "Voici les 5 premiers éléments trouvés :<br><ul>";
    $i = 0;
    foreach($fichiers as $f) {
        if($f != '.' && $f != '..') {
            echo "<li>$f</li>";
            $i++;
        }
        if($i >= 5) break;
    }
    echo "</ul>";
} else {
    echo "<h3 style='color:red'>❌ ÉCHEC. PHP ne trouve pas ce dossier.</h3>";
    echo "Vérifie que :<br>";
    echo "1. Le dossier est bien monté sur l'ordinateur/serveur.<br>";
    echo "2. Le chemin est correct (Attention aux majuscules !).<br>";
    echo "3. PHP a les droits de lecture dessus.";
}
?>