<?php
// On inclut les fichiers nécessaires
require_once __DIR__ . '/../secrets.php';
require_once __DIR__ . '/../modele/connexionBd.php';

// 1. Récupération de l'ID
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: ../index.php'); exit; }

// 2. Infos Locales (NAS / Vu / À voir)
$sql = "SELECT * FROM films_a_voir WHERE tmdb_id = ? UNION SELECT * FROM films_vus WHERE tmdb_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $id]);
$local = $stmt->fetch();

// --- MODIFICATION INTELLIGENTE ICI ---
// Si la base pense que le fichier existe, on vérifie si c'est VRAI physiquement.
if ($local && !empty($local['chemin_fichier'])) {
    if (!file_exists($local['chemin_fichier'])) {
        // Aïe, le fichier a été supprimé du NAS manuellement !
        // On met à jour la base de données immédiatement pour oublier ce fichier.

        // On nettoie les deux tables par sécurité
        $pdo->prepare("UPDATE films_vus SET chemin_fichier = NULL WHERE tmdb_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE films_a_voir SET chemin_fichier = NULL WHERE tmdb_id = ?")->execute([$id]);

        // On met à jour la variable locale pour que l'affichage change tout de suite
        $local['chemin_fichier'] = NULL;
    }
}
// -------------------------------------

$isOnNas = ($local && !empty($local['chemin_fichier']));
$isVu = ($local && isset($local['vu']) && $local['vu'] == 1);
$isInDb = ($local !== false);

// 3. Infos TMDB (Avec Images pour le Logo !)
// On demande les images (logos) en plus des crédits
$url = "https://api.themoviedb.org/3/movie/$id?api_key=".API_KEY."&language=fr-FR&append_to_response=credits,images&include_image_language=fr,en,null";
$json = @file_get_contents($url);
$api = $json ? json_decode($json, true) : [];

// --- PRÉPARATION DES DONNÉES ---

// Titre & Logo
$titreText = $api['title'] ?? ($local['titre'] ?? 'Titre Inconnu');
$logoUrl = null;

// On cherche un logo PNG dans la réponse API
if (!empty($api['images']['logos'])) {
    // On prend le premier logo (souvent le meilleur)
    $logoUrl = "https://image.tmdb.org/t/p/w500" . $api['images']['logos'][0]['file_path'];
}

// Images de fond
$backdrop = !empty($api['backdrop_path'])
    ? "https://image.tmdb.org/t/p/original" . $api['backdrop_path']
    : "../assets/no-poster.jpg";

// Métadonnées
$dureeMin = $api['runtime'] ?? ($local['duree'] ?? 0);
$duree = ($dureeMin > 0) ? floor($dureeMin/60)." h ".sprintf('%02d', $dureeMin%60)." min" : "";
$annee = substr($api['release_date'] ?? ($local['date_sortie'] ?? ''), 0, 4);
$note = isset($api['vote_average']) ? round($api['vote_average'] * 10) : 0;
$synopsis = $api['overview'] ?? ($local['synopsis'] ?? 'Aucun résumé disponible.');
$tagline = $api['tagline'] ?? '';

// Casting (3 premiers acteurs)
$cast = [];
if (!empty($api['credits']['cast'])) {
    $cast = array_slice($api['credits']['cast'], 0, 4); // Top 4
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titreText) ?></title>
    <link rel="stylesheet" href="../css/detailFilm.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<a href="#" onclick="if(history.length > 1){ history.back(); } else { window.location.href='../index.php'; } return false;" class="back-btn">
    <i class="fas fa-arrow-left"></i>
</a>

<div class="hero" style="background-image: url('<?= $backdrop ?>');">

    <div class="vignette"></div>

    <div class="content-wrapper">
        <div class="info-block">

            <?php if ($logoUrl): ?>
                <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($titreText) ?>" class="movie-logo">
            <?php else: ?>
                <div class="movie-title-text"><?= htmlspecialchars($titreText) ?></div>
            <?php endif; ?>

            <div class="meta-line">
                <span class="score"><?= $note ?>% Recommandé</span>
                <span class="year"><?= $annee ?></span>
                <span class="hd-tag">HD</span>
                <span><?= $duree ?></span>
            </div>

            <div class="btn-row">

                <?php if (!$isInDb): ?>
                    <button id="btnDl" class="btn" style="background-color: #e50914;" onclick="ajouterFilm(<?= $id ?>, true)">
                        <i class="fas fa-download"></i> Ajouter & Télécharger
                    </button>

                    <button id="btnList" class="btn btn-glass" onclick="ajouterFilm(<?= $id ?>, false)">
                        <i class="fas fa-list"></i> Juste ajouter à ma liste
                    </button>

                <?php elseif ($isInDb && !$isVu): ?>
                    <button class="btn btn-glass" onclick="marquerVu(<?= $id ?>)">
                        <i class="far fa-check-circle"></i> Marquer comme Vu
                    </button>

                    <?php if(!$isOnNas): ?>
                        <button class="btn btn-glass" onclick="ajouterFilm(<?= $id ?>, true)" title="Relancer le téléchargement">
                            <i class="fas fa-download"></i>
                        </button>
                    <?php endif; ?>

                <?php elseif ($isVu): ?>
                    <?php if (!$isOnNas): ?>
                        <button id="btnDl" class="btn" style="background-color: #e50914;" onclick="ajouterFilm(<?= $id ?>, true)">
                            <i class="fas fa-download"></i> Télécharger à nouveau
                        </button>
                    <?php endif; ?>

                    <button class="btn btn-glass" style="border-color: #666; color: #aaa;" onclick="demarquerVu(<?= $id ?>)">
                        <i class="fas fa-times"></i> Retirer des films vus
                    </button>

                <?php endif; ?>
                <?php if ($isInDb): ?>
                    <button class="btn btn-glass" style="border-color: #d32f2f; color: #d32f2f; margin-left: auto;" onclick="supprimerTotalement(<?= $id ?>)" title="Supprimer définitivement">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>

            </div>

            <div id="progressContainer" style="display: none; margin-top: 20px; width: 100%; max-width: 400px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.9rem; color:#ccc;">
                    <span id="progressText">Recherche...</span>
                    <span id="progressTime"></span>
                </div>
                <div style="width: 100%; background: #333; height: 10px; border-radius: 5px; overflow: hidden;">
                    <div id="progressBar" style="width: 0%; height: 100%; background: #e50914; transition: width 0.5s;"></div>
                </div>
            </div>

            <?php if($tagline): ?>
                <div style="font-style:italic; color:#ccc; margin-bottom:10px;">“<?= htmlspecialchars($tagline) ?>”</div>
            <?php endif; ?>

            <div class="synopsis">
                <?= mb_strimwidth($synopsis, 0, 350, "...") ?>
            </div>

            <?php if(!empty($cast)): ?>
                <div style="color:#777; font-size:0.9rem; margin-bottom:10px;">Distribution :</div>
                <div class="cast-row">
                    <?php foreach($cast as $actor): ?>
                        <?php $face = $actor['profile_path'] ? "https://image.tmdb.org/t/p/w200".$actor['profile_path'] : "../assets/no-user.jpg"; ?>
                        <div class="actor">
                            <img src="<?= $face ?>" alt="Actor">
                            <span><?= explode(' ', $actor['name'])[0] // Juste le prénom ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    const tmdbId = <?= $id ?>;
    const isOnNas = <?= $isOnNas ? 'true' : 'false' ?>; // On utilise la variable PHP

    // Au chargement, si on n'a pas le film, on vérifie si un téléchargement est en cours
    document.addEventListener('DOMContentLoaded', () => {
        if (!isOnNas) {
            pollProgress();
            // On vérifie toutes les 2 secondes
            setInterval(pollProgress, 2000);
        }
    });

    // Fonction qui surveille l'avancement
    function pollProgress() {
        // On rend la barre visible
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const btn = document.getElementById('btnAction'); // Le bouton principal

        progressContainer.style.display = 'block';
        if(btn) btn.style.display = 'none'; // On cache le bouton d'ajout pendant le suivi

        const interval = setInterval(() => {
            fetch(`../modele/checkProgress.php?id=<?= $id ?>`)
                .then(r => r.json())
                .then(data => {

                    // 1. CAS : Film Prêt
                    if (data.status === 'ready') {
                        clearInterval(interval);
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = '#4CAF50'; // Vert
                        progressText.innerHTML = "<i class='fas fa-check'></i> Film disponible !";
                        setTimeout(() => location.reload(), 2000);
                    }

                    // 2. CAS : Téléchargement actif
                    else if (data.status === 'downloading') {
                        progressBar.style.width = data.percent + '%';
                        progressBar.style.backgroundColor = '#2196F3'; // Bleu
                        progressText.innerText = data.message;
                    }

                    // 3. CAS : Problème technique (Bloqué dans Radarr)
                    else if (data.status === 'warning') {
                        progressBar.style.width = data.percent + '%';
                        progressBar.style.backgroundColor = '#ff9800'; // Orange
                        progressText.innerHTML = "<i class='fas fa-exclamation-triangle'></i> " + data.message;
                    }

                    // 4. CAS : Recherche en cours (Pas de sources trouvées) -> C'est celui que tu voulais
                    else if (data.status === 'searching') {
                        progressBar.style.width = '100%'; // On remplit la barre
                        progressBar.style.backgroundColor = '#607d8b'; // Gris bleu (couleur neutre d'attente)
                        // On ajoute une animation "striped" si tu veux, ou juste une couleur fixe
                        progressText.innerHTML = "<i class='fas fa-search'></i> " + data.message;
                    }

                    // 5. CAS : Erreur / Non trouvé
                    else {
                        progressText.innerText = "État inconnu...";
                    }
                })
                .catch(e => {
                    console.error("Erreur polling:", e);
                    // On ne coupe pas forcément l'intervalle en cas d'erreur réseau temporaire
                });
        }, 2000); // Vérifie toutes les 2 secondes
    }

    // Au chargement de la page, si on vient de lancer un téléchargement, on lance le poll
    // (Tu peux ajouter une condition PHP ici si tu veux que ça se lance auto quand un film est "a_voir" mais pas "isOnNas")
    <?php if ($isInDb && !$isOnNas): ?>
    pollProgress();
    <?php endif; ?>

    // --- Tes anciennes fonctions ---

    async function ajouterFilm(id, download) {
        const btnId = download ? 'btnDl' : 'btnList';
        const btn = document.getElementById(btnId) || document.querySelector('.btn');

        const originalText = btn.innerHTML;
        btn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> ...";

        try {
            const response = await fetch('../modele/addMovie.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ tmdb_id: id, download: download })
            });
            const data = await response.json();

            if(data.success || data.already_exists) {
                if (download) {
                    // Si on lance un téléchargement, on force l'affichage de la barre immédiatement
                    pollProgress();
                } else {
                    btn.innerHTML = "<i class='fas fa-check'></i> Ajouté";
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                alert("Erreur: " + data.message);
                btn.innerHTML = originalText;
            }
        } catch(e) {
            console.error(e);
            btn.innerHTML = originalText;
        }
    }

    function marquerVu(id) { postAction('../modele/marquerVu.php', id); }
    function demarquerVu(id) { if(confirm("Restaurer ?")) postAction('../modele/demarquerVu.php', id); }
    function postAction(url, id) {
        const form = document.createElement('form'); form.method = 'POST'; form.action = url;
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'tmdb_id'; input.value = id;
        form.appendChild(input); document.body.appendChild(form); form.submit();
    }

    function supprimerTotalement(id) {
        if (confirm("Supprimer ce film de la liste ?\n(Cela ne supprime pas le fichier sur le NAS)")) {
            fetch('../modele/supprimerFilm.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ tmdb_id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if(data.success) window.location.href = '../index.php';
                    else alert("Erreur: " + data.message);
                });
        }
    }
</script>
</body>
</html>