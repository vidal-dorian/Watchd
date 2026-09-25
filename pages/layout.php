<?php
// pages/layout.php - Helpers et gabarit commun à toutes les pages

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function dureeFmt($min) {
    $min = (int)$min;
    return $min > 0 ? intdiv($min, 60) . 'h' . sprintf('%02d', $min % 60) : '';
}

function noteFmt($note) { return $note ? number_format((float)$note, 1, ',', '') : ''; }

function annee($date) { return substr($date ?? '', 0, 4); }

function imageTmdb($path, $taille = 'w342') {
    return $path ? "https://image.tmdb.org/t/p/$taille$path" : null;
}

function isExtended($titre) {
    return preg_match('/(version longue|extended|director|uncut|v\.l\.)/i', $titre);
}

// Image d'affiche avec apparition en fondu une fois chargée
function imgAffiche($url, $alt = '') { ?>
    <img src="<?= e($url) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async" onload="this.classList.add('is-loaded')">
<?php }

// $base : chemin vers la racine ('' depuis index.php, '../' depuis pages/)
// $actif : 'collection' | 'ajouter' | null
// $opts : 'classe' (classe du body), 'ambiance' (image du halo de fond), 'barre' (afficher la barre du haut)
function enTete($base, $titre, $actif, $opts = []) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07070a">
    <title><?= e($titre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://image.tmdb.org">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ?v= change à chaque modification du CSS : Cloudflare et le navigateur ne servent jamais une vieille version -->
    <link rel="stylesheet" href="<?= $base ?>css/app.css?v=<?= filemtime(__DIR__ . '/../css/app.css') ?>">
    <script>
        // localStorage peut être indisponible (navigation privée...) : on ne plante jamais dessus
        const store = {
            get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } },
            set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch {} },
            del(k) { try { localStorage.removeItem(k); } catch {} },
        };

        function toast(message, erreur = false) {
            let zone = document.getElementById('toasts');
            if (!zone) {
                zone = document.createElement('div');
                zone.id = 'toasts';
                zone.className = 'toasts';
                zone.setAttribute('role', 'status');
                document.body.append(zone);
            }
            const t = document.createElement('div');
            t.className = 'toast' + (erreur ? ' is-err' : '');
            t.innerHTML = `<i class="fas ${erreur ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>`;
            t.append(message);
            zone.append(t);
            setTimeout(() => t.remove(), 3200);
        }
    </script>
</head>
<body class="<?= e($opts['classe'] ?? '') ?>">
<?php if (!empty($opts['ambiance'])): ?>
    <div class="ambiance" id="ambiance" style="background-image: url('<?= e($opts['ambiance']) ?>')" aria-hidden="true"></div>
<?php endif; ?>
<?php if ($opts['barre'] ?? true): ?>
<header class="topbar" id="topbar">
    <a href="<?= $base ?>index.php" class="logo" aria-label="Watchd, accueil">WATCHD</a>
    <nav class="topnav" aria-label="Navigation principale">
        <a href="<?= $base ?>index.php" <?= $actif === 'collection' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-film"></i> Collection
        </a>
        <a href="<?= $base ?>pages/recherche.php" <?= $actif === 'ajouter' ? 'aria-current="page"' : '' ?>>
            <i class="fas fa-plus"></i> Ajouter
        </a>
    </nav>
</header>
<?php endif;
}

function piedDePage($base, $actif, $nav = true) { ?>
<?php if ($nav): ?>
<nav class="bottomnav" aria-label="Navigation principale">
    <a href="<?= $base ?>index.php" <?= $actif === 'collection' ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-film"></i><span>Collection</span>
    </a>
    <a href="<?= $base ?>pages/recherche.php" <?= $actif === 'ajouter' ? 'aria-current="page"' : '' ?>>
        <i class="fas fa-plus"></i><span>Ajouter</span>
    </a>
</nav>
<?php endif; ?>
<script>
    {
        // Barre du haut en verre dès qu'on défile ; barre de recherche collante qui se cache
        // quand on descend et revient quand on remonte (mobile)
        const topbar = document.getElementById('topbar');
        const toolbar = document.querySelector('.toolbar');
        const repere = document.createElement('div');
        toolbar?.before(repere);
        let dernierY = scrollY;
        const majBarres = () => {
            const y = scrollY;
            topbar?.classList.toggle('scrolled', y > 8);
            if (toolbar) {
                const haut = parseFloat(getComputedStyle(toolbar).top) || 0;
                toolbar.classList.toggle('is-stuck', repere.getBoundingClientRect().top < haut + 1);
                if (Math.abs(y - dernierY) > 6) {
                    toolbar.classList.toggle('is-hidden', y > dernierY && y > 400 && document.activeElement?.tagName !== 'INPUT');
                    dernierY = y;
                }
            }
        };
        addEventListener('scroll', majBarres, { passive: true });
        majBarres();

        // L'affiche cliquée « s'envole » vers la fiche (View Transitions, navigateurs compatibles)
        document.addEventListener('click', e => {
            const lien = e.target.closest('a[data-vt]');
            if (!lien) return;
            document.querySelectorAll('.vt-poster').forEach(el => el.classList.remove('vt-poster'));
            lien.closest('.card')?.querySelector('.poster')?.classList.add('vt-poster');
        });
    }
</script>
</body>
</html>
<?php }
