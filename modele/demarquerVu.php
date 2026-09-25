<?php
// modele/demarquerVu.php
require_once __DIR__ . '/connexionBd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tmdb_id'])) {
    $tmdbId = $_POST['tmdb_id'];

    try {
        $pdo->beginTransaction();

        // 1. SECURITÉ : On supprime d'abord de 'films_a_voir' pour éviter tout conflit d'ID unique
        $del = $pdo->prepare("DELETE FROM films_a_voir WHERE tmdb_id = ?");
        $del->execute([$tmdbId]);

        // 2. INSERTION PROPRE : On copie de 'films_vus' vers 'films_a_voir'
        // On force 'vu' à 0 et on met à jour 'date_ajout' à maintenant
        $sql = "INSERT INTO films_a_voir (
                    saga_id, tmdb_id, titre, titre_original, genres, 
                    duree, note_tmdb, synopsis, tagline, date_sortie, 
                    poster_path, backdrop_path, chemin_fichier, vu, date_ajout
                )
                SELECT 
                    saga_id, tmdb_id, titre, titre_original, genres, 
                    duree, note_tmdb, synopsis, tagline, date_sortie, 
                    poster_path, backdrop_path, chemin_fichier, 0, datetime('now')
                FROM films_vus WHERE tmdb_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tmdbId]);

        // 3. NETTOYAGE : On supprime enfin de 'films_vus'
        $delVu = $pdo->prepare("DELETE FROM films_vus WHERE tmdb_id = ?");
        $delVu->execute([$tmdbId]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

// Retour sur la page du film
if (isset($tmdbId)) {
    header("Location: ../pages/detailFilm.php?id=" . $tmdbId);
} else {
    header("Location: ../index.php");
}
exit;