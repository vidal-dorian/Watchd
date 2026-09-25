<?php
// modele/scanner.php - V4 EXTENDED EDITION
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Force l'affichage immédiat
if (function_exists('apache_setenv')) apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

require_once __DIR__ . '/../secrets.php';
require_once 'connexionBd.php';

define('DOSSIER_FILMS', '/mnt/films');
$extensions = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'mpg', 'iso'];

?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Scanner V4 Extended</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background-color: #0f1014; color: #ccc; font-family: sans-serif; padding: 20px; font-size: 14px; }
            .log { background: #1a1a1a; padding: 10px; margin-bottom: 5px; border-radius: 4px; display: flex; align-items: center; gap: 10px; }
            .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #000; font-size: 11px; min-width: 80px; text-align: center; }
            .bg-ok { background: #46d369; }
            .bg-new { background: #33aadd; color:white; }
            .bg-fail { background: #e50914; color:white; }
            .bg-purple { background: #9b59b6; color:white; } /* Pour version longue */
            .info { color: #666; font-size: 12px; margin-left: auto; }
            strong { color: white; }
        </style>
    </head>
    <body>
    <h2 style="color:white; border-bottom:1px solid #333; padding-bottom:10px;">⚡ Scanner V4 (Support Versions Longues)</h2>
    <div id="console">
<?php

// ------------------------------------------------------------------
// LOGIQUE PRINCIPALE
// ------------------------------------------------------------------

$directory = new RecursiveDirectoryIterator(DOSSIER_FILMS, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($directory);
$compteur = 0;

foreach ($iterator as $file) {
    if (in_array(strtolower($file->getExtension()), $extensions)) {

        if (strpos($file->getFilename(), '._') === 0) continue;
        if (strpos(strtolower($file->getFilename()), 'sample') !== false) continue;

        $cheminComplet = $file->getRealPath();

        // Check DB
        $check = $pdo->prepare("SELECT id FROM films_a_voir WHERE chemin_fichier = ? UNION SELECT id FROM films_vus WHERE chemin_fichier = ?");
        $check->execute([$cheminComplet, $cheminComplet]);
        if ($check->fetch()) continue;

        $compteur++;
        $nomFichier = $file->getBasename('.' . $file->getExtension());
        $nomDossier = basename(dirname($cheminComplet));

        // -----------------------------------------------------------
        // ALGORITHME DE RECHERCHE
        // -----------------------------------------------------------
        $tmdbFilm = null;
        $method = "";
        $isExtended = false;

        // TENTATIVE 1 : Fichier
        $infos = analyserChaine($nomFichier);
        $tmdbFilm = tentativerRecherche($infos);
        if ($tmdbFilm) {
            $method = "Fichier";
            $isExtended = $infos['is_extended'];
        }

        // TENTATIVE 2 : Dossier
        if (!$tmdbFilm) {
            $infosDossier = analyserChaine($nomDossier);
            if ($infosDossier['titre'] !== $infos['titre']) {
                $tmdbFilm = tentativerRecherche($infosDossier);
                if ($tmdbFilm) {
                    $method = "Dossier";
                    $isExtended = $infosDossier['is_extended'];
                }
            }
        }

        // TENTATIVE 3 : Fuzzy
        if (!$tmdbFilm) {
            $mots = explode(' ', $infos['titre']);
            if (count($mots) >= 2) {
                $shortQuery = $mots[0] . " " . $mots[1];
                $tmdbFilm = searchTMDB($shortQuery);
                if ($tmdbFilm) $method = "Fuzzy";
            }
        }

        // -----------------------------------------------------------
        // TRAITEMENT RESULTAT
        // -----------------------------------------------------------
        if ($tmdbFilm) {
            $idTmdb = $tmdbFilm['id'];

            // --- C'EST ICI QUE LA MAGIE OPÈRE ---
            $titreEnBase = $tmdbFilm['title'];
            if ($isExtended) {
                $titreEnBase .= " (Version Longue)";
            }
            // ------------------------------------

            $anneeFinal = substr($tmdbFilm['release_date'] ?? '????', 0, 4);

            // Vérif existence ID
            $checkID = $pdo->prepare("SELECT id FROM films_a_voir WHERE tmdb_id = ?");
            $checkID->execute([$idTmdb]);
            $existing = $checkID->fetch();

            if ($existing) {
                // Update chemin ET Mise à jour du titre si besoin (pour ajouter Version Longue sur existant)
                $pdo->prepare("UPDATE films_a_voir SET chemin_fichier = ?, titre = ? WHERE id = ?")
                    ->execute([$cheminComplet, $titreEnBase, $existing['id']]);

                $badge = $isExtended ? "VL LIÉE" : "LIÉE";
                $color = $isExtended ? "bg-purple" : "bg-ok";
                renderLog($badge, $titreEnBase, "Via $method", $color);

            } else {
                $details = getDetailsTMDB($idTmdb);
                if ($details) {
                    // On écrase le titre officiel par notre titre "custom" Version Longue
                    $details['title'] = $titreEnBase;
                    traiterEtInsererFilm($pdo, $details, $cheminComplet);

                    $badge = $isExtended ? "VL AJOUT" : "NOUVEAU";
                    $color = $isExtended ? "bg-purple" : "bg-new";
                    renderLog($badge, $titreEnBase, "Via $method", $color);
                }
            }
        } else {
            try {
                $pdo->prepare("INSERT INTO films_non_trouves (nom_fichier, chemin_complet) VALUES (?, ?)")->execute([$nomFichier, $cheminComplet]);
            } catch(Exception $e) {}
            renderLog("ECHEC", $nomFichier, "Testé fichier + dossier", "bg-fail");
        }

        flush();
        echo "<script>window.scrollTo(0, document.body.scrollHeight);</script>";
    }
}

if ($compteur == 0) echo "<p style='color:#666; text-align:center'>Aucun nouveau fichier.</p>";
echo "</div></body></html>";


// ------------------------------------------------------------------
// FONCTIONS CLÉS
// ------------------------------------------------------------------

function analyserChaine($str) {
    // 1. Extraction Année
    $annee = null;
    if (preg_match('/(19|20)\d{2}/', $str, $matches)) {
        $annee = $matches[0];
    }

    // 2. Détection Version Longue (AVANT le nettoyage)
    $isExtended = false;
    $extendedKeywords = '/(extended|director\'?s\s?cut|version\s?longue|unrated|uncut|long\s?version|v\.?l\.?|dc)/i';

    // Attention : on vérifie que "DC" n'est pas au milieu d'un mot (ex: HDCam)
    // On utilise les bornes de mot \b ou des séparateurs
    if (preg_match($extendedKeywords, $str)) {
        $isExtended = true;
    }

    // 3. Nettoyage
    $clean = $str;
    if ($annee) {
        $parts = preg_split('/(19|20)\d{2}/', $str);
        $clean = $parts[0];
    }

    // Liste noire (Inclut maintenant les mots Extended pour qu'ils n'induisent pas l'API en erreur)
    $garbage = [
        '/[\[\(\{].*?[\]\}\)]/',
        '/\./', '/_/',
        '/1080p|720p|480p|4k|uhd|hd|hdr|x264|x265|h264|h265|hevc/i',
        '/bluray|web-dl|webrip|dvdrip|cam|ts|remux|box/i',
        '/truefrench|french|vff|vfi|vostfr|multi|english/i',
        '/ac3|dts|aac|mp3|dd5\.1|dolby|atmos/i',
        // On retire ces mots de la recherche, mais on a gardé l'info dans $isExtended
        '/repack|proper|extended|unrated|director\'s cut|dc|version longue|long version|uncut/i',
        '/cd[1-9]|part[1-9]/i'
    ];

    foreach ($garbage as $regex) $clean = preg_replace($regex, ' ', $clean);

    $clean = str_replace('-', ' ', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);

    return ['titre' => trim($clean), 'annee' => $annee, 'is_extended' => $isExtended];
}

function tentativerRecherche($infos) {
    $titre = $infos['titre'];
    $annee = $infos['annee'];

    if ($annee) {
        $res = searchTMDB($titre, $annee);
        if ($res) return $res;
        $res = searchTMDB($titre, $annee - 1);
        if ($res) return $res;
        $res = searchTMDB($titre, $annee + 1);
        if ($res) return $res;
    }
    return searchTMDB($titre);
}

function searchTMDB($query, $year = null) {
    if (strlen($query) < 2) return null;
    $url = "https://api.themoviedb.org/3/search/movie?api_key=" . API_KEY . "&query=" . urlencode($query) . "&language=fr-FR&include_adult=false";
    if ($year) $url .= "&year=" . $year;

    $json = @file_get_contents($url);
    if (!$json) return null;

    $data = json_decode($json, true);
    if (!empty($data['results'])) return $data['results'][0];
    return null;
}

function renderLog($badge, $titre, $info, $color) {
    echo "<div class='log'>
            <span class='badge $color'>$badge</span>
            <strong>$titre</strong>
            <span class='info'>$info</span>
          </div>";
}

function getDetailsTMDB($id) {
    $url = "https://api.themoviedb.org/3/movie/" . $id . "?api_key=" . API_KEY . "&language=fr-FR";
    return json_decode(@file_get_contents($url), true);
}

function traiterEtInsererFilm($pdo, $details, $chemin) {
    // Sagas
    $sagaIdLocal = null;
    if (!empty($details['belongs_to_collection'])) {
        $col = $details['belongs_to_collection'];
        $stmtSaga = $pdo->prepare("SELECT id FROM sagas WHERE tmdb_id = ?");
        $stmtSaga->execute([$col['id']]);
        $row = $stmtSaga->fetch();
        if ($row) $sagaIdLocal = $row['id'];
        else {
            $ins = $pdo->prepare("INSERT INTO sagas (tmdb_id, nom, poster_path, backdrop_path) VALUES (?,?,?,?)");
            $ins->execute([$col['id'], $col['name'], $col['poster_path'], $col['backdrop_path']]);
            $sagaIdLocal = $pdo->lastInsertId();
        }
    }

    $genres = [];
    if (!empty($details['genres'])) foreach($details['genres'] as $g) $genres[] = $g['name'];

    // Note : $details['title'] contient déjà "(Version Longue)" si on l'a modifié plus haut
    $sql = "INSERT INTO films_a_voir (
                saga_id, tmdb_id, titre, titre_original, genres, 
                duree, note_tmdb, synopsis, tagline, date_sortie, 
                poster_path, backdrop_path, chemin_fichier, vu, date_ajout
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $sagaIdLocal, $details['id'], $details['title'], $details['original_title'],
        implode(', ', $genres), $details['runtime']??0, $details['vote_average']??0,
        $details['overview']??'', $details['tagline']??'', $details['release_date']??null,
        $details['poster_path']??'', $details['backdrop_path']??'',
        $chemin,
        date('Y-m-d H:i:s')
    ]);
}
?>