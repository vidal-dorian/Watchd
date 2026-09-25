<?php
// modele/supprimerFilm.php - Retire un film de la collection
require_once __DIR__ . '/api.php';

try {
    $pdo->prepare("DELETE FROM films_a_voir WHERE tmdb_id = ?")->execute([$tmdbId]);
    $pdo->prepare("DELETE FROM films_vus WHERE tmdb_id = ?")->execute([$tmdbId]);
    repondre(true);
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
