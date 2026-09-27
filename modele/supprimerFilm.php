<?php
// modele/supprimerFilm.php - Retire un film ou une série de la collection
require_once __DIR__ . '/api.php';

try {
    $pdo->prepare("DELETE FROM films_a_voir WHERE tmdb_id = ? AND type = ?")->execute([$tmdbId, $type]);
    $pdo->prepare("DELETE FROM films_vus WHERE tmdb_id = ? AND type = ?")->execute([$tmdbId, $type]);
    $pdo->prepare("DELETE FROM avis WHERE tmdb_id = ? AND type = ?")->execute([$tmdbId, $type]);
    repondre(true);
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
