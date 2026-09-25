CREATE DATABASE IF NOT EXISTS watchd;

USE watchd;

DROP TABLE IF EXISTS films;
DROP TABLE IF EXISTS sagas;

-- 2. Création de la table SAGAS (Les collections)
CREATE TABLE IF NOT EXISTS sagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT UNIQUE,
    nom VARCHAR(255) NOT NULL,
    poster_path VARCHAR(255),
    backdrop_path VARCHAR(255)
    );

-- 3. Création de la table FILMS
CREATE TABLE IF NOT EXISTS films (
    id INT AUTO_INCREMENT PRIMARY KEY,

    saga_id INT DEFAULT NULL,

    -- Infos TMDB
    tmdb_id INT NOT NULL UNIQUE,
    titre VARCHAR(255) NOT NULL,
    titre_original VARCHAR(255),

    -- Infos de tri demandées
    duree INT,
    date_sortie DATE,
    genres TEXT,

-- Infos visuelles & Texte
    synopsis TEXT,
    poster_path VARCHAR(255),
    backdrop_path VARCHAR(255),
    tagline VARCHAR(255),
    note_tmdb DECIMAL(3, 1),

    -- Infos Perso (NAS)
    chemin_fichier VARCHAR(512),
    vu BOOLEAN DEFAULT 0,
    favori BOOLEAN DEFAULT 0,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (saga_id) REFERENCES sagas(id) ON DELETE SET NULL
);