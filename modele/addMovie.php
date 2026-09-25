<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../secrets.php';
require_once __DIR__ . '/connexionBd.php';

// Fonction pour parler à Radarr
function envoyerARadarr($tmdbId, $titre) {
    $url = RADARR_URL . '/api/v3/movie?apikey=' . RADARR_API_KEY;

    $data = [
        'tmdbId' => (int)$tmdbId,
        'title' => $titre,
        'qualityProfileId' => RADARR_QUALITY_PROFILE,
        'rootFolderPath' => RADARR_ROOT_FOLDER,
        'monitored' => true,
        'addOptions' => [
            'searchForMovie' => true
        ]
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

try {
    // 1. Récupération des données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $tmdbId = $input['tmdb_id'] ?? null;
    // NOUVEAU : On regarde si l'utilisateur veut télécharger ou juste ajouter à la liste
    // Par défaut (true) pour garder la compatibilité, mais on l'enverra à false depuis le bouton
    $download = $input['download'] ?? true;

    if (!$tmdbId) {
        throw new Exception("ID du film manquant.");
    }

    // 2. Infos TMDB
    $urlTmdb = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . API_KEY . "&language=fr-FR";
    $jsonTmdb = @file_get_contents($urlTmdb);
    $details = json_decode($jsonTmdb, true);

    if (!$details || isset($details['status_code'])) {
        throw new Exception("Film introuvable sur TMDB.");
    }

    // 3. RADARR (Seulement si demandé)
    $radarrMessage = "Ajouté à la Watchlist (Sans téléchargement)";

    if ($download) {
        $radarrResponse = envoyerARadarr($tmdbId, $details['title']);
        $radarrMessage = "Ajouté et téléchargement lancé";

        if (isset($radarrResponse[0]['errorMessage'])) {
            $radarrMessage = "Radarr : " . $radarrResponse[0]['errorMessage'];
        } elseif (isset($radarrResponse['message'])) {
            $radarrMessage = "Radarr erreur : " . $radarrResponse['message'];
        }
    }

    // 4. INSERTION BASE LOCALE
    $stmtCheck = $pdo->prepare("SELECT id FROM films_a_voir WHERE tmdb_id = ? UNION SELECT id FROM films_vus WHERE tmdb_id = ?");
    $stmtCheck->execute([$tmdbId, $tmdbId]);

    if (!$stmtCheck->fetch()) {
        // ... (Logique Saga identique à avant) ...
        $sagaIdLocal = null;
        if (!empty($details['belongs_to_collection'])) {
            $col = $details['belongs_to_collection'];
            $stmtSaga = $pdo->prepare("SELECT id FROM sagas WHERE tmdb_id = ?");
            $stmtSaga->execute([$col['id']]);
            $row = $stmtSaga->fetch();
            if ($row) $sagaIdLocal = $row['id'];
            else {
                $ins = $pdo->prepare("INSERT INTO sagas (tmdb_id, nom, poster_path, backdrop_path) VALUES (?,?,?,?)");
                $ins->execute([$col['id'], $col['name'], $col['poster_path'], $col['backdrop_path']]);
                $sagaIdLocal = $pdo->lastInsertId();
            }
        }

        $genres = [];
        if (isset($details['genres'])) foreach($details['genres'] as $g) $genres[] = $g['name'];

        $sql = "INSERT INTO films_a_voir (
                    saga_id, tmdb_id, titre, titre_original, genres, 
                    duree, note_tmdb, synopsis, tagline, date_sortie, 
                    poster_path, backdrop_path, chemin_fichier, vu
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $sagaIdLocal, $details['id'], $details['title'], $details['original_title'],
            implode(', ', $genres), $details['runtime'], $details['vote_average'],
            $details['overview'], $details['tagline'] ?? '', $details['release_date'] ?? null,
            $details['poster_path'], $details['backdrop_path']
        ]);

        echo json_encode(['success' => true, 'message' => $radarrMessage]);
    } else {
        // Le film existe déjà (Vu ou À voir)
        // Mais si on a demandé un téléchargement ($download = true), Radarr a quand même été contacté au début du script.
        // On renvoie donc le message de Radarr pour confirmer à l'utilisateur.

        $msg = 'Déjà dans la liste.';
        if ($download) {
            $msg .= ' ' . $radarrMessage;
        }

        echo json_encode([
            'success' => true,
            'already_exists' => true,
            'message' => $msg
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>