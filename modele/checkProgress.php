<?php
// modele/checkProgress.php
header('Content-Type: application/json');
require_once __DIR__ . '/../secrets.php';

$tmdbId = $_GET['id'] ?? null;
if (!$tmdbId) { echo json_encode(['status' => 'error']); exit; }

// 1. Récup ID Radarr via TMDB ID
$urlMovie = RADARR_URL . '/api/v3/movie?apikey=' . RADARR_API_KEY;
$jsonMovies = @file_get_contents($urlMovie);
$movies = json_decode($jsonMovies, true);

$monFilm = null;
if ($movies) {
    foreach ($movies as $m) {
        if (isset($m['tmdbId']) && $m['tmdbId'] == $tmdbId) {
            $monFilm = $m;
            break;
        }
    }
}

if (!$monFilm) { echo json_encode(['status' => 'not_found']); exit; }

// 2. Si le film est marqué comme "Téléchargé" dans Radarr (Fichier présent)
if ($monFilm['hasFile']) {
    echo json_encode(['status' => 'ready', 'percent' => 100, 'message' => 'Disponible']);
    exit;
}

// 3. Regarder la Queue (File d'attente)
$urlQueue = RADARR_URL . '/api/v3/queue?apikey=' . RADARR_API_KEY;
$jsonQueue = @file_get_contents($urlQueue);
$queue = json_decode($jsonQueue, true);

$item = null;
if (isset($queue['records'])) {
    foreach ($queue['records'] as $r) {
        if ($r['movieId'] == $monFilm['id']) {
            $item = $r;
            break;
        }
    }
}

// CAS : C'est dans la file (actif ou attente)
if ($item) {
    $size = $item['size'] ?? 0;
    $left = $item['sizeleft'] ?? 0;
    $status = $item['status']; // Downloading, Queued, Paused...
    $percent = 0;

    // Calcul mathématique simple
    if ($size > 0) {
        $percent = round((($size - $left) / $size) * 100, 1);
    }

    // Si on a commencé à télécharger (> 0%), on considère que c'est actif, peu importe le label "Queued"
    if ($percent > 0) {
        echo json_encode([
            'status' => 'downloading',
            'percent' => $percent,
            'message' => "Téléchargement : $percent%"
        ]);
        exit;
    }

    // Si 0% et statut Warning/Failed
    if ($status === 'Warning' || $status === 'Failed') {
        echo json_encode([
            'status' => 'warning',
            'percent' => 0,
            'message' => 'Erreur de téléchargement'
        ]);
        exit;
    }

    // Si 0% et Queued
    echo json_encode([
        'status' => 'warning', // Orange
        'percent' => 0,
        'message' => 'En attente de sources (0%)'
    ]);
    exit;
}

// CAS : Pas de fichier, pas dans la queue => Recherche en cours ou Échec
echo json_encode([
    'status' => 'searching',
    'percent' => 100, // Barre pleine mais grise
    'message' => 'Recherche de sources...' // Message explicite
]);
exit;