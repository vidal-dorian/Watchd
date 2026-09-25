<?php
// modele/addMovie.php - Ajoute un film TMDB à la liste « à voir »
require_once __DIR__ . '/api.php';

try {
    $check = $pdo->prepare("SELECT 1 FROM films_a_voir WHERE tmdb_id = ? UNION SELECT 1 FROM films_vus WHERE tmdb_id = ?");
    $check->execute([$tmdbId, $tmdbId]);
    if ($check->fetch()) repondre(true, 'Déjà dans la collection.');

    $json = @file_get_contents("https://api.themoviedb.org/3/movie/$tmdbId?api_key=" . API_KEY . "&language=fr-FR");
    $details = $json ? json_decode($json, true) : null;
    if (!$details || isset($details['status_code'])) repondre(false, 'Film introuvable sur TMDB.', 404);

    // Saga : on la crée si elle n'existe pas encore
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

    $pdo->prepare("INSERT INTO films_a_voir (" . COLONNES_FILM . ", vu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0)")
        ->execute([
            $sagaId, $details['id'], $details['title'], $details['original_title'],
            implode(', ', array_column($details['genres'] ?? [], 'name')),
            $details['runtime'] ?? 0, $details['vote_average'] ?? 0,
            $details['overview'] ?? '', $details['tagline'] ?? '', ($details['release_date'] ?? '') ?: null,
            $details['poster_path'], $details['backdrop_path'],
        ]);

    repondre(true, 'Ajouté à la liste.');
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
