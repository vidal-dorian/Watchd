<?php
// modele/supprimerFilm.php
header('Content-Type: application/json');
require_once __DIR__ . '/connexionBd.php';

// Récupération du JSON envoyé par le JS
$input = json_decode(file_get_contents('php://input'), true);
$tmdbId = $input['tmdb_id'] ?? null;

if (!$tmdbId) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    // Suppression brutale dans les deux tables
    // Pas besoin de transaction complexe ici, on veut juste que ça disparaisse
    $stmt1 = $pdo->prepare("DELETE FROM films_a_voir WHERE tmdb_id = ?");
    $stmt1->execute([$tmdbId]);

    $stmt2 = $pdo->prepare("DELETE FROM films_vus WHERE tmdb_id = ?");
    $stmt2->execute([$tmdbId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>