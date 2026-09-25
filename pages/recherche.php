<?php
// pages/recherche.php
require_once __DIR__ . '/../secrets.php';
require_once __DIR__ . '/../modele/connexionBd.php';

// 1. Récupération des IDs locaux pour savoir ce qu'on a déjà
// Cela évite d'essayer d'ajouter un film qu'on a déjà
$sql = "SELECT tmdb_id FROM films_a_voir UNION SELECT tmdb_id FROM films_vus";
$stmt = $pdo->query($sql);
$localIds = $stmt->fetchAll(PDO::FETCH_COLUMN); // Tableau simple [123, 456, 789]

// 2. Logique de Recherche TMDB
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : "";
$resultats = [];
$erreur = null;

if ($recherche !== "") {
    $queryEncoded = urlencode($recherche);
    $url = "https://api.themoviedb.org/3/search/movie?api_key=" . API_KEY . "&query=" . $queryEncoded . "&language=fr-FR&page=1&include_adult=false";

    $jsonBrut = @file_get_contents($url);
    if ($jsonBrut) {
        $data = json_decode($jsonBrut, true);
        $resultats = isset($data['results']) ? $data['results'] : [];
    } else {
        $erreur = "Erreur de connexion à TMDB.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un film - Watchd</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700;900&display=swap');

        :root {
            --bg-black: #141414;
            --netflix-red: #E50914;
            --match-green: #46d369;
            --transition: 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        body { margin: 0; padding: 0; background-color: var(--bg-black); color: white; font-family: 'Roboto', sans-serif; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        /* HEADER */
        .navbar { position: fixed; top: 0; width: 100%; z-index: 1000; padding: 20px 4%; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to bottom, rgba(0,0,0,0.9) 0%, transparent 100%); }
        .logo { color: var(--netflix-red); font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; }
        .close-btn { font-size: 2rem; color: #aaa; transition: 0.3s; }
        .close-btn:hover { color: white; transform: rotate(90deg); }

        /* SEARCH SECTION */
        .search-container {
            padding: 120px 4% 40px 4%;
            display: flex; flex-direction: column; align-items: center;
        }

        .search-box {
            position: relative; width: 100%; max-width: 800px;
        }

        .search-input {
            width: 100%;
            background: #222;
            border: 1px solid #444;
            padding: 20px 60px 20px 25px;
            font-size: 1.5rem;
            color: white;
            border-radius: 4px; /* Carré arrondi Netflix */
            outline: none;
            transition: 0.3s;
        }
        .search-input:focus { background: #333; border-color: #777; }

        .search-icon {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            font-size: 1.5rem; color: #888;
        }

        /* GRID RESULTS */
        .results-title { padding: 0 4%; color: #777; font-size: 1rem; margin-bottom: 20px; font-weight: bold; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            padding: 0 4% 50px 4%;
        }

        .card {
            position: relative;
            aspect-ratio: 2/3;
            border-radius: 4px;
            overflow: hidden;
            background: #1a1a1a;
            transition: var(--transition);
        }

        .card-img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; transition: var(--transition); }

        .card:hover .card-img { opacity: 0.4; transform: scale(1.05); }

        /* Overlay Action (Le bouton + au survol) */
        .card-overlay {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            opacity: 0; transition: 0.3s;
            padding: 10px; text-align: center;
        }
        .card:hover .card-overlay { opacity: 1; }

        .movie-year { color: #ccc; font-size: 0.9rem; margin-bottom: 5px; }
        .movie-title { font-weight: bold; font-size: 1rem; margin-bottom: 15px; text-shadow: 0 2px 4px black; }

        /* État "Déjà ajouté" */
        .status-added { color: var(--match-green); font-size: 2rem; animation: popIn 0.3s; }

        /* TOAST NOTIFICATION */
        #toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; }

        @keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

        /* Mobile */
        @media (max-width: 768px) {
            .grid { grid-template-columns: repeat(3, 1fr); gap: 5px; }
            .search-input { font-size: 1rem; padding: 15px; }
            .card-overlay { opacity: 1; background: linear-gradient(to top, black, transparent); justify-content: flex-end; padding-bottom: 10px; }
            .card:hover .card-img { opacity: 1; transform: none; }
            .btn-add { width: 35px; height: 35px; font-size: 1rem; background: rgba(0,0,0,0.6); border: 1px solid white; margin-bottom: 5px;}
            .movie-title { font-size: 0.8rem; margin-bottom: 5px; }
            .movie-year { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="../index.php" class="logo">WATCHD</a>
    <a href="../index.php" class="close-btn" title="Fermer">×</a>
</nav>

<div class="search-container">
    <form method="GET" action="" class="search-box">
        <input type="text" name="recherche" class="search-input" placeholder="Titres, personnes, genres..." value="<?= htmlspecialchars($recherche) ?>" autofocus autocomplete="off">
        <button type="submit" style="background:none; border:none; cursor:pointer;" class="search-icon">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<?php if ($recherche): ?>
    <div class="results-title">
        <?php if(empty($resultats)): ?>
            Aucun résultat pour "<?= htmlspecialchars($recherche) ?>"
        <?php else: ?>
            Résultats pour "<?= htmlspecialchars($recherche) ?>"
        <?php endif; ?>
    </div>

    <div class="grid">
        <?php foreach ($resultats as $film): ?>
            <?php
            $tmdbId = $film['id'];
            $img = $film['poster_path'] ? "https://image.tmdb.org/t/p/w500".$film['poster_path'] : "../assets/no-poster.jpg";
            $annee = substr($film['release_date'] ?? '', 0, 4);

            // Vérifie si on l'a déjà
            $isOwned = in_array($tmdbId, $localIds);
            ?>

            <?php foreach ($resultats as $film): ?>
                <?php
                $tmdbId = $film['id'];
                $img = $film['poster_path'] ? "https://image.tmdb.org/t/p/w500".$film['poster_path'] : "../assets/no-poster.jpg";
                $annee = substr($film['release_date'] ?? '', 0, 4);

                // Vérifie si on l'a déjà
                $isOwned = in_array($tmdbId, $localIds);
                ?>

                <a href="detailFilm.php?id=<?= $tmdbId ?>" class="card">
                    <img src="<?= $img ?>" class="card-img" loading="lazy">

                    <div class="card-overlay">
                        <div class="movie-title"><?= htmlspecialchars($film['title']) ?></div>
                        <div class="movie-year"><?= $annee ?></div>

                        <div class="action-icon">
                            <?php if ($isOwned): ?>
                                <i class="fas fa-check-circle status-added" title="Déjà dans la collection"></i>
                            <?php else: ?>
                                <i class="fas fa-info-circle" style="font-size: 2rem; color: white;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div id="toast-container"></div>

</body>
</html>