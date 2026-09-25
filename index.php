<?php
// index.php - Collection
require_once __DIR__ . '/modele/connexionBd.php';
require_once __DIR__ . '/pages/layout.php';

$sql = "SELECT T.*, s.nom AS saga_nom, s.poster_path AS saga_poster
        FROM (
            SELECT *, 'a_voir' AS statut FROM films_a_voir
            UNION ALL
            SELECT *, 'vu' AS statut FROM films_vus
        ) AS T
        LEFT JOIN sagas s ON T.saga_id = s.id
        ORDER BY T.date_ajout DESC";
$allFilms = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Groupement : un film seul ou une saga (à la position de son film le plus récent)
$affichage = [];
$sagas = [];
$genres = [];
$heroCandidats = [];

foreach ($allFilms as $f) {
    foreach (array_filter(array_map('trim', explode(',', $f['genres'] ?? ''))) as $g) $genres[$g] = true;
    if (!empty($f['backdrop_path']) && !empty($f['synopsis'])) $heroCandidats[] = $f;

    if (!empty($f['saga_id'])) {
        $sId = $f['saga_id'];
        if (!isset($sagas[$sId])) {
            $sagas[$sId] = ['id' => $sId, 'nom' => $f['saga_nom'], 'poster_path' => $f['saga_poster'] ?: $f['poster_path'], 'films' => []];
            $affichage[] = ['saga' => $sId];
        }
        $sagas[$sId]['films'][] = $f;
    } else {
        $affichage[] = ['film' => $f];
    }
}
// Une saga avec un seul film dans la collection s'affiche comme un film normal
foreach ($affichage as &$item) {
    if (isset($item['saga']) && count($sagas[$item['saga']]['films']) === 1) {
        $film = $sagas[$item['saga']]['films'][0];
        unset($sagas[$item['saga']]);
        $item = ['film' => $film];
    }
}
unset($item);

$genres = array_keys($genres);
sort($genres);
$hero = $heroCandidats ? $heroCandidats[array_rand($heroCandidats)] : null;

// Attributs utilisés par le filtre JS
function attrsFiltre($titres, $genres, $duree, $aVoir, $vus) {
    return sprintf('data-titre="%s" data-genres="%s" data-duree="%d" data-avoir="%d" data-vus="%d"',
        e(mb_strtolower(implode(' | ', $titres))), e(mb_strtolower($genres)), $duree, $aVoir, $vus);
}

function carteFilm($f) {
    $vu = $f['statut'] === 'vu';
    $img = imageTmdb($f['poster_path']);
    $meta = array_filter([substr($f['date_sortie'] ?? '', 0, 4), dureeFmt($f['duree'])]);
    ?>
    <a href="pages/detailFilm.php?id=<?= (int)$f['tmdb_id'] ?>" class="card<?= $vu ? ' is-vu' : '' ?>"
       <?= attrsFiltre([$f['titre']], $f['genres'] ?? '', $f['duree'], !$vu, $vu) ?>>
        <div class="poster">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
            <?php else: ?><span class="poster-empty"><?= e($f['titre']) ?></span><?php endif; ?>
            <?php if ($vu): ?><span class="badge badge-vu" title="Vu"><i class="fas fa-check"></i></span><?php endif; ?>
            <?php if (isExtended($f['titre'])): ?><span class="badge badge-vl" title="Version longue">VL</span><?php endif; ?>
        </div>
        <div class="card-title"><?= e($f['titre']) ?></div>
        <div class="card-meta"><?= e(implode(' · ', $meta)) ?></div>
    </a>
<?php }

enTete('', 'Watchd', 'collection');
?>

