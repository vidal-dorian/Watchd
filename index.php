<?php
// index.php - Version Finale (Scroll Memory + Saga Memory + Filtres)
require_once __DIR__ . '/modele/connexionBd.php';

// --- 1. RÉCUPÉRATION ET GROUPEMENT ---
$sql = "SELECT T.*, s.nom as saga_nom, s.poster_path as saga_poster 
        FROM (
            SELECT *, 'a_voir' as statut FROM films_a_voir
            UNION
            SELECT *, 'vu' as statut FROM films_vus
        ) AS T
        LEFT JOIN sagas s ON T.saga_id = s.id
        ORDER BY T.date_ajout DESC";

$stmt = $pdo->query($sql);
$allFilms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$affichage = [];
$sagasMap = [];
$heroMovie = null;
$filmsWithBackdrop = [];

function isExtended($titre) {
    return preg_match('/(version longue|extended|director|uncut|v\.l\.)/i', $titre);
}

foreach ($allFilms as $f) {
    if (!empty($f['backdrop_path']) && !empty($f['synopsis'])) {
        $filmsWithBackdrop[] = $f;
    }

    if (!empty($f['saga_id'])) {
        $sId = $f['saga_id'];
        if (!isset($sagasMap[$sId])) {
            $sagasMap[$sId] = [
                'is_saga' => true,
                'id' => $sId,
                'titre' => $f['saga_nom'],
                'poster_path' => $f['saga_poster'] ?? $f['poster_path'],
                'genres' => $f['genres'],
                'date_ajout' => $f['date_ajout'],
                'statut' => $f['statut'],
                'films' => []
            ];
            $affichage[] = &$sagasMap[$sId];
        }
        $sagasMap[$sId]['films'][] = $f;
    } else {
        $f['is_saga'] = false;
        $affichage[] = $f;
    }
}

if (!empty($filmsWithBackdrop)) $heroMovie = $filmsWithBackdrop[array_rand($filmsWithBackdrop)];
elseif (!empty($allFilms)) $heroMovie = $allFilms[0];

