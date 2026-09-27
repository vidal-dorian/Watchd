<?php
// modele/noterFilm.php - Avis perso : POST {"tmdb_id", "note": 1..10 | null, "commentaire"}
require_once __DIR__ . '/api.php';

$note = $body['note'] ?? null;
if ($note !== null && (!is_int($note) || $note < 1 || $note > 10)) repondre(false, 'Note invalide', 400);
$commentaire = mb_substr(trim((string)($body['commentaire'] ?? '')), 0, 2000);

try {
    // Ni note ni commentaire : on efface l'avis
    if ($note === null && $commentaire === '') {
        $pdo->prepare("DELETE FROM avis WHERE tmdb_id = ? AND type = ?")->execute([$tmdbId, $type]);
    } else {
        $pdo->prepare("INSERT OR REPLACE INTO avis (type, tmdb_id, note, commentaire) VALUES (?, ?, ?, ?)")
            ->execute([$type, $tmdbId, $note, $commentaire ?: null]);
    }
    repondre(true);
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
