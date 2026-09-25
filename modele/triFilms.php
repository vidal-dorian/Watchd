<?php
    function scannerDossierFilms()
    {
        // 1. Définir où regarder (C'est le chemin INTERNE au conteneur)
        $dossier = '/mnt/films';

        // 2. Vérifier si le dossier est bien accessible (Débogage)
        if (!is_dir($dossier)) {
            return ["Erreur : Le dossier $dossier est introuvable. Vérifie ton docker-compose."];
        }

        // 3. Chercher les fichiers
        // Le motif *.{mp4,mkv,avi} cherche toutes ces extensions
        // GLOB_BRACE permet d'utiliser les accolades {}
        $fichiers = glob($dossier . '/*.{mp4,mkv,avi,MKV,MP4}', GLOB_BRACE);

        // 4. Si c'est vide ou erreur
        if ($fichiers === false || empty($fichiers)) {
            return []; // On retourne un tableau vide
        }

        // 5. Nettoyage : On ne veut souvent que le nom du fichier, pas le chemin complet
        $listePropre = [];
        foreach ($fichiers as $cheminComplet) {
            // basename() garde juste "Avatar.mkv" et enlève "/mnt/films/"
            $listePropre[] = basename($cheminComplet);
        }

        return $listePropre;
    }
