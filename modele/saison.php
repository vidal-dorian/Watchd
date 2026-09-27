<?php
// modele/saison.php - Saison en cours d'une série : POST {"tmdb_id", "type": "tv", "saison": 1.. | null}
// null : la série n'est plus commencée, elle redevient « à voir ».
require_once __DIR__ . '/api.php';

$saison = $body['saison'] ?? null;
if ($saison !== null && (!is_int($saison) || $saison < 1)) repondre(false, 'Saison invalide', 400);

try {
    $maj = $pdo->prepare("UPDATE films_a_voir SET saison = ? WHERE tmdb_id = ? AND type = 'tv'");
    $maj->execute([$saison, $tmdbId]);
    if ($maj->rowCount() === 0) repondre(false, 'Série introuvable dans « à voir »', 404);
    repondre(true);
} catch (Exception $e) {
    repondre(false, $e->getMessage(), 500);
}
