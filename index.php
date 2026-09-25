<?php
// index.php - Collection
require_once __DIR__ . '/modele/connexionBd.php';
require_once __DIR__ . '/pages/layout.php';

$sql = "SELECT T.*, s.nom AS saga_nom, s.poster_path AS saga_poster, s.backdrop_path AS saga_backdrop
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
$nbVus = 0;

foreach ($allFilms as $f) {
    if ($f['statut'] === 'vu') $nbVus++;
    foreach (array_filter(array_map('trim', explode(',', $f['genres'] ?? ''))) as $g) $genres[$g] = true;

    if (!empty($f['saga_id'])) {
        $sId = $f['saga_id'];
        if (!isset($sagas[$sId])) {
            $sagas[$sId] = [
                'id' => $sId,
                'nom' => $f['saga_nom'],
                'poster_path' => $f['saga_poster'] ?: $f['poster_path'],
                'backdrop_path' => $f['saga_backdrop'] ?: $f['backdrop_path'],
                'films' => [],
            ];
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
$nbAVoir = count($allFilms) - $nbVus;

// Hero (PC) : jusqu'à 5 films, en priorité ceux qui restent à voir
$avecImage = array_filter($allFilms, fn($f) => $f['backdrop_path'] && $f['synopsis']);
$aVoirAvecImage = array_filter($avecImage, fn($f) => $f['statut'] === 'a_voir');
$heros = count($aVoirAvecImage) >= 3 ? $aVoirAvecImage : $avecImage;
shuffle($heros);
$heros = array_slice($heros, 0, 5);

// Attributs utilisés par le filtre JS
function attrsFiltre($titres, $genres, $duree, $aVoir, $vus) {
    return sprintf('data-titre="%s" data-genres="%s" data-duree="%d" data-avoir="%d" data-vus="%d"',
        e(mb_strtolower(implode(' | ', $titres))), e(mb_strtolower($genres)), $duree, $aVoir, $vus);
}

function carteFilm($f) {
    $vu = $f['statut'] === 'vu';
    $img = imageTmdb($f['poster_path']);
    $meta = array_filter([annee($f['date_sortie']), dureeFmt($f['duree'])]);
    $note = noteFmt($f['note_tmdb']);
    ?>
    <a href="pages/detailFilm.php?id=<?= (int)$f['tmdb_id'] ?>" class="card<?= $vu ? ' is-vu' : '' ?>" data-vt
       <?= attrsFiltre([$f['titre']], $f['genres'] ?? '', $f['duree'], !$vu, $vu) ?>>
        <div class="poster">
            <?php if ($img): imgAffiche($img); else: ?><span class="poster-empty"><?= e($f['titre']) ?></span><?php endif; ?>
            <?php if ($note): ?><span class="badge badge-note"><i class="fas fa-star"></i> <?= $note ?></span><?php endif; ?>
            <?php if ($vu): ?><span class="badge badge-vu" title="Vu"><i class="fas fa-check"></i></span><?php endif; ?>
            <?php if (isExtended($f['titre'])): ?><span class="badge badge-vl" title="Version longue">VL</span><?php endif; ?>
        </div>
        <div class="card-title"><?= e($f['titre']) ?></div>
        <div class="card-meta"><?= e(implode(' · ', $meta)) ?></div>
    </a>
<?php }

enTete('', 'Watchd', 'collection', [
    'ambiance' => $heros ? imageTmdb($heros[0]['backdrop_path'], 'w300') : null,
]);
?>

<?php if ($heros): ?>
    <section class="hero" aria-label="À l'affiche">
        <?php foreach ($heros as $i => $h): ?>
            <?php $meta = array_filter([annee($h['date_sortie']), dureeFmt($h['duree']), str_replace(',', ' ·', $h['genres'] ?? '')]); ?>
            <article class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" data-ambiance="<?= e(imageTmdb($h['backdrop_path'], 'w300')) ?>">
                <div class="hero-bg" style="--img: url('<?= e(imageTmdb($h['backdrop_path'], 'w1280')) ?>')"></div>
                <div class="hero-content">
                    <p class="eyebrow"><?= $h['statut'] === 'a_voir' ? 'À voir ce soir' : 'Déjà vu, à revoir' ?></p>
                    <h2 class="hero-title"><?= e($h['titre']) ?></h2>
                    <p class="hero-meta">
                        <?php if (noteFmt($h['note_tmdb'])): ?><span class="pill pill-note"><i class="fas fa-star"></i> <?= noteFmt($h['note_tmdb']) ?></span><?php endif; ?>
                        <span><?= e(implode('  ·  ', $meta)) ?></span>
                    </p>
                    <p class="hero-desc"><?= e($h['synopsis']) ?></p>
                    <a href="pages/detailFilm.php?id=<?= (int)$h['tmdb_id'] ?>" class="btn btn-primary">
                        <i class="fas fa-play"></i> Voir la fiche
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (count($heros) > 1): ?>
            <div class="hero-dots">
                <?php foreach ($heros as $i => $h): ?>
                    <button type="button" class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>" aria-label="Film <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!$allFilms): ?>
    <div class="empty">
        <i class="fas fa-film"></i>
        <strong>Ta collection est vide</strong>
        Commence par ajouter les films que tu veux voir.<br>
        <a href="pages/recherche.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un film</a>
    </div>
<?php else: ?>
    <section class="intro<?= $heros ? ' has-hero' : '' ?>">
        <p class="eyebrow">Ma collection</p>
        <h1 class="intro-title">On regarde quoi<br>ce soir ?</h1>
        <div class="intro-stats">
            <span class="stat"><i class="fas fa-bookmark"></i><b><?= $nbAVoir ?></b> à voir</span>
            <span class="stat"><i class="fas fa-check"></i><b><?= $nbVus ?></b> vus</span>
            <?php if ($sagas): ?><span class="stat"><i class="fas fa-layer-group"></i><b><?= count($sagas) ?></b> sagas</span><?php endif; ?>
        </div>
    </section>

    <div class="toolbar">
        <label class="search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="q" placeholder="Film, saga, genre…" autocomplete="off" enterkeyhint="search" aria-label="Rechercher dans ma collection">
        </label>
        <button type="button" class="filter-btn" id="ouvrirFiltres" aria-label="Filtres">
            <i class="fas fa-sliders"></i>
            <span class="filter-badge" id="nbFiltres" hidden></span>
        </button>
        <div class="segmented" id="statut" role="group" aria-label="Statut">
            <span class="seg-indicator"></span>
            <button type="button" data-statut="tout">Tout</button>
            <button type="button" data-statut="avoir">À voir</button>
            <button type="button" data-statut="vus">Vus</button>
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
                <div class="stack">
                    <div class="poster">
                        <?php if ($img): imgAffiche($img); else: ?><span class="poster-empty"><?= e($s['nom']) ?></span><?php endif; ?>
                        <span class="badge badge-saga"><i class="fas fa-layer-group"></i> <?= $nb ?></span>
                        <?php if (!$aVoir): ?><span class="badge badge-vu" title="Tous vus"><i class="fas fa-check"></i></span><?php endif; ?>
                    </div>
                </div>
                <div class="card-title"><?= e($s['nom']) ?></div>
                <div class="card-meta">Saga · <?= $nb ?> films<?= $vus && $aVoir ? " · $vus vu" . ($vus > 1 ? 's' : '') : '' ?></div>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="empty" id="vide" hidden>
        <i class="fas fa-ghost"></i>
        <strong>Rien par ici</strong>
        Aucun film ne correspond à ces filtres.<br>
        <button type="button" class="btn btn-glass" id="toutEffacer">Effacer les filtres</button>
    </div>

    <?php foreach ($sagas as $s): ?>
        <?php
        usort($s['films'], fn($a, $b) => strcmp($a['date_sortie'] ?? '', $b['date_sortie'] ?? ''));
        $vus = count(array_filter($s['films'], fn($f) => $f['statut'] === 'vu'));
        ?>
        <template id="saga-<?= (int)$s['id'] ?>" data-nom="<?= e($s['nom']) ?>" data-backdrop="<?= e(imageTmdb($s['backdrop_path'], 'w1280')) ?>"
                  data-nb="<?= count($s['films']) ?>" data-vus="<?= $vus ?>">
            <?php foreach ($s['films'] as $f) carteFilm($f); ?>
        </template>
    <?php endforeach; ?>

    <dialog class="sheet" id="sagaDialog" aria-labelledby="sagaTitre">
        <header class="saga-head" id="sagaHead">
            <div class="sheet-grab"></div>
            <button type="button" class="icon-btn" aria-label="Fermer" onclick="sagaDialog.close()"><i class="fas fa-xmark"></i></button>
            <p class="eyebrow">Saga</p>
            <h2 class="saga-title" id="sagaTitre"></h2>
            <p class="saga-info" id="sagaInfo"></p>
            <div class="progress"><span id="sagaProgression"></span></div>
        </header>
        <div class="grid" id="sagaGrille"></div>
    </dialog>

    <dialog class="sheet" id="filtresDialog" aria-labelledby="filtresTitre">
        <div class="sheet-grab"></div>
        <div class="sheet-head">
            <h2 id="filtresTitre">Filtres</h2>
            <button type="button" class="icon-btn" aria-label="Fermer" onclick="filtresDialog.close()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="sheet-body">
            <p class="sheet-label">Durée</p>
            <div class="chips">
                <button type="button" class="chip" data-f="duree" data-v="court">Moins d'1h30</button>
                <button type="button" class="chip" data-f="duree" data-v="standard">Moins de 2h</button>
                <button type="button" class="chip" data-f="duree" data-v="long">Plus de 2h</button>
            </div>
            <p class="sheet-label">Genre</p>
            <div class="chips">
                <?php foreach ($genres as $g): ?>
                    <button type="button" class="chip" data-f="genre" data-v="<?= e(mb_strtolower($g)) ?>"><?= e($g) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sheet-foot">
            <button type="button" class="btn btn-glass" id="reinitFiltres">Réinitialiser</button>
            <button type="button" class="btn btn-primary" onclick="filtresDialog.close()">Voir <span id="nbResultats"></span></button>
        </div>
    </dialog>

    <script>
        const STATUTS = ['tout', 'avoir', 'vus'];
        const st = Object.assign({ q: '', statut: 'tout', duree: '', genre: '' }, store.get('watchd_filtres_v2'));
        const cartes = [...document.querySelectorAll('#grille > .card')];
        const chips = [...document.querySelectorAll('#filtresDialog .chip')];
        const segment = document.getElementById('statut');
        const inputQ = document.getElementById('q');
        const sagaDialog = document.getElementById('sagaDialog');
        const filtresDialog = document.getElementById('filtresDialog');
        const dureeOk = { court: d => d <= 90, standard: d => d <= 120, long: d => d > 120 };

        function appliquer() {
            segment.style.setProperty('--i', STATUTS.indexOf(st.statut));
            segment.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b.dataset.statut === st.statut));
            chips.forEach(b => b.setAttribute('aria-pressed', st[b.dataset.f] === b.dataset.v));

            const q = st.q.trim().toLowerCase();
            let n = 0;
            cartes.forEach(c => {
                const d = c.dataset, duree = +d.duree;
                const ok = (!q || d.titre.includes(q) || d.genres.includes(q))
                    && (!st.genre || d.genres.split(', ').includes(st.genre))
                    && (!st.duree || !duree || dureeOk[st.duree](duree))
                    && (st.statut === 'tout' || (st.statut === 'avoir' ? d.avoir : d.vus) === '1');
                c.hidden = !ok;
                if (ok) n++;
            });

            const nbFiltres = (st.duree ? 1 : 0) + (st.genre ? 1 : 0);
            const badge = document.getElementById('nbFiltres');
            badge.textContent = nbFiltres;
            badge.hidden = !nbFiltres;
            document.getElementById('ouvrirFiltres').classList.toggle('is-active', nbFiltres > 0);

            const libelle = n + (n > 1 ? ' titres' : ' titre');
            document.getElementById('compteur').textContent = libelle;
            document.getElementById('nbResultats').textContent = libelle;
            document.getElementById('vide').hidden = n > 0;
            store.set('watchd_filtres_v2', st);
        }

        segment.addEventListener('click', e => {
            const b = e.target.closest('button');
            if (b) { st.statut = b.dataset.statut; appliquer(); }
        });
        filtresDialog.addEventListener('click', e => {
            if (e.target === filtresDialog) return filtresDialog.close();
            const b = e.target.closest('.chip');
            if (b) { st[b.dataset.f] = st[b.dataset.f] === b.dataset.v ? '' : b.dataset.v; appliquer(); }
        });
        document.getElementById('ouvrirFiltres').addEventListener('click', () => filtresDialog.showModal());
        document.getElementById('reinitFiltres').addEventListener('click', () => { st.duree = st.genre = ''; appliquer(); });
        document.getElementById('toutEffacer').addEventListener('click', () => {
            Object.assign(st, { q: '', statut: 'tout', duree: '', genre: '' });
            inputQ.value = '';
            appliquer();
        });
        inputQ.addEventListener('input', () => { st.q = inputQ.value; appliquer(); });

        // Sagas
        function ouvrirSaga(id) {
            const t = document.getElementById('saga-' + id);
            if (!t) return;
            const d = t.dataset;
            document.getElementById('sagaTitre').textContent = d.nom;
            document.getElementById('sagaInfo').textContent = `${d.nb} films · ${d.vus} vu${d.vus > 1 ? 's' : ''}`;
            document.getElementById('sagaHead').style.setProperty('--img', d.backdrop ? `url("${d.backdrop}")` : 'none');
            const barre = document.getElementById('sagaProgression');
            barre.style.width = 0;
            const grille = document.getElementById('sagaGrille');
            grille.replaceChildren(t.content.cloneNode(true));
            sagaDialog.showModal();
            grille.scrollTop = 0;
            requestAnimationFrame(() => requestAnimationFrame(() => barre.style.width = (d.vus / d.nb * 100) + '%'));
            store.set('watchd_saga', id);
        }
        document.getElementById('grille').addEventListener('click', e => {
            const s = e.target.closest('[data-saga]');
            if (s) ouvrirSaga(s.dataset.saga);
        });
        sagaDialog.addEventListener('click', e => { if (e.target === sagaDialog) sagaDialog.close(); });
        sagaDialog.addEventListener('close', () => store.del('watchd_saga'));

        // Hero : carrousel, la barre de progression active déclenche le film suivant
        const slides = [...document.querySelectorAll('.hero-slide')];
        const dots = [...document.querySelectorAll('.hero-dot')];
        const ambiance = document.getElementById('ambiance');
        let courant = 0;
        function montrer(i) {
            slides[courant].classList.remove('is-active');
            dots[courant].classList.remove('is-active');
            courant = (i + slides.length) % slides.length;
            slides[courant].classList.add('is-active');
            dots[courant].classList.add('is-active');
            if (ambiance) ambiance.style.backgroundImage = `url("${slides[courant].dataset.ambiance}")`;
        }
        dots.forEach((d, i) => {
            d.addEventListener('click', () => montrer(i));
            d.addEventListener('animationend', () => montrer(i + 1));
        });

        // Mémoire du scroll : sauvegardé en ouvrant une fiche, restauré au retour
        document.addEventListener('click', e => {
            if (e.target.closest('a.card')) store.set('watchd_scroll', scrollY);
        });
        // Retour arrière depuis une fiche modifiée : on recharge pour afficher le nouveau statut
        addEventListener('pageshow', e => {
            if (e.persisted && store.get('watchd_modifie')) { store.del('watchd_modifie'); location.reload(); }
        });

        store.del('watchd_modifie');
        inputQ.value = st.q;
        appliquer();
        const saga = store.get('watchd_saga');
        if (saga) ouvrirSaga(saga);
        const y = store.get('watchd_scroll');
        if (y) { requestAnimationFrame(() => scrollTo(0, y)); store.del('watchd_scroll'); }
    </script>
<?php endif; ?>

<?php piedDePage('', 'collection'); ?>
