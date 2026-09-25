<?php
// pages/detailFilm.php - Fiche d'un film (un seul écran, sans scroll vertical)
require_once __DIR__ . '/../modele/connexionBd.php';
require_once __DIR__ . '/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ../index.php'); exit; }

$stmt = $pdo->prepare("SELECT *, 'a_voir' AS statut FROM films_a_voir WHERE tmdb_id = ?
                       UNION ALL SELECT *, 'vu' AS statut FROM films_vus WHERE tmdb_id = ?");
$stmt->execute([$id, $id]);
$local = $stmt->fetch() ?: null;
$statut = $local['statut'] ?? 'aucun';

$url = "https://api.themoviedb.org/3/movie/$id?api_key=" . API_KEY
     . "&language=fr-FR&append_to_response=credits,images,videos&include_image_language=fr,en,null&include_video_language=fr,en";
$json = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
$api = $json ? json_decode($json, true) : [];
if (isset($api['status_code'])) $api = [];

if (!$api && !$local) {
    http_response_code(404);
    enTete('../', 'Film introuvable', null);
    echo '<div class="empty"><i class="fas fa-film"></i><strong>Film introuvable</strong><a href="../index.php" class="btn btn-glass">Retour à la collection</a></div>';
    piedDePage('../', null);
    exit;
}

// Le titre local peut contenir « (Version Longue) », on le préfère
$titre = $local['titre'] ?? $api['title'] ?? 'Titre inconnu';

// Logo : français en priorité, puis anglais, puis le premier
$logo = null;
foreach (['fr', 'en', null] as $langue) {
    foreach ($api['images']['logos'] ?? [] as $l) {
        if ($langue === null || ($l['iso_639_1'] ?? '') === $langue) { $logo = $l; break 2; }
    }
}

// Bande-annonce YouTube : française en priorité
$trailer = null;
foreach ($api['videos']['results'] ?? [] as $v) {
    if ($v['site'] !== 'YouTube' || !in_array($v['type'], ['Trailer', 'Teaser'])) continue;
    if (!$trailer || ($v['iso_639_1'] === 'fr' && $trailer['iso_639_1'] !== 'fr')) $trailer = $v;
}

$poster = imageTmdb($api['poster_path'] ?? $local['poster_path'] ?? null, 'w780');
$backdrop = imageTmdb($api['backdrop_path'] ?? $local['backdrop_path'] ?? null, 'original');
$duree = dureeFmt($api['runtime'] ?? $local['duree'] ?? 0);
$dateSortie = $api['release_date'] ?? $local['date_sortie'] ?? '';
$note = noteFmt($api['vote_average'] ?? $local['note_tmdb'] ?? 0);
$synopsis = ($api['overview'] ?? '') ?: ($local['synopsis'] ?? '') ?: 'Aucun résumé disponible.';
$tagline = ($api['tagline'] ?? '') ?: ($local['tagline'] ?? '');
$genres = isset($api['genres'])
    ? array_column($api['genres'], 'name')
    : array_filter(array_map('trim', explode(',', $local['genres'] ?? '')));
$cast = array_slice($api['credits']['cast'] ?? [], 0, 15);
$realisateurs = array_column(array_filter($api['credits']['crew'] ?? [], fn($c) => $c['job'] === 'Director'), 'name');
$titreOriginal = $api['original_title'] ?? $local['titre_original'] ?? '';

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$ts = $dateSortie ? strtotime($dateSortie) : false;
$sortieLongue = $ts ? date('j', $ts) . ' ' . $mois[date('n', $ts) - 1] . ' ' . date('Y', $ts) : '';

$images = ($poster ? "--poster: url('$poster');" : '') . ($backdrop ? "--backdrop: url('$backdrop');" : '');

// Visible seulement pour certains statuts (mis à jour en JS après une action)
function si($statuts, $statut) {
    return 'data-si="' . $statuts . '"' . (in_array($statut, explode(' ', $statuts)) ? '' : ' hidden');
}

function acteur($a, $avecRole) { ?>
    <div class="actor">
        <?php if ($a['profile_path']): ?>
            <img src="<?= e(imageTmdb($a['profile_path'], 'w185')) ?>" alt="" loading="lazy">
        <?php else: ?>
            <span class="avatar"><i class="fas fa-user"></i></span>
        <?php endif; ?>
        <div class="actor-name"><?= e($a['name']) ?></div>
        <?php if ($avecRole && !empty($a['character'])): ?><div class="actor-role"><?= e($a['character']) ?></div><?php endif; ?>
    </div>
<?php }

enTete('../', $titre . ' - Watchd', null, ['barre' => false, 'classe' => 'page-detail']);
?>

<main class="detail" id="detail" data-statut="<?= $statut ?>" style="<?= e($images) ?>">
    <div class="detail-bg" aria-hidden="true"></div>
    <div class="detail-shade" aria-hidden="true"></div>

    <a href="../index.php" class="icon-btn detail-back" aria-label="Retour"
       onclick="if (document.referrer.includes(location.host)) { history.back(); return false; }">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="detail-content">
        <h1>
            <?php if ($logo): ?>
                <img src="<?= e(imageTmdb($logo['file_path'], 'w500')) ?>" alt="<?= e($titre) ?>" class="detail-logo">
            <?php else: ?>
                <span class="detail-title"><?= e($titre) ?></span>
            <?php endif; ?>
        </h1>

        <div class="detail-meta">
            <?php if ($note): ?><span class="pill pill-note"><i class="fas fa-star"></i> <?= $note ?></span><?php endif; ?>
            <?php if ($ts): ?><span><?= date('Y', $ts) ?></span><?php endif; ?>
            <?php if ($duree): ?><span><?= $duree ?></span><?php endif; ?>
            <?php if (isExtended($titre)): ?><span class="pill pill-vl">Version longue</span><?php endif; ?>
            <span class="pill pill-avoir" <?= si('a_voir', $statut) ?>><i class="fas fa-bookmark"></i> Dans ma liste</span>
            <span class="pill pill-vu" <?= si('vu', $statut) ?>><i class="fas fa-check"></i> Vu</span>
        </div>

        <?php if ($genres): ?><p class="detail-genres"><?= e(implode(' · ', $genres)) ?></p><?php endif; ?>

        <p class="detail-synopsis" id="synopsis"><?= e($synopsis) ?></p>
        <button type="button" class="lien-plus" id="plusInfos">Plus d'infos <i class="fas fa-chevron-right"></i></button>

        <div class="actions">
            <button type="button" class="btn btn-primary" data-action="addMovie" <?= si('aucun', $statut) ?>>
                <i class="fas fa-plus"></i> Ajouter à ma liste
            </button>
            <button type="button" class="btn btn-primary" data-action="marquerVu" <?= si('a_voir', $statut) ?>>
                <i class="fas fa-check"></i> Marquer comme vu
            </button>
            <button type="button" class="btn btn-glass" data-action="demarquerVu" <?= si('vu', $statut) ?>>
                <i class="fas fa-rotate-left"></i> Remettre à voir
            </button>
            <?php if ($trailer): ?>
                <button type="button" class="btn btn-glass btn-round has-label" id="voirTrailer" aria-label="Bande-annonce" title="Bande-annonce">
                    <i class="fas fa-play"></i><span class="btn-label">Bande-annonce</span>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-glass btn-round btn-danger" data-action="supprimerFilm" <?= si('a_voir vu', $statut) ?>
                    data-confirm="Retirer ce film de ta collection ?" aria-label="Retirer de la collection" title="Retirer de la collection">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <?php if ($cast): ?>
            <div class="cast" aria-label="Distribution">
                <?php foreach ($cast as $a) acteur($a, false); ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<dialog class="sheet" id="infosDialog" aria-labelledby="infosTitre">
    <div class="sheet-grab"></div>
    <div class="sheet-head">
        <h2 id="infosTitre"><?= e($titre) ?></h2>
        <button type="button" class="icon-btn" aria-label="Fermer" onclick="infosDialog.close()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="sheet-body">
        <?php if ($tagline): ?><p class="infos-tagline">« <?= e($tagline) ?> »</p><?php endif; ?>
        <p class="infos-synopsis"><?= e($synopsis) ?></p>
        <dl class="facts">
            <?php if ($realisateurs): ?><dt>Réalisation</dt><dd><?= e(implode(', ', $realisateurs)) ?></dd><?php endif; ?>
            <?php if ($titreOriginal && $titreOriginal !== $titre): ?><dt>Titre original</dt><dd><?= e($titreOriginal) ?></dd><?php endif; ?>
            <?php if ($sortieLongue): ?><dt>Sortie</dt><dd><?= $sortieLongue ?></dd><?php endif; ?>
            <?php if ($duree): ?><dt>Durée</dt><dd><?= $duree ?></dd><?php endif; ?>
            <?php if ($genres): ?><dt>Genres</dt><dd><?= e(implode(', ', $genres)) ?></dd><?php endif; ?>
            <?php if ($note): ?><dt>Note TMDB</dt><dd><?= $note ?> / 10</dd><?php endif; ?>
        </dl>
        <?php if ($cast): ?>
            <p class="sheet-label">Distribution</p>
            <div class="cast-grid">
                <?php foreach ($cast as $a) acteur($a, true); ?>
            </div>
        <?php endif; ?>
    </div>
</dialog>

<?php if ($trailer): ?>
    <dialog class="sheet sheet-video" id="videoDialog" aria-label="Bande-annonce">
        <div class="video"><iframe id="videoFrame" title="Bande-annonce" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
    </dialog>
<?php endif; ?>

<script>
    const TMDB_ID = <?= $id ?>;
    const detail = document.getElementById('detail');
    const infosDialog = document.getElementById('infosDialog');
    const SUIVANT = { addMovie: 'a_voir', marquerVu: 'vu', demarquerVu: 'a_voir', supprimerFilm: 'aucun' };
    const MESSAGES = { addMovie: 'Ajouté à ta liste', marquerVu: 'Marqué comme vu', demarquerVu: 'Remis dans « à voir »', supprimerFilm: 'Retiré de ta collection' };

    function afficherStatut(statut) {
        detail.dataset.statut = statut;
        document.querySelectorAll('[data-si]').forEach(el => el.hidden = !el.dataset.si.split(' ').includes(statut));
    }

    document.querySelectorAll('[data-action]').forEach(btn => btn.addEventListener('click', async () => {
        const action = btn.dataset.action;
        if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
        btn.disabled = true;
        try {
            const r = await fetch('../modele/' + action + '.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tmdb_id: TMDB_ID }),
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            const maj = () => afficherStatut(SUIVANT[action]);
            // La transition peut être annulée (onglet caché...) : la mise à jour se fait quand même
            document.startViewTransition ? document.startViewTransition(maj).ready.catch(() => {}) : maj();
            toast(MESSAGES[action]);
            store.set('watchd_modifie', 1);
        } catch (e) {
            toast('Erreur : ' + e.message, true);
        }
        btn.disabled = false;
    }));

    // Synopsis : fondu en bas quand il est coupé faute de place
    const synopsis = document.getElementById('synopsis');
    new ResizeObserver(() => synopsis.classList.toggle('is-clamped', synopsis.scrollHeight > synopsis.clientHeight + 1)).observe(synopsis);
    synopsis.addEventListener('click', () => infosDialog.showModal());
    document.getElementById('plusInfos').addEventListener('click', () => infosDialog.showModal());

    document.querySelectorAll('dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));

    <?php if ($trailer): ?>
    const videoDialog = document.getElementById('videoDialog');
    const videoFrame = document.getElementById('videoFrame');
    document.getElementById('voirTrailer').addEventListener('click', () => {
        videoFrame.src = 'https://www.youtube-nocookie.com/embed/' + <?= json_encode($trailer['key']) ?> + '?autoplay=1&rel=0';
        videoDialog.showModal();
    });
    videoDialog.addEventListener('close', () => videoFrame.src = 'about:blank');
    <?php endif; ?>
</script>

<?php piedDePage('../', null, false); ?>