<?php if ($hero): ?>
    <section class="hero" style="background-image: url('<?= e(imageTmdb($hero['backdrop_path'], 'w1280')) ?>')">
        <div class="hero-content">
            <h1 class="hero-title"><?= e($hero['titre']) ?></h1>
            <p class="hero-meta">
                <?php if ($hero['note_tmdb']): ?><span class="note"><?= round($hero['note_tmdb'] * 10) ?>%</span> · <?php endif; ?>
                <?= e(substr($hero['date_sortie'] ?? '', 0, 4)) ?>
                <?php if ($hero['duree']): ?> · <?= dureeFmt($hero['duree']) ?><?php endif; ?>
            </p>
            <p class="hero-desc"><?= e($hero['synopsis']) ?></p>
            <a href="pages/detailFilm.php?id=<?= (int)$hero['tmdb_id'] ?>" class="btn btn-primary">
                <i class="fas fa-circle-info"></i> Voir la fiche
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if (!$allFilms): ?>
    <p class="empty">Ta collection est vide. <a href="pages/recherche.php">Ajoute ton premier film</a>.</p>
<?php else: ?>
    <div class="toolbar">
        <label class="search">
            <i class="fas fa-search"></i>
            <input type="search" id="q" placeholder="Rechercher dans ma collection" autocomplete="off" aria-label="Rechercher dans ma collection">
        </label>
        <div class="chips" id="filtres">
            <button type="button" class="chip" data-f="avoir"><i class="fas fa-bookmark"></i> À voir</button>
            <button type="button" class="chip" data-f="vus"><i class="fas fa-check"></i> Vus</button>
            <span class="chip-sep"></span>
            <button type="button" class="chip" data-f="duree" data-v="court">Moins d'1h30</button>
            <button type="button" class="chip" data-f="duree" data-v="standard">Moins de 2h</button>
            <button type="button" class="chip" data-f="duree" data-v="long">Plus de 2h</button>
        </div>
        <div class="chips">
            <?php foreach ($genres as $g): ?>
                <button type="button" class="chip" data-f="genre" data-v="<?= e(mb_strtolower($g)) ?>"><?= e($g) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="count" id="compteur"></p>

    <div class="grid" id="grille">
        <?php foreach ($affichage as $item): ?>
            <?php if (isset($item['film'])): carteFilm($item['film']); continue; endif; ?>
            <?php
            $s = $sagas[$item['saga']];
            $nb = count($s['films']);
            $aVoir = $vus = 0;
            $genresSaga = [];
            foreach ($s['films'] as $f) {
                $f['statut'] === 'vu' ? $vus++ : $aVoir++;
                $genresSaga = array_merge($genresSaga, array_map('trim', explode(',', $f['genres'] ?? '')));
            }
            $img = imageTmdb($s['poster_path']);
            ?>
            <button type="button" class="card is-saga<?= $aVoir ? '' : ' is-vu' ?>" data-saga="<?= (int)$s['id'] ?>"
                <?= attrsFiltre(array_merge([$s['nom']], array_column($s['films'], 'titre')), implode(', ', array_unique(array_filter($genresSaga))), 0, $aVoir > 0, $vus > 0) ?>>
                <div class="poster">
                    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
                    <?php else: ?><span class="poster-empty"><?= e($s['nom']) ?></span><?php endif; ?>
                    <span class="badge badge-saga"><i class="fas fa-layer-group"></i> <?= $nb ?></span>
                    <?php if (!$aVoir): ?><span class="badge badge-vu" title="Tous vus"><i class="fas fa-check"></i></span><?php endif; ?>
                </div>
                <div class="card-title"><?= e($s['nom']) ?></div>
                <div class="card-meta">Saga · <?= $nb ?> film<?= $nb > 1 ? 's' : '' ?><?= $vus && $aVoir ? " · $vus vu" . ($vus > 1 ? 's' : '') : '' ?></div>
            </button>
        <?php endforeach; ?>
    </div>

    <p class="empty" id="vide" hidden>Aucun film ne correspond à ces filtres.</p>

    <?php foreach ($sagas as $s): ?>
        <?php usort($s['films'], fn($a, $b) => strcmp($a['date_sortie'] ?? '', $b['date_sortie'] ?? '')); ?>
        <template id="saga-<?= (int)$s['id'] ?>" data-nom="<?= e($s['nom']) ?>">
            <?php foreach ($s['films'] as $f) carteFilm($f); ?>
        </template>
    <?php endforeach; ?>

    <dialog class="sheet" id="sagaDialog" aria-labelledby="sagaTitre">
        <div class="sheet-head">
            <h2 class="sheet-title" id="sagaTitre"></h2>
            <button type="button" class="icon-btn" aria-label="Fermer" onclick="sagaDialog.close()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="grid" id="sagaGrille"></div>
    </dialog>

    <script>
        // localStorage peut être indisponible (navigation privée...) : on ne plante jamais dessus
        const store = {
            get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } },
            set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch {} },
            del(k) { try { localStorage.removeItem(k); } catch {} },
        };

        const st = Object.assign({ q: '', avoir: false, vus: false, duree: '', genre: '' }, store.get('watchd_filtres'));
        const cartes = [...document.querySelectorAll('#grille > .card')];
        const chips = [...document.querySelectorAll('.chip')];
        const inputQ = document.getElementById('q');
        const sagaDialog = document.getElementById('sagaDialog');

        const dureeOk = { court: d => d <= 90, standard: d => d <= 120, long: d => d > 120 };

        function appliquer() {
            chips.forEach(b => {
                const f = b.dataset.f;
                b.setAttribute('aria-pressed', f === 'avoir' || f === 'vus' ? st[f] : st[f] === b.dataset.v);
            });

            const q = st.q.trim().toLowerCase();
            const tousStatuts = !st.avoir && !st.vus;
            let n = 0;
            cartes.forEach(c => {
                const d = c.dataset, duree = +d.duree;
                const ok = (!q || d.titre.includes(q) || d.genres.includes(q))
                    && (!st.genre || d.genres.split(', ').includes(st.genre))
                    && (!st.duree || !duree || dureeOk[st.duree](duree))
                    && (tousStatuts || (st.avoir && d.avoir === '1') || (st.vus && d.vus === '1'));
                c.hidden = !ok;
                if (ok) n++;
            });
            document.getElementById('compteur').textContent = n + (n > 1 ? ' éléments' : ' élément');
            document.getElementById('vide').hidden = n > 0;
            store.set('watchd_filtres', st);
        }

        document.getElementById('filtres').parentElement.addEventListener('click', e => {
            const b = e.target.closest('.chip');
            if (!b) return;
            const f = b.dataset.f;
            if (f === 'avoir' || f === 'vus') st[f] = !st[f];
            else st[f] = st[f] === b.dataset.v ? '' : b.dataset.v;
            appliquer();
        });
        inputQ.addEventListener('input', () => { st.q = inputQ.value; appliquer(); });

        // Sagas
        function ouvrirSaga(id) {
            const t = document.getElementById('saga-' + id);
            if (!t) return;
            document.getElementById('sagaTitre').textContent = t.dataset.nom;
            document.getElementById('sagaGrille').replaceChildren(t.content.cloneNode(true));
            sagaDialog.showModal();
            store.set('watchd_saga', id);
        }
        document.getElementById('grille').addEventListener('click', e => {
            const s = e.target.closest('[data-saga]');
            if (s) ouvrirSaga(s.dataset.saga);
        });
        sagaDialog.addEventListener('click', e => { if (e.target === sagaDialog) sagaDialog.close(); });
        sagaDialog.addEventListener('close', () => store.del('watchd_saga'));

        // Mémoire du scroll : sauvegardé en ouvrant une fiche, restauré au retour
        document.addEventListener('click', e => {
            if (e.target.closest('a.card')) store.set('watchd_scroll', scrollY);
        });

        inputQ.value = st.q;
        appliquer();
        const saga = store.get('watchd_saga');
        if (saga) ouvrirSaga(saga);
        const y = store.get('watchd_scroll');
        if (y) { requestAnimationFrame(() => scrollTo(0, y)); store.del('watchd_scroll'); }
    </script>
<?php endif; ?>

<?php piedDePage('', 'collection'); ?>
