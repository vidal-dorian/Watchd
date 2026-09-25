<?php
// modele/marquerVu.php - films_a_voir -> films_vus
// On déplace nous-mêmes au lieu de faire UPDATE vu = 1 : le trigger trigger_bascule_vu
// ne recopie pas saga_id, le film sortait donc de sa saga.
require_once __DIR__ . '/api.php';
deplacerFilm($pdo, $tmdbId, 'films_a_voir', 'films_vus', 1);
