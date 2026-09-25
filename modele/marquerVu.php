<?php
require_once __DIR__ . '/connexionBd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tmdb_id'])) {

    $tmdbId = $_POST['tmdb_id'];

    try {
        // C'est ici que la magie opère.
        // On change juste le 0 en 1.
        // Le TRIGGER 'trigger_bascule_vu' détecte ce changement,
        // copie le film dans 'films_vus' et le supprime de 'films_a_voir'.
        $stmt = $pdo->prepare("UPDATE films_a_voir SET vu = 1 WHERE tmdb_id = ?");
        $stmt->execute([$tmdbId]);

    } catch (PDOException $e) {
        // En cas d'erreur, on ne fait rien de spécial pour l'instant,
        // on renverra juste l'utilisateur sur la page.
    }
}

// Une fois fini, on recharge la page d'où l'on vient (la fiche du film)
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;