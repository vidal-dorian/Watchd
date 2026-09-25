<?php
// pages/detailFilm.php - Fiche d'un film
require_once __DIR__ . '/../modele/connexionBd.php';
require_once __DIR__ . '/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ../index.php'); exit; }

$stmt = $pdo->prepare("SELECT *, 'a_voir' AS statut FROM films_a_voir WHERE tmdb_id = ?
                       UNION ALL SELECT *, 'vu' AS statut FROM films_vus WHERE tmdb_id = ?");
$stmt->execute([$id, $id]);
$local = $stmt->fetch() ?: null;
$statut = $local['statut'] ?? null;

$url = "https://api.themoviedb.org/3/movie/$id?api_key=" . API_KEY . "&language=fr-FR&append_to_response=credits,images&include_image_language=fr,en,null";
$json = @file_get_contents($url);
$api = $json ? json_decode($json, true) : [];
if (isset($api['status_code'])) $api = [];

if (!$api && !$local) {
    http_response_code(404);
    enTete('../', 'Film introuvable', null, true);
    echo '<p class="empty">Film introuvable.</p>';
    piedDePage('../', null);
    exit;
}

// Le titre local peut contenir « (Version Longue) », on le préfère
$titre = $local['titre'] ?? $api['title'] ?? 'Titre inconnu';

$logo = null;
$logos = $api['images']['logos'] ?? [];
foreach ($logos as $l) if (($l['iso_639_1'] ?? '') === 'fr') { $logo = $l; break; }
$logo = $logo ?? ($logos[0] ?? null);

$backdrop = imageTmdb($api['backdrop_path'] ?? $local['backdrop_path'] ?? null, 'w1280');
$poster = imageTmdb($api['poster_path'] ?? $local['poster_path'] ?? null, 'w500');
$duree = dureeFmt($api['runtime'] ?? $local['duree'] ?? 0);
$annee = substr($api['release_date'] ?? $local['date_sortie'] ?? '', 0, 4);
$noteBrute = $api['vote_average'] ?? $local['note_tmdb'] ?? 0;
$note = $noteBrute ? round($noteBrute * 10) : 0;
$synopsis = ($api['overview'] ?? '') ?: ($local['synopsis'] ?? '') ?: 'Aucun résumé disponible.';
$tagline = $api['tagline'] ?? $local['tagline'] ?? '';
$genres = isset($api['genres'])
    ? array_column($api['genres'], 'name')
    : array_filter(array_map('trim', explode(',', $local['genres'] ?? '')));
$cast = array_slice($api['credits']['cast'] ?? [], 0, 12);

enTete('../', $titre . ' - Watchd', null, true);
?>

<div class="detail-backdrop" <?= $backdrop ? 'style="background-image: url(\'' . e($backdrop) . '\')"' : '' ?>></div>

<main class="detail">
    <?php if ($poster): ?>
        <img src="<?= e($poster) ?>" alt="Affiche de <?= e($titre) ?>" class="detail-poster">
    <?php else: ?>
        <div class="detail-poster"></div>
    <?php endif; ?>

    <div class="detail-head">
        <?php if ($logo): ?>
            <h1><img src="<?= e(imageTmdb($logo['file_path'], 'w500')) ?>" alt="<?= e($titre) ?>" class="detail-logo"></h1>
            <?php if (isExtended($titre)): ?><p class="detail-meta">Version longue</p><?php endif; ?>
        <?php else: ?>
            <h1 class="detail-title"><?= e($titre) ?></h1>
        <?php endif; ?>

        <p class="detail-meta">
            <?php if ($note): ?><span class="note"><?= $note ?>%</span><?php endif; ?>
            <?php if ($annee): ?><span><?= e($annee) ?></span><?php endif; ?>
            <?php if ($duree): ?><span><?= $duree ?></span><?php endif; ?>
        </p>

        <?php if ($statut === 'a_voir'): ?>
            <span class="status status-avoir"><i class="fas fa-bookmark"></i> Dans ma liste</span>
        <?php elseif ($statut === 'vu'): ?>
            <span class="status status-vu"><i class="fas fa-check"></i> Vu</span>
        <?php endif; ?>
    </div>

    <div class="detail-body">
        <div class="actions">
            <?php if (!$statut): ?>
                <button type="button" class="btn btn-primary" data-action="addMovie">
                    <i class="fas fa-plus"></i> Ajouter à ma liste
                </button>
            <?php elseif ($statut === 'a_voir'): ?>
                <button type="button" class="btn btn-primary" data-action="marquerVu">
                    <i class="fas fa-check"></i> Marquer comme vu
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary" data-action="demarquerVu">
                    <i class="fas fa-rotate-left"></i> Remettre dans « à voir »
                </button>
            <?php endif; ?>
            <?php if ($statut): ?>
                <button type="button" class="btn btn-danger" data-action="supprimerFilm"
                        data-confirm="Supprimer ce film de ta collection ?" aria-label="Supprimer de la collection" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($tagline): ?><p class="tagline">« <?= e($tagline) ?> »</p><?php endif; ?>
        <p class="synopsis"><?= e($synopsis) ?></p>

        <?php if ($genres): ?>
            <div class="genres"><?php foreach ($genres as $g): ?><span><?= e($g) ?></span><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($cast): ?>
            <h2 class="cast-title">Distribution</h2>
            <div class="cast">
                <?php foreach ($cast as $a): ?>
                    <div class="actor">
                        <?php if ($a['profile_path']): ?>
                            <img src="<?= e(imageTmdb($a['profile_path'], 'w185')) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="avatar"><i class="fas fa-user"></i></span>
                        <?php endif; ?>
                        <div class="actor-name"><?= e($a['name']) ?></div>
                        <?php if (!empty($a['character'])): ?><div class="actor-role"><?= e($a['character']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    const TMDB_ID = <?= $id ?>;
    document.querySelectorAll('[data-action]').forEach(btn => btn.addEventListener('click', async () => {
        if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
        btn.disabled = true;
        try {
            const r = await fetch('../modele/' + btn.dataset.action + '.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tmdb_id: TMDB_ID }),
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            if (btn.dataset.action === 'supprimerFilm') location.href = '../index.php';
            else location.reload();
        } catch (e) {
            alert('Erreur : ' + e.message);
            btn.disabled = false;
        }
    }));
</script>

<?php piedDePage('../', null); ?>
