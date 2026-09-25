<?php
require_once __DIR__ . '/connexionBd.php';

try {
    echo "<h1>🛠️ Mise à jour ULTIME de la structure</h1>";

    // On nettoie
    $pdo->exec("DROP TABLE IF EXISTS films_a_voir");
    $pdo->exec("DROP TABLE IF EXISTS films_vus");
    $pdo->exec("DROP TABLE IF EXISTS films_non_trouves");
    $pdo->exec("DROP TRIGGER IF EXISTS trigger_bascule_vu");

    // 1. Table SAGAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS sagas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tmdb_id INTEGER UNIQUE,
        nom TEXT,
        poster_path TEXT,
        backdrop_path TEXT
    )");

    // 2. Définition commune des colonnes pour les films
    $cols = "
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        saga_id INTEGER,
        tmdb_id INTEGER UNIQUE,
        titre TEXT NOT NULL,
        titre_original TEXT,
        genres TEXT,
        duree INTEGER,
        note_tmdb REAL,
        synopsis TEXT,          -- Ajouté
        tagline TEXT,           -- Ajouté
        date_sortie TEXT,       -- Ajouté
        poster_path TEXT,
        backdrop_path TEXT,     -- Ajouté
        chemin_fichier TEXT,    -- Ajouté partout pour le tri NAS
        vu INTEGER DEFAULT 0,
        date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (saga_id) REFERENCES sagas(id)
    ";

    // 3. Création des tables identiques
    $pdo->exec("CREATE TABLE films_a_voir ($cols)");
    $pdo->exec("CREATE TABLE films_vus ($cols)");

    // 4. Table Erreurs
    $pdo->exec("CREATE TABLE films_non_trouves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom_fichier TEXT,
        chemin_complet TEXT,
        date_scan DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. TRIGGER INTELLIGENT
    // Il copie TOUT d'une table à l'autre
    $pdo->exec("
        CREATE TRIGGER trigger_bascule_vu
        AFTER UPDATE OF vu ON films_a_voir
        FOR EACH ROW
        WHEN NEW.vu = 1
        BEGIN
            INSERT INTO films_vus (
                tmdb_id, titre, titre_original, genres, duree, note_tmdb, 
                synopsis, tagline, date_sortie, poster_path, backdrop_path, chemin_fichier, vu
            )
            VALUES (
                OLD.tmdb_id, OLD.titre, OLD.titre_original, OLD.genres, OLD.duree, OLD.note_tmdb,
                OLD.synopsis, OLD.tagline, OLD.date_sortie, OLD.poster_path, OLD.backdrop_path, OLD.chemin_fichier, 1
            );
            
            DELETE FROM films_a_voir WHERE id = OLD.id;
        END;
    ");

    echo "✅ Base de données prête avec TOUS les attributs.";
    echo "<br>👉 <a href='scanner.php'>Relance le Scanner</a>.";

} catch (PDOException $e) {
    die("❌ Erreur : " . $e->getMessage());
}
?>