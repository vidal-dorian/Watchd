<?php
// modele/addMovie.php - Ajoute un film ou une série TMDB à la liste « à voir »
require_once __DIR__ . '/api.php';

try {
    $check = $pdo->prepare("SELECT 1 FROM films_a_voir WHERE tmdb_id = ? AND type = ? UNION SELECT 1 FROM films_vus WHERE tmdb_id = ? AND type = ?");
    $check->execute([$tmdbId, $type, $tmdbId, $type]);
    if ($check->fetch()) repondre(true, 'Déjà dans la collection.');

    $json = @file_get_contents("https://api.themoviedb.org/3/$type/$tmdbId?api_key=" . API_KEY . "&language=fr-FR");
    $details = $json ? json_decode($json, true) : null;
    if (!$details || isset($details['status_code'])) repondre(false, 'Introuvable sur TMDB.', 404);

    // Saga : on la crée si elle n'existe pas encore (films uniquement)
    $sagaId = null;
    if (!empty($details['belongs_to_collection'])) {
        $col = $details['belongs_to_collection'];
        $stmt = $pdo->prepare("SELECT id FROM sagas WHERE tmdb_id = ?");
        $stmt->execute([$col['id']]);
        $sagaId = $stmt->fetchColumn();
        if (!$sagaId) {
            $pdo->prepare("INSERT INTO sagas (tmdb_id, nom, poster_path, backdrop_path) VALUES (?, ?, ?, ?)")
                ->execute([$col['id'], $col['name'], $col['poster_path'], $col['backdrop_path']]);
            $sagaId = $pdo->lastInsertId();
        }
    }

    // Série : durée = celle d'un épisode
    $tv = $type === 'tv';
    $duree = $tv ? ($details['episode_run_time'][0] ?? $details['last_episode_to_air']['runtime'] ?? 0) : ($details['runtime'] ?? 0);

    $pdo->prepare("INSERT INTO films_a_voir (" . COLONNES_FILM . ", vu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0)")
        ->execute([
            $type, $sagaId, $details['id'],
            $details[$tv ? 'name' : 'title'], $details[$tv ? 'original_name' : 'original_title'],
            implode(', ', array_column($details['genres'] ?? [], 'name')),
            $duree, $details['number_of_seasons'] ?? null, $details['vote_average'] ?? 0,
            $details['overview'] ?? '', $details['tagline'] ?? '', ($details[$tv ? 'first_air_date' : 'release_date'] ?? '') ?: null,
            $details['poster_path'], $details['backdrop_path'],
        ]);

    repondre(true, 'Ajouté à la liste.');
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
