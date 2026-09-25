<?php
// pages/layout.php - Helpers et gabarit commun à toutes les pages

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function dureeFmt($min) {
    $min = (int)$min;
    return $min > 0 ? intdiv($min, 60) . 'h' . sprintf('%02d', $min % 60) : '';
}

function imageTmdb($path, $taille = 'w342') {
    return $path ? "https://image.tmdb.org/t/p/$taille$path" : null;
}

function isExtended($titre) {
    return preg_match('/(version longue|extended|director|uncut|v\.l\.)/i', $titre);
}

// $base : chemin vers la racine ('' depuis index.php, '../' depuis pages/)
// $actif : 'collection' | 'ajouter' | null
function enTete($base, $titre, $actif, $retour = false) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0b0d">
    <title><?= e($titre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>css/app.css">
</head>
<body>
<header class="topbar">
    <?php if ($retour): ?>
        <a href="<?= $base ?>index.php" class="icon-btn" aria-label="Retour"
           onclick="if (document.referrer.includes(location.host)) { history.back(); return false; }">
            <i class="fas fa-arrow-left"></i>
        </a>
    <?php endif; ?>
    <a href="<?= $base ?>index.php" class="logo">WATCHD</a>
    <nav class="topnav">
        <a href="<?= $base ?>index.php" <?= $actif === 'collection' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-film"></i> Collection
        </a>
        <a href="<?= $base ?>pages/recherche.php" <?= $actif === 'ajouter' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-plus"></i> Ajouter
        </a>
    </nav>
</header>
<?php }

function piedDePage($base, $actif) { ?>
<nav class="bottomnav">
    <a href="<?= $base ?>index.php" <?= $actif === 'collection' ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-film"></i><span>Collection</span>
    </a>
    <a href="<?= $base ?>pages/recherche.php" <?= $actif === 'ajouter' ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-plus"></i><span>Ajouter</span>
    </a>
</nav>
</body>
</html>
<?php }
