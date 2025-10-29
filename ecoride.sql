-- Active: 1739202736683@@127.0.0.1@3306@ecoride
-- Structure de la table `utilisateurs`
--
CREATE TABLE `utilisateurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pseudo` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `mot_de_passe` VARCHAR(255) NOT NULL,
    `role` ENUM(
        'utilisateur',
        'employe',
        'admin'
    ) NOT NULL DEFAULT 'utilisateur',
    `credit` FLOAT NOT NULL DEFAULT 20,
    `description` TEXT,
    `note_moyenne` FLOAT DEFAULT NULL,
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) CHARSET = utf8mb4;

--
-- Structure de la table `profils_utilisateur`
-- (Gère les rôles passager/chauffeur)
--
CREATE TABLE `profils_utilisateur` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `est_chauffeur` TINYINT(1) NOT NULL DEFAULT 0,
    `est_passager` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

--
-- Structure de la table `vehicules`
--
CREATE TABLE `vehicules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `marque` VARCHAR(100) NOT NULL,
    `modele` VARCHAR(100) NOT NULL,
    `couleur` VARCHAR(50),
    `plaque_immatriculation` VARCHAR(20) NOT NULL,
    `energie` ENUM(
        'essence',
        'diesel',
        'electrique',
        'hybride',
        'gpl'
    ) NOT NULL,
    `date_premiere_immat` DATE NOT NULL,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

--
-- Structure de la table `covoiturages`
--
CREATE TABLE `covoiturages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chauffeur_id` INT NOT NULL,
    `vehicule_id` INT NOT NULL,
    `depart` VARCHAR(255) NOT NULL,
    `arrivee` VARCHAR(255) NOT NULL,
    `date_depart` DATETIME NOT NULL,
    `duree` VARCHAR(50) DEFAULT NULL, -- ex: "2h30"
    `date_arrivee` DATETIME DEFAULT NULL,
    `prix` FLOAT NOT NULL,
    `places_disponibles` INT NOT NULL,
    `est_ecologique` TINYINT(1) NOT NULL DEFAULT 0,
    `statut` ENUM(
        'planifié',
        'en_cours',
        'terminé',
        'annulé'
    ) NOT NULL DEFAULT 'planifié',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`chauffeur_id`) REFERENCES `utilisateurs` (`id`), -- Pas de cascade pour garder l'historique
    FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`) -- Pas de cascade
) CHARSET = utf8mb4;

--
-- Structure de la table `reservations`
--
CREATE TABLE `reservations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `covoiturage_id` INT NOT NULL,
    `date_reservation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `statut` ENUM(
        'en_attente',
        'confirmée',
        'refusée',
        'annulée',
        'validée',
        'en_litige'
    ) NOT NULL DEFAULT 'en_attente',
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`),
    FOREIGN KEY (`covoiturage_id`) REFERENCES `covoiturages` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

--
-- Structure de la table `avis`
--
CREATE TABLE `avis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL, -- Qui a écrit l'avis
    `covoiturage_id` INT NOT NULL, -- À propos de quel trajet
    `note` INT NOT NULL,
    `commentaire` TEXT,
    `statut` ENUM(
        'en_attente',
        'validé',
        'refusé'
    ) NOT NULL DEFAULT 'en_attente',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`),
    FOREIGN KEY (`covoiturage_id`) REFERENCES `covoiturages` (`id`)
) CHARSET = utf8mb4;

--
-- Structure de la table `incidents`
--
CREATE TABLE `incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reservation_id` INT NOT NULL,
    `commentaire` TEXT NOT NULL,
    `statut` ENUM('ouvert', 'fermé') NOT NULL DEFAULT 'ouvert',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`)
) CHARSET = utf8mb4;

--
-- Structure de la table `notifications`
--
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `est_lu` TINYINT(1) NOT NULL DEFAULT 0,
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

-- --------------------------------------------------------
--
-- Trigger pour mettre à jour la note moyenne du chauffeur
--
-- --------------------------------------------------------

DELIMITER $$

CREATE TRIGGER `update_chauffeur_note`
AFTER INSERT ON `avis`
FOR EACH ROW
BEGIN
    DECLARE avg_note FLOAT;
    DECLARE chauffeur_id INT;

    -- S'exécute uniquement si l'avis est marqué 'validé' (ou si on l'insère déjà comme 'validé')
    IF NEW.statut = 'validé' THEN
        
        -- 1. Trouver l'ID du chauffeur basé sur le covoiturage de l'avis
        SELECT c.chauffeur_id INTO chauffeur_id
        FROM covoiturages c
        WHERE c.id = NEW.covoiturage_id;

        -- 2. Calculer la nouvelle note moyenne de ce chauffeur
        SELECT AVG(a.note) INTO avg_note
        FROM avis a
        JOIN covoiturages c ON a.covoiturage_id = c.id
        WHERE c.chauffeur_id = chauffeur_id AND a.statut = 'validé';

        -- 3. Mettre à jour la table utilisateurs
        UPDATE utilisateurs
        SET note_moyenne = avg_note
        WHERE id = chauffeur_id;
    END IF;
END$$

DELIMITER;

--
-- Trigger pour la mise à jour (si un employé valide un avis 'en_attente')
--
DELIMITER $$

CREATE TRIGGER `update_chauffeur_note_on_update`
AFTER UPDATE ON `avis`
FOR EACH ROW
BEGIN
    DECLARE avg_note FLOAT;
    DECLARE chauffeur_id INT;

    -- S'exécute si le statut passe à 'validé' (et qu'il ne l'était pas avant)
    IF NEW.statut = 'validé' AND OLD.statut != 'validé' THEN
        
        -- 1. Trouver l'ID du chauffeur
        SELECT c.chauffeur_id INTO chauffeur_id
        FROM covoiturages c
        WHERE c.id = NEW.covoiturage_id;

        -- 2. Calculer la nouvelle note moyenne
        SELECT AVG(a.note) INTO avg_note
        FROM avis a
        JOIN covoiturages c ON a.covoiturage_id = c.id
        WHERE c.chauffeur_id = chauffeur_id AND a.statut = 'validé';

        -- 3. Mettre à jour la table utilisateurs
        UPDATE utilisateurs
        SET note_moyenne = avg_note
        WHERE id = chauffeur_id;
    END IF;
END$$

DELIMITER;