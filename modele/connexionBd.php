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

    // Migration v1 : les séries rejoignent les films. Un film et une série peuvent avoir le même
    // tmdb_id, l'unicité passe donc sur (type, tmdb_id) : SQLite impose de reconstruire les tables.
    // type = 'movie' | 'tv' (les valeurs de TMDB), saisons = nombre de saisons d'une série.
    if ($pdo->query("PRAGMA user_version")->fetchColumn() < 1) {
        $tables = [
            'films_a_voir' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL DEFAULT \'movie\', saga_id INTEGER REFERENCES sagas(id),
                tmdb_id INTEGER NOT NULL, titre TEXT NOT NULL, titre_original TEXT, genres TEXT, duree INTEGER, saisons INTEGER,
                note_tmdb REAL, synopsis TEXT, tagline TEXT, date_sortie TEXT, poster_path TEXT, backdrop_path TEXT, chemin_fichier TEXT,
                vu INTEGER DEFAULT 0, date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE (type, tmdb_id)',
            // Avis perso, à part des films : ils survivent aux allers-retours « vu » / « à voir »
            'avis' => 'type TEXT NOT NULL DEFAULT \'movie\', tmdb_id INTEGER NOT NULL, note INTEGER CHECK (note BETWEEN 1 AND 10),
                commentaire TEXT, date_maj DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (type, tmdb_id)',
        ];
        $tables['films_vus'] = $tables['films_a_voir'];
        $anciennes = [
            'films_a_voir' => 'id, saga_id, tmdb_id, titre, titre_original, genres, duree, note_tmdb, synopsis, tagline, date_sortie, poster_path, backdrop_path, chemin_fichier, vu, date_ajout',
            'avis' => 'tmdb_id, note, commentaire, date_maj',
        ];
        $anciennes['films_vus'] = $anciennes['films_a_voir'];

        // En cas d'erreur, la connexion se ferme sans COMMIT : SQLite annule tout
        $pdo->beginTransaction();
        foreach ($tables as $t => $def) {
            $pdo->exec("CREATE TABLE {$t}_v1 ($def)");
            if ($pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = '$t'")->fetchColumn()) {
                $pdo->exec("INSERT INTO {$t}_v1 ({$anciennes[$t]}) SELECT {$anciennes[$t]} FROM $t");
                $pdo->exec("DROP TABLE $t"); // emporte aussi l'ancien trigger_bascule_vu, inutilisé
            }
            $pdo->exec("ALTER TABLE {$t}_v1 RENAME TO $t");
        }
        $pdo->exec("PRAGMA user_version = 1");
        $pdo->commit();
    }

} catch (PDOException $e) {
    // Si le dossier n'existe pas ou qu'il y a un problème de droit
    die("❌ Erreur de connexion SQLite : " . $e->getMessage() .
        "<br>Assure-toi que le dossier <b>/data</b> existe à la racine du site et qu'il est accessible en écriture.");
}
?>