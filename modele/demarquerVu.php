<?php
// modele/demarquerVu.php - films_vus -> films_a_voir
require_once __DIR__ . '/api.php';
deplacerFilm($pdo, $tmdbId, $type, 'films_vus', 'films_a_voir', 0);
