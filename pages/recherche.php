<?php
// pages/recherche.php - Recherche TMDB et ajout rapide
require_once __DIR__ . '/../modele/connexionBd.php';
require_once __DIR__ . '/layout.php';

$dejaAjoutes = array_flip($pdo->query("SELECT tmdb_id FROM films_a_voir UNION SELECT tmdb_id FROM films_vus")->fetchAll(PDO::FETCH_COLUMN));

$recherche = trim($_GET['recherche'] ?? '');
// Sans recherche, on propose les tendances de la semaine
$url = $recherche !== ''
    ? "https://api.themoviedb.org/3/search/movie?api_key=" . API_KEY . "&query=" . urlencode($recherche) . "&language=fr-FR&include_adult=false"
    : "https://api.themoviedb.org/3/trending/movie/week?api_key=" . API_KEY . "&language=fr-FR";
$json = @file_get_contents($url);
$resultats = $json ? (json_decode($json, true)['results'] ?? []) : [];

enTete('../', 'Ajouter un film - Watchd', 'ajouter');
?>

<h1 class="page-title">Ajouter un film</h1>

<form method="GET" class="toolbar" role="search">
    <label class="search">
        <i class="fas fa-search"></i>
        <input type="search" name="recherche" value="<?= e($recherche) ?>" placeholder="Titre du film…"
               autocomplete="off" aria-label="Titre du film" <?= $recherche === '' ? 'autofocus' : '' ?>>
    </label>
</form>

<?php if ($json === false): ?>
    <p class="empty">Impossible de contacter TMDB.</p>
<?php elseif (!$resultats): ?>
    <p class="empty">Aucun résultat pour « <?= e($recherche) ?> ».</p>
<?php else: ?>
    <p class="section-title"><?= $recherche !== '' ? 'Résultats pour « ' . e($recherche) . ' »' : 'Tendances de la semaine' ?></p>
    <div class="grid">
        <?php foreach ($resultats as $film): ?>
            <?php
            $id = (int)$film['id'];
            $img = imageTmdb($film['poster_path'] ?? null);
            $lien = "detailFilm.php?id=$id";
            ?>
            <article class="card">
                <a href="<?= $lien ?>" class="poster" tabindex="-1" aria-hidden="true">
                    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
                    <?php else: ?><span class="poster-empty"><?= e($film['title']) ?></span><?php endif; ?>
                </a>
                <?php if (isset($dejaAjoutes[$id])): ?>
                    <span class="add-btn is-added" title="Déjà dans ma collection"><i class="fas fa-check"></i></span>
                <?php else: ?>
                    <button type="button" class="add-btn" data-id="<?= $id ?>" aria-label="Ajouter <?= e($film['title']) ?> à ma liste" title="Ajouter à ma liste">
                        <i class="fas fa-plus"></i>
                    </button>
                <?php endif; ?>
                <a href="<?= $lien ?>" class="card-title"><?= e($film['title']) ?></a>
                <div class="card-meta"><?= e(substr($film['release_date'] ?? '', 0, 4)) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('click', async e => {
        const btn = e.target.closest('button.add-btn');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const r = await fetch('../modele/addMovie.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tmdb_id: +btn.dataset.id }),
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);
            btn.classList.add('is-added');
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.setAttribute('aria-label', 'Ajouté à ma liste');
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i>';
            alert('Erreur : ' + err.message);
        }
    });
</script>

<?php piedDePage('../', 'ajouter'); ?>
