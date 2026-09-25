<?php
// pages/recherche.php - Recherche TMDB (en direct) et ajout rapide
require_once __DIR__ . '/../modele/connexionBd.php';
require_once __DIR__ . '/layout.php';

$dejaAjoutes = array_flip($pdo->query("SELECT tmdb_id FROM films_a_voir UNION SELECT tmdb_id FROM films_vus")->fetchAll(PDO::FETCH_COLUMN));

$recherche = trim($_GET['recherche'] ?? '');
// Sans recherche, on propose les tendances de la semaine
$url = $recherche !== ''
    ? "https://api.themoviedb.org/3/search/movie?api_key=" . API_KEY . "&query=" . urlencode($recherche) . "&language=fr-FR&include_adult=false"
    : "https://api.themoviedb.org/3/trending/movie/week?api_key=" . API_KEY . "&language=fr-FR";
$json = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 6]]));
$resultats = $json ? (json_decode($json, true)['results'] ?? []) : [];

$ambiance = null;
foreach ($resultats as $r) if (!empty($r['backdrop_path'])) { $ambiance = imageTmdb($r['backdrop_path'], 'w300'); break; }

enTete('../', 'Ajouter un film - Watchd', 'ajouter', ['ambiance' => $ambiance]);
?>

<header class="page-head">
    <p class="eyebrow">Découvrir</p>
    <h1 class="page-title">Ajouter un film</h1>
</header>

<form method="GET" class="toolbar" id="formRecherche" role="search">
    <label class="search search-lg">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="recherche" value="<?= e($recherche) ?>" placeholder="Titre du film…"
               autocomplete="off" enterkeyhint="search" aria-label="Titre du film">
    </label>
</form>

<div id="resultats">
    <?php if ($json === false): ?>
        <div class="empty"><i class="fas fa-wifi"></i><strong>TMDB ne répond pas</strong>Réessaie dans un instant.</div>
    <?php elseif (!$resultats): ?>
        <div class="empty"><i class="fas fa-magnifying-glass"></i><strong>Aucun résultat</strong>Rien trouvé pour « <?= e($recherche) ?> ».</div>
    <?php else: ?>
        <h2 class="section-title"><?= $recherche !== '' ? 'Résultats pour « ' . e($recherche) . ' »' : '🔥 Tendances de la semaine' ?></h2>
        <div class="grid">
            <?php foreach ($resultats as $film): ?>
                <?php
                $id = (int)$film['id'];
                $img = imageTmdb($film['poster_path'] ?? null);
                $lien = "detailFilm.php?id=$id";
                $note = noteFmt($film['vote_average'] ?? 0);
                ?>
                <article class="card">
                    <div class="poster">
                        <?php if ($img): imgAffiche($img); else: ?><span class="poster-empty"><?= e($film['title']) ?></span><?php endif; ?>
                        <a href="<?= $lien ?>" class="poster-link" data-vt tabindex="-1" aria-hidden="true"></a>
                        <?php if ($note): ?><span class="badge badge-note"><i class="fas fa-star"></i> <?= $note ?></span><?php endif; ?>
                        <?php if (isset($dejaAjoutes[$id])): ?>
                            <span class="add-btn is-added" title="Déjà dans ma collection"><i class="fas fa-check"></i></span>
                        <?php else: ?>
                            <button type="button" class="add-btn" data-id="<?= $id ?>" aria-label="Ajouter <?= e($film['title']) ?> à ma liste" title="Ajouter à ma liste">
                                <i class="fas fa-plus"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <a href="<?= $lien ?>" class="card-title" data-vt><?= e($film['title']) ?></a>
                    <div class="card-meta"><?= e(annee($film['release_date'] ?? '')) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Recherche en direct : on recharge juste la zone des résultats pendant la frappe
    const form = document.getElementById('formRecherche');
    const champ = form.elements.recherche;
    const zone = document.getElementById('resultats');
    let minuteur, requete;

    async function chercher() {
        const url = '?recherche=' + encodeURIComponent(champ.value.trim());
        requete?.abort();
        requete = new AbortController();
        zone.classList.add('is-loading');
        try {
            const html = await (await fetch(url, { signal: requete.signal })).text();
            zone.innerHTML = new DOMParser().parseFromString(html, 'text/html').getElementById('resultats').innerHTML;
            history.replaceState(null, '', url);
        } catch (e) {
            if (e.name === 'AbortError') return;
            toast('Recherche impossible', true);
        }
        zone.classList.remove('is-loading');
    }
    champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(chercher, 350); });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(minuteur); champ.blur(); chercher(); });
    if (!champ.value && matchMedia('(hover: hover)').matches) champ.focus();

    // Bouton + : ajout direct à la liste
    document.addEventListener('click', async e => {
        const btn = e.target.closest('button.add-btn');
        if (!btn) return;
        const titre = btn.closest('.card').querySelector('.card-title').textContent;
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
            btn.setAttribute('aria-label', titre + ' est dans ta liste');
            toast(`« ${titre} » ajouté à ta liste`);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i>';
            toast('Erreur : ' + err.message, true);
        }
    });
</script>

<?php piedDePage('../', 'ajouter'); ?>
