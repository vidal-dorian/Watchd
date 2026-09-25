<?php
// modele/api.php - Base commune des actions JSON : POST {"tmdb_id": 123}
header('Content-Type: application/json');
require_once __DIR__ . '/connexionBd.php';

function repondre($ok, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') repondre(false, 'Méthode non autorisée', 405);

$tmdbId = (int)(json_decode(file_get_contents('php://input'), true)['tmdb_id'] ?? 0);
if ($tmdbId <= 0) repondre(false, 'ID invalide', 400);

// Colonnes copiées quand un film change de table (tout sauf id, vu, date_ajout)
const COLONNES_FILM = 'saga_id, tmdb_id, titre, titre_original, genres, duree, note_tmdb, synopsis, tagline, date_sortie, poster_path, backdrop_path, chemin_fichier';

// Déplace un film d'une table à l'autre dans une transaction
function deplacerFilm($pdo, $tmdbId, $depuis, $vers, $vu) {
    try {
        $pdo->beginTransaction();
        $cols = COLONNES_FILM;
        $ins = $pdo->prepare("INSERT INTO $vers ($cols, vu, date_ajout) SELECT $cols, $vu, datetime('now') FROM $depuis WHERE tmdb_id = ?");
        $ins->execute([$tmdbId]);
        if ($ins->rowCount() === 0) {
            $pdo->rollBack();
            repondre(false, 'Film introuvable', 404);
        }
        $pdo->prepare("DELETE FROM $depuis WHERE tmdb_id = ?")->execute([$tmdbId]);
        $pdo->commit();
        repondre(true);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        repondre(false, $e->getMessage(), 500);
    }
}