$tousLesGenres = [];
foreach ($allFilms as $f) {
    if (!empty($f['genres'])) {
        foreach (explode(',', $f['genres']) as $g) {
            $g = trim($g);
            if ($g && !in_array($g, $tousLesGenres)) $tousLesGenres[] = $g;
        }
    }
}
sort($tousLesGenres);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watchd Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- STYLE & DA ORIGINAL --- */
        :root {
            --bg-deep: #0a0a0a;
            --bg-surface: #141414;
            --accent-red: #ff1f1f;
            --accent-purple: #9b59b6;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --glass-bg: rgba(20, 20, 20, 0.7);
            --glass-border: 1px solid rgba(255, 255, 255, 0.1);
            --glow-red: 0 0 20px rgba(255, 31, 31, 0.3);
            --transition-smooth: cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: var(--bg-deep); color: var(--text-primary); font-family: 'Montserrat', sans-serif; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; transition: 0.3s; }
        input, select, button { font-family: inherit; outline: none; }

        /* NAVBAR */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; padding: 20px 4%;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 1000; background: transparent; transition: all 0.4s var(--transition-smooth);
        }
        .navbar.scrolled {
            background: var(--glass-bg); backdrop-filter: blur(15px);
            border-bottom: var(--glass-border); padding: 15px 4%;
        }
        .logo {
            font-weight: 900; font-size: 1.8rem; letter-spacing: 2px;
            background: linear-gradient(45deg, var(--accent-red), #ff5757);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .nav-actions { display: flex; gap: 15px; }
        .btn-nav {
            padding: 10px 20px; border-radius: 30px; font-weight: 600; font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px; transition: 0.3s; border: 1px solid transparent;
        }
        .btn-ghost { background: rgba(255,255,255,0.1); color: white; }
        .btn-ghost:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); }
        .btn-primary-red { background: var(--accent-red); color: white; box-shadow: var(--glow-red); }
        .btn-primary-red:hover { background: #d41b1b; transform: translateY(-2px); }

        /* HERO */
        .hero {
            position: relative; height: 85vh; width: 100%;
            background-size: cover; background-position: center top;
            display: flex; align-items: center; justify-content: center; text-align: center;
        }
        .hero-overlay {
            position: absolute; inset: 0;
            background: radial-gradient(circle at center, transparent 0%, var(--bg-deep) 90%),
            linear-gradient(to top, var(--bg-deep) 10%, transparent 50%);
        }
        .hero-content { position: relative; z-index: 2; max-width: 800px; padding: 0 20px; margin-top: 50px; }
        .hero-title { font-size: 4rem; font-weight: 900; text-transform: uppercase; line-height: 1; margin-bottom: 20px; text-shadow: 0 5px 15px rgba(0,0,0,0.5); }
        .hero-meta { display: inline-flex; gap: 20px; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; backdrop-filter: blur(5px); }
        .match-score { color: var(--accent-red); }
        .hero-desc { font-size: 1.1rem; line-height: 1.6; color: #ddd; margin-bottom: 30px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;}

        /* FILTRES */
        .filter-container { position: relative; z-index: 10; margin: -40px auto 40px auto; width: 90%; max-width: 1100px; }
        .filter-bar {
            display: flex; align-items: center; gap: 15px;
            background: var(--glass-bg); backdrop-filter: blur(20px);
            border: var(--glass-border); padding: 10px 20px;
            border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .search-wrapper { flex: 2; display: flex; align-items: center; gap: 10px; color: var(--text-secondary); }
        .search-input { background: transparent; border: none; color: white; font-size: 1rem; width: 100%; padding: 5px; }
        .search-input::placeholder { color: var(--text-secondary); }
        .filter-select {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: white; padding: 8px 15px; border-radius: 20px; cursor: pointer; transition: 0.3s; flex: 1;
        }
        .filter-select option { background: var(--bg-deep); }
        .toggles { display: flex; gap: 5px; background: rgba(0,0,0,0.3); padding: 5px; border-radius: 30px; }
        .toggle-btn {
            padding: 8px 15px; border-radius: 20px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); transition: 0.3s; display: flex; align-items: center; gap: 5px; user-select: none;
        }
        .toggle-btn.active-red { background: var(--accent-red); color: white; box-shadow: var(--glow-red); }
        .toggle-btn.active-green { background: #2ecc71; color: white; box-shadow: 0 0 15px rgba(46, 204, 113, 0.3); }
        .toggle-btn input { display: none; }

        /* GRID */
        .grid-section { padding: 0 4% 50px 4%; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }

        .card {
            position: relative; border-radius: 12px; overflow: hidden;
            aspect-ratio: 2/3; cursor: pointer; transition: 0.4s var(--transition-smooth);
            background: #111;
        }
        .card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 15px 30px rgba(0,0,0,0.4), var(--glow-red); z-index: 2; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
        .card:hover .card-img { opacity: 1; }

        /* BADGES */
        .badge {
            position: absolute; top: 12px; left: 12px;
            padding: 4px 12px; font-size: 0.7rem; font-weight: 800;
            border-radius: 20px; text-transform: uppercase; letter-spacing: 1px;
            z-index: 5; box-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .badge.new { background: var(--accent-red); color: white; }
        .badge.vu { background: #2ecc71; color: black; }
        .badge.saga { background: #3498db; color: white; border: 1px solid rgba(255,255,255,0.3); }

        .badge-vl {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(to top, var(--accent-purple), rgba(155, 89, 182, 0.2));
            padding: 25px 10px 8px 10px;
            text-align: center; color: white; font-weight: 800; font-size: 0.8rem;
            text-shadow: 0 2px 4px black; z-index: 4;
            letter-spacing: 1px;
        }

        .badge-nas { position: absolute; bottom: 12px; right: 12px; width: 10px; height: 10px; background: #3498db; border-radius: 50%; box-shadow: 0 0 10px #3498db; z-index: 5; }

        .card-overlay {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 20px;
            opacity: 0;
            transition: 0.3s;
            background: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0.8) 30%, rgba(0,0,0,0) 100%);
            z-index: 10;
        }

        .card:hover .card-overlay { opacity: 1; }

        .card-title {
            font-weight: 700; font-size: 1.1rem; margin-bottom: 8px; text-align: center;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.9);
        }
        .card-meta {
            display: flex; justify-content: center; gap: 15px;
            font-size: 0.8rem; font-weight: 600; color: #ddd;
            text-shadow: 0 1px 2px rgba(0,0,0,0.9);
        }
        .meta-accent { color: var(--accent-red); }

        .hidden { display: none !important; }

        /* SAGA STYLE */
        .card.is-saga { border: 1px solid rgba(255,255,255,0.1); }
        .card.is-saga::before {
            content: ''; position: absolute; top: -5px; left: 10px; right: 10px; height: 10px;
            background: rgba(255,255,255,0.15); border-radius: 12px 12px 0 0; z-index: -1;
        }

        /* MODAL */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
            z-index: 2000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;
        }
        .modal-overlay.open { display: flex; opacity: 1; }
        .modal-content {
            background: var(--bg-surface); border: var(--glass-border); width: 90%; max-width: 1000px; max-height: 85vh;
            border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        .modal-header { padding: 20px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.5rem; font-weight: 800; color: white; }
        .modal-close { background: none; border: none; color: white; font-size: 2rem; cursor: pointer; transition: 0.2s; }
        .modal-close:hover { color: var(--accent-red); transform: rotate(90deg); }
        .modal-body { padding: 30px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; }

        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; } .logo { font-size: 1.5rem; }
            .btn-nav span { display: none; }
            .hero { height: 70vh; align-items: flex-end; padding-bottom: 60px; }
            .hero-title { font-size: 2.5rem; }
            .filter-bar { flex-direction: column; border-radius: 20px; padding: 15px; gap: 15px; }
            .search-wrapper, .filter-select, .toggles { width: 100%; justify-content: space-between; }
            .grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
        }
    </style>
</head>
<body>

<nav class="navbar" id="navbar">
    <a href="index.php" class="logo">WATCHD</a>
    <div class="nav-actions">
        <a href="modele/scanner.php" class="btn-nav btn-ghost">
            <i class="fas fa-sync-alt"></i> <span>Scanner</span>
        </a>
        <a href="pages/recherche.php" class="btn-nav btn-primary-red">
            <i class="fas fa-plus"></i> <span>Ajouter</span>
        </a>
    </div>
</nav>

<?php if ($heroMovie): ?>
    <?php
    $heroBackdrop = "https://image.tmdb.org/t/p/original" . $heroMovie['backdrop_path'];
    $heroNote = $heroMovie['note_tmdb'] ? round($heroMovie['note_tmdb'] * 10) : 0;
    $heroUrl = "pages/detailFilm.php?id=" . $heroMovie['tmdb_id'];
    $anneeHero = substr($heroMovie['date_sortie'] ?? '', 0, 4);
    ?>
    <div class="hero" style="background-image: url('<?= $heroBackdrop ?>');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title"><?= htmlspecialchars($heroMovie['titre']) ?></h1>
            <div class="hero-meta">
                <span class="match-score"><i class="fas fa-fire"></i> <?= $heroNote ?>%</span>
                <span><?= $anneeHero ?></span>
                <span>HD</span>
            </div>
            <p class="hero-desc"><?= htmlspecialchars($heroMovie['synopsis']) ?></p>
            <a href="<?= $heroUrl ?>" class="btn-nav btn-primary-red" style="display: inline-flex; padding: 12px 30px; font-size: 1rem;">
                <i class="fas fa-play"></i> Voir les détails
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="filter-container">
    <div class="filter-bar">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Titre, acteur..." oninput="filtrer()">
        </div>

        <select id="durationSelect" class="filter-select" onchange="filtrer()">
            <option value="9999">Toutes durées</option>
            <option value="90">Court (- 1h30)</option>
            <option value="120">Standard (- 2h)</option>
            <option value="150">Long (+ 2h)</option>
        </select>

        <select id="genreSelect" class="filter-select" onchange="filtrer()">
            <option value="">Tous Genres</option>
            <?php foreach ($tousLesGenres as $g): ?>
                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="toggles">
            <label class="toggle-btn active-red" id="labelAvoir">
                <input type="checkbox" id="checkAvoir" checked onchange="updateToggle(this, 'labelAvoir', 'active-red'); filtrer()">
                🍿
            </label>
            <label class="toggle-btn active-green" id="labelVus">
                <input type="checkbox" id="checkVus" checked onchange="updateToggle(this, 'labelVus', 'active-green'); filtrer()">
                ✅
            </label>
        </div>
    </div>
</div>

<div class="grid-section">
    <div class="grid">
        <?php foreach ($affichage as $item): ?>

            <?php if ($item['is_saga']): ?>
                <div class="card is-saga"
                     onclick="ouvrirSaga(<?= $item['id'] ?>)"
                     data-title="<?= strtolower(htmlspecialchars($item['titre'])) ?>"
                     data-genre="<?= strtolower(htmlspecialchars($item['genres'] ?? '')) ?>"
                     data-duree="9999"
                     data-vu="0">

                    <?php $imgSaga = $item['poster_path'] ? "https://image.tmdb.org/t/p/w500".$item['poster_path'] : "assets/no-poster.jpg"; ?>
                    <img src="<?= $imgSaga ?>" class="card-img" loading="lazy">
                    <div class="badge saga">SAGA</div>

                    <div class="card-overlay">
                        <div class="card-title"><?= htmlspecialchars($item['titre']) ?></div>
                        <div class="card-meta">
                            <span><?= count($item['films']) ?> Films</span>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <?php
                $film = $item;
                $img = $film['poster_path'] ? "https://image.tmdb.org/t/p/w500".$film['poster_path'] : "assets/no-poster.jpg";
                $isNas = !empty($film['chemin_fichier']);
                $isVu = ($film['statut'] === 'vu');
                $note = $film['note_tmdb'] ? round($film['note_tmdb']*10) : 0;
                $duree = $film['duree'] ? (int)$film['duree'] : 0;
                $dureeAffichage = ($duree > 0) ? floor($duree/60)."h ".sprintf('%02d', $duree%60) : "";
                $isVL = isExtended($film['titre']);
                ?>
                <a href="pages/detailFilm.php?id=<?= $film['tmdb_id'] ?>"
                   class="card"
                   onclick="memoriserScroll()"
                   data-title="<?= strtolower(htmlspecialchars($film['titre'])) ?>"
                   data-genre="<?= strtolower(htmlspecialchars($film['genres'] ?? '')) ?>"
                   data-duree="<?= $duree ?>"
                   data-vu="<?= $isVu ? '1' : '0' ?>">

                    <img src="<?= $img ?>" class="card-img" loading="lazy">

                    <?php if($isVu): ?>
                        <div class="badge vu">VU</div>
                    <?php else: ?>
                        <div class="badge new">NEW</div>
                    <?php endif; ?>

                    <?php if($isVL): ?>
                        <div class="badge-vl">VERSION LONGUE</div>
                    <?php endif; ?>

                    <?php if ($isNas): ?>
                        <div class="badge-nas" title="Sur le NAS"></div>
                    <?php endif; ?>

                    <div class="card-overlay">
                        <div class="card-title"><?= htmlspecialchars($film['titre']) ?></div>
                        <div class="card-meta">
                            <span class="meta-accent"><i class="fas fa-star"></i> <?= $note ?>%</span>
                            <span><?= $dureeAffichage ?></span>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>
</div>

<div id="sagaModal" class="modal-overlay" onclick="fermerSaga(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" id="sagaTitle">Saga</div>
            <button class="modal-close" onclick="fermerSaga(null)">&times;</button>
        </div>
        <div class="modal-body" id="sagaBody">
        </div>
    </div>
</div>

<script>
    const sagasData = <?= json_encode($sagasMap) ?>;

    // Navbar Scroll
    window.addEventListener('scroll', () => {
        document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 30);
    });

    // -----------------------------------------------------------
    // 1. GESTION DU LOCAL STORAGE (Persistance COMPLÈTE)
    // -----------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        // A. Restaurer les FILTRES
        const savedFilters = localStorage.getItem('watchd_filters');
        if (savedFilters) {
            const state = JSON.parse(savedFilters);
            document.getElementById('searchInput').value = state.search || '';
            document.getElementById('genreSelect').value = state.genre || '';
            document.getElementById('durationSelect').value = state.duration || '9999';

            const chkAvoir = document.getElementById('checkAvoir');
            chkAvoir.checked = state.avoir;
            updateToggle(chkAvoir, 'labelAvoir', 'active-red');

            const chkVus = document.getElementById('checkVus');
            chkVus.checked = state.vus;
            updateToggle(chkVus, 'labelVus', 'active-green');

            filtrer();
        }

        // B. Restaurer la SAGA (Si on était dedans)
        const openSagaId = localStorage.getItem('watchd_open_saga');
        if (openSagaId) {
            ouvrirSaga(openSagaId);
        }

        // C. Restaurer le SCROLL (En dernier, après que le contenu soit prêt)
        const savedScroll = localStorage.getItem('watchd_scroll_pos');
        if (savedScroll) {
            // Petit délai pour laisser le navigateur rendre la grille
            setTimeout(() => {
                window.scrollTo(0, parseInt(savedScroll));
            }, 50);
        }
    });

    // Fonction appelée quand on clique sur une carte de film
    function memoriserScroll() {
        localStorage.setItem('watchd_scroll_pos', window.scrollY);
    }

    // Sauvegarde filtres
    function saveFilterState() {
        const state = {
            search: document.getElementById('searchInput').value,
            genre: document.getElementById('genreSelect').value,
            duration: document.getElementById('durationSelect').value,
            avoir: document.getElementById('checkAvoir').checked,
            vus: document.getElementById('checkVus').checked
        };
        localStorage.setItem('watchd_filters', JSON.stringify(state));
    }

    // -----------------------------------------------------------
    // 2. MOTEUR DE FILTRES
    // -----------------------------------------------------------

    function updateToggle(checkbox, labelId, activeClass) {
        const label = document.getElementById(labelId);
        if (checkbox.checked) label.classList.add(activeClass);
        else label.classList.remove(activeClass);
    }

    function filtrer() {
        saveFilterState();

        const query = document.getElementById('searchInput').value.toLowerCase();
        const genre = document.getElementById('genreSelect').value.toLowerCase();
        const maxDuree = parseInt(document.getElementById('durationSelect').value);

        const wantAvoir = document.getElementById('checkAvoir').checked;
        const wantVus = document.getElementById('checkVus').checked;
        const showAll = (!wantAvoir && !wantVus);

        document.querySelectorAll('.grid > .card').forEach(card => {
            const title = card.getAttribute('data-title');
            const g = card.getAttribute('data-genre');
            const d = parseInt(card.getAttribute('data-duree'));
            const isVu = card.getAttribute('data-vu') === '1';

            let visible = true;
            if (!title.includes(query) && !g.includes(query)) visible = false;
            if (genre && !g.includes(genre)) visible = false;

            if (maxDuree === 150) {
                if (d < 120 && d !== 0 && d !== 9999) visible = false;
            } else {
                if (d > maxDuree) visible = false;
            }

            if (!showAll) {
                if (isVu && !wantVus) visible = false;
                if (!isVu && !wantAvoir) visible = false;
            }

            card.classList.toggle('hidden', !visible);
        });
    }

    // -----------------------------------------------------------
    // 3. GESTION SAGA (Mémoire incluse)
    // -----------------------------------------------------------

    function ouvrirSaga(id) {
        const data = sagasData[id];
        if (!data) return;

        // 1. Sauvegarder qu'on est dans cette saga
        localStorage.setItem('watchd_open_saga', id);
        // On sauvegarde aussi le scroll principal pour le retour futur
        if (window.scrollY > 0) localStorage.setItem('watchd_scroll_pos', window.scrollY);

        document.getElementById('sagaTitle').innerText = data.titre;
        const body = document.getElementById('sagaBody');
        body.innerHTML = '';

        data.films.sort((a, b) => (a.date_sortie > b.date_sortie) ? 1 : -1);

        data.films.forEach(film => {
            const isVu = (film.statut === 'vu');
            const isVL = /version longue|extended|director/i.test(film.titre);
            const img = film.poster_path ? "https://image.tmdb.org/t/p/w300"+film.poster_path : "assets/no-poster.jpg";
            const annee = film.date_sortie ? film.date_sortie.substring(0,4) : '';

            // Calcul Note
            const note = film.note_tmdb ? Math.round(film.note_tmdb * 10) : 0;

            // Calcul Durée
            const d = parseInt(film.duree) || 0;
            let dureeFmt = "";
            if (d > 0) {
                const h = Math.floor(d / 60);
                const m = d % 60;
                dureeFmt = h + "h " + (m < 10 ? '0'+m : m);
            }

            // On ajoute 'memoriserScroll()' au click aussi ici, bien que moins critique dans une modale
            const html = `
                    <a href="pages/detailFilm.php?id=${film.tmdb_id}" class="card" style="aspect-ratio:2/3; text-decoration:none;">
                        <img src="${img}" class="card-img" style="opacity:${isVu ? 0.6 : 1}">

                        ${isVu ? '<div class="badge vu">VU</div>' : '<div class="badge new">NEW</div>'}
                        ${isVL ? '<div class="badge-vl">VERSION LONGUE</div>' : ''}

                        <div class="card-overlay">
                            <div class="card-title">${film.titre}</div>
                            <div class="card-meta">
                                <span class="meta-accent"><i class="fas fa-star"></i> ${note}%</span>
                                <span>${annee}</span>
                                <span>${dureeFmt}</span>
                            </div>
                        </div>
                    </a>
                `;
            body.innerHTML += html;
        });

        document.getElementById('sagaModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function fermerSaga(e) {
        if (e && e.target !== e.currentTarget) return;

        // On oublie la saga ouverte
        localStorage.removeItem('watchd_open_saga');

        document.getElementById('sagaModal').classList.remove('open');
        document.body.style.overflow = 'auto';
    }
</script>
</body>
</html>