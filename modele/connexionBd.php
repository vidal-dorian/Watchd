<?php
// On n'a plus besoin des identifiants MySQL de secrets.php
// Mais on garde secrets.php s'il contient ta clé API TMDB
require_once __DIR__ . '/../secrets.php';

// Définition du chemin vers le fichier de base de données
// Il sera stocké dans le dossier 'data' à la racine du site
$dbPath = __DIR__ . '/../data/watchd.sqlite';

try {
    // Connexion SPÉCIFIQUE pour SQLite (Mode fichier)
    $pdo = new PDO('sqlite:' . $dbPath);

    // Configuration des options d'erreur et de récupération
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Important pour SQLite : activer la gestion des clés étrangères
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    // Si le dossier n'existe pas ou qu'il y a un problème de droit
    die("❌ Erreur de connexion SQLite : " . $e->getMessage() .
        "<br>Assure-toi que le dossier <b>/data</b> existe à la racine du site et qu'il est accessible en écriture.");
}
?>