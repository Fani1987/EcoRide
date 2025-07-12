SET FOREIGN_KEY_CHECKS = 0;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : sam. 12 juil. 2025 à 10:56
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!40101 SET NAMES utf8mb4 */
;

--
-- Base de données : `ecoride`
--

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;

CREATE TABLE IF NOT EXISTS `avis` (
    `id` int NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int NOT NULL,
    `covoiturage_id` int NOT NULL,
    `note` tinyint NOT NULL,
    `commentaire` text,
    `statut` enum(
        'en_attente',
        'validé',
        'refusé'
    ) NOT NULL DEFAULT 'en_attente',
    PRIMARY KEY (`id`),
    KEY `utilisateur_id` (`utilisateur_id`),
    KEY `covoiturage_id` (`covoiturage_id`)
) ENGINE = MyISAM AUTO_INCREMENT = 4 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `avis`
--

INSERT INTO
    `avis` (
        `id`,
        `utilisateur_id`,
        `covoiturage_id`,
        `note`,
        `commentaire`,
        `statut`
    )
VALUES (
        1,
        1,
        8,
        5,
        'Excellent trajet, chauffeur très agréable et ponctuel ! Je recommande.',
        'validé'
    ),
    (
        2,
        2,
        8,
        4,
        'Bonne expérience, le trajet s\'est bien passé.',
        'validé'
    ),
    (
        3,
        1,
        9,
        5,
        'Parfait ! Trajet rapide et efficace. Fani1987 est un super chauffeur.',
        'validé'
    );

-- --------------------------------------------------------

--
-- Structure de la table `covoiturages`
--

DROP TABLE IF EXISTS `covoiturages`;

CREATE TABLE IF NOT EXISTS `covoiturages` (
    `id` int NOT NULL AUTO_INCREMENT,
    `chauffeur_id` int DEFAULT NULL,
    `vehicule_id` int DEFAULT NULL,
    `depart` varchar(100) DEFAULT NULL,
    `arrivee` varchar(100) DEFAULT NULL,
    `date_depart` datetime DEFAULT NULL,
    `date_arrivee` datetime DEFAULT NULL,
    `prix` decimal(5, 2) DEFAULT NULL,
    `places_disponibles` int DEFAULT NULL,
    `est_ecologique` tinyint(1) DEFAULT NULL,
    `statut` enum(
        'planifié',
        'en_cours',
        'terminé',
        'annulé'
    ) NOT NULL DEFAULT 'planifié',
    `duree` varchar(50) GENERATED ALWAYS AS (
        concat(
            floor(
                (
                    time_to_sec(
                        timediff(`date_arrivee`, `date_depart`)
                    ) / 3600
                )
            ),
            _utf8mb4 'h ',
            lpad(
                floor(
                    (
                        (
                            time_to_sec(
                                timediff(`date_arrivee`, `date_depart`)
                            ) % 3600
                        ) / 60
                    )
                ),
                2,
                _utf8mb4 '0'
            ),
            _utf8mb4 'min'
        )
    ) STORED,
    PRIMARY KEY (`id`),
    KEY `fk_covoiturages_chauffeur_id` (`chauffeur_id`),
    KEY `fk_covoiturages_vehicule_id` (`vehicule_id`)
) ENGINE = InnoDB AUTO_INCREMENT = 13 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `covoiturages`
--

INSERT INTO
    `covoiturages` (
        `id`,
        `chauffeur_id`,
        `vehicule_id`,
        `depart`,
        `arrivee`,
        `date_depart`,
        `date_arrivee`,
        `prix`,
        `places_disponibles`,
        `est_ecologique`,
        `statut`
    )
VALUES (
        1,
        1,
        1,
        'Paris',
        'Lyon',
        '2025-07-05 08:00:00',
        '2025-07-05 13:00:00',
        35.00,
        3,
        0,
        'planifié'
    ),
    (
        2,
        3,
        2,
        'Marseille',
        'Nice',
        '2025-07-06 10:30:00',
        '2025-07-06 12:30:00',
        20.00,
        2,
        1,
        'planifié'
    ),
    (
        3,
        1,
        1,
        'Lyon',
        'Paris',
        '2025-07-07 14:00:00',
        '2025-07-07 19:00:00',
        30.00,
        4,
        0,
        'planifié'
    ),
    (
        6,
        3,
        2,
        'Caen',
        'Rennes',
        '2025-07-20 09:00:00',
        '2025-07-20 11:30:00',
        20.00,
        3,
        1,
        'planifié'
    ),
    (
        7,
        3,
        2,
        'Rennes',
        'Nantes',
        '2025-07-22 14:00:00',
        '2025-07-22 15:30:00',
        12.50,
        2,
        1,
        'planifié'
    ),
    (
        8,
        3,
        2,
        'Caen',
        'Rennes',
        '2025-07-20 09:00:00',
        '2025-07-20 11:30:00',
        20.00,
        3,
        1,
        'planifié'
    ),
    (
        9,
        3,
        2,
        'Rennes',
        'Nantes',
        '2025-07-22 14:00:00',
        '2025-07-22 15:30:00',
        12.50,
        2,
        1,
        'planifié'
    ),
    (
        10,
        7,
        3,
        'Marseilles',
        'Paris',
        '2025-07-20 10:00:00',
        NULL,
        40.00,
        3,
        1,
        'annulé'
    ),
    (
        11,
        7,
        3,
        'paris',
        'marseille',
        '2025-07-20 10:19:00',
        NULL,
        50.00,
        1,
        1,
        'terminé'
    ),
    (
        12,
        7,
        3,
        'Caen',
        'Paris',
        '2025-07-11 12:00:00',
        NULL,
        20.00,
        0,
        1,
        'annulé'
    );

-- --------------------------------------------------------

--
-- Structure de la table `incidents`
--

DROP TABLE IF EXISTS `incidents`;

CREATE TABLE IF NOT EXISTS `incidents` (
    `id` int NOT NULL AUTO_INCREMENT,
    `reservation_id` int NOT NULL,
    `commentaire` text NOT NULL,
    `statut` enum('ouvert', 'fermé') NOT NULL DEFAULT 'ouvert',
    `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_incidents_reservation_id` (`reservation_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int NOT NULL,
    `message` varchar(255) NOT NULL,
    `est_lu` tinyint(1) NOT NULL DEFAULT '0',
    `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_notifications_utilisateur_id` (`utilisateur_id`)
) ENGINE = InnoDB AUTO_INCREMENT = 2 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO
    `notifications` (
        `id`,
        `utilisateur_id`,
        `message`,
        `est_lu`,
        `date_creation`
    )
VALUES (
        1,
        3,
        'Le trajet de  à  a été annulé par le chauffeur.',
        1,
        '2025-07-11 10:22:07'
    );

-- --------------------------------------------------------

--
-- Structure de la table `profils_utilisateur`
--

DROP TABLE IF EXISTS `profils_utilisateur`;

CREATE TABLE IF NOT EXISTS `profils_utilisateur` (
    `utilisateur_id` int NOT NULL,
    `est_chauffeur` tinyint(1) DEFAULT '0',
    `est_passager` tinyint(1) DEFAULT '0',
    PRIMARY KEY (`utilisateur_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `profils_utilisateur`
--

INSERT INTO
    `profils_utilisateur` (
        `utilisateur_id`,
        `est_chauffeur`,
        `est_passager`
    )
VALUES (1, 1, 1),
    (2, 0, 1),
    (3, 1, 1),
    (5, 0, 1),
    (7, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

DROP TABLE IF EXISTS `reservations`;

CREATE TABLE IF NOT EXISTS `reservations` (
    `id` int NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int NOT NULL,
    `covoiturage_id` int NOT NULL,
    `date_reservation` datetime NOT NULL,
    `statut` enum(
        'en_attente',
        'confirmée',
        'refusée',
        'validée',
        'en_litige',
        'annulée'
    ) NOT NULL DEFAULT 'en_attente',
    PRIMARY KEY (`id`),
    KEY `fk_reservations_utilisateur_id` (`utilisateur_id`),
    KEY `fk_reservations_covoiturage_id` (`covoiturage_id`)
) ENGINE = InnoDB AUTO_INCREMENT = 3 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO
    `reservations` (
        `id`,
        `utilisateur_id`,
        `covoiturage_id`,
        `date_reservation`,
        `statut`
    )
VALUES (
        1,
        3,
        10,
        '2025-07-11 10:35:11',
        'annulée'
    ),
    (
        2,
        3,
        12,
        '2025-07-11 11:42:15',
        'annulée'
    );

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;

CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id` int NOT NULL AUTO_INCREMENT,
    `pseudo` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `mot_de_passe` varchar(255) NOT NULL,
    `role` enum(
        'utilisateur',
        'employe',
        'admin'
    ) DEFAULT 'utilisateur',
    `actif` tinyint(1) NOT NULL DEFAULT '1',
    `credit` int DEFAULT '20',
    `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `note_moyenne` decimal(3, 2) DEFAULT NULL,
    `description` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE = InnoDB AUTO_INCREMENT = 9 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO
    `utilisateurs` (
        `id`,
        `pseudo`,
        `email`,
        `mot_de_passe`,
        `role`,
        `actif`,
        `credit`,
        `date_creation`,
        `note_moyenne`,
        `description`
    )
VALUES (
        1,
        'JeanDupond',
        'jean.dupond@example.com',
        '$2y$10$E/GL9n9PyYWjc6mMbQMDU.TwDBmAgTKEmzIBGnJRoY8EnM.3rruRO',
        'utilisateur',
        1,
        20,
        '2025-07-04 08:09:00',
        4.50,
        'Passionné de covoiturage et de voyages.'
    ),
    (
        2,
        'MarieCurie',
        'marie.curie@example.com',
        '$2y$10$E/GL9n9PyYWjc6mMbQMDU.TwDBmAgTKEmzIBGnJRoY8EnM.3rruRO',
        'utilisateur',
        1,
        20,
        '2025-07-04 08:09:00',
        3.80,
        'Aime discuter pendant les trajets.'
    ),
    (
        3,
        'Fani1987',
        'Estefania.capitao@gmail.com',
        '$2y$10$E/GL9n9PyYWjc6mMbQMDU.TwDBmAgTKEmzIBGnJRoY8EnM.3rruRO',
        'utilisateur',
        1,
        70,
        '2025-07-04 08:09:00',
        4.67,
        'En route pour de nouvelles aventures!'
    ),
    (
        5,
        'Marcel25',
        'marcel@test.fr',
        '$2y$10$RphIgbNA.BsPNS2vAUhd8O4wAFruKDxBX5kuGW8FHpPZIjrwzn94u',
        'utilisateur',
        1,
        0,
        '2025-07-08 05:51:07',
        NULL,
        '20'
    ),
    (
        6,
        'AdminEcoRide',
        'admin@ecoride.fr',
        '$2y$10$VCbW8cEcTQziDExMbYqp1uRiWfsBAPBo8mz0oXhsftiajXyspPgX6',
        'admin',
        1,
        100,
        '2025-07-08 10:16:32',
        NULL,
        'Compte administrateur principal'
    ),
    (
        7,
        'Test',
        'test@test.fr',
        '$2y$10$l0rp3MkQb0Jc/LtSsEcYzueqE4.4FP6AD.WpHUTwNWcZi7nwJa2H2',
        'utilisateur',
        1,
        44,
        '2025-07-08 12:07:40',
        NULL,
        'Je teste les fonctionnalités du site EcoRide.'
    ),
    (
        8,
        'EmployeEcoride',
        'employe@ecoride.fr',
        '$2y$10$4zN97KpcEiKyEuZrBQeWKu2DNitICJfA3tFrKyA.2aYqWbl0UJd.G',
        'employe',
        1,
        20,
        '2025-07-10 16:21:25',
        NULL,
        NULL
    );

-- --------------------------------------------------------

--
-- Structure de la table `vehicules`
--

DROP TABLE IF EXISTS `vehicules`;

CREATE TABLE IF NOT EXISTS `vehicules` (
    `id` int NOT NULL AUTO_INCREMENT,
    `utilisateur_id` int DEFAULT NULL,
    `marque` varchar(50) DEFAULT NULL,
    `modele` varchar(50) DEFAULT NULL,
    `couleur` varchar(30) DEFAULT NULL,
    `energie` enum(
        'essence',
        'diesel',
        'electrique',
        'hybride'
    ) DEFAULT NULL,
    `plaque_immatriculation` varchar(20) DEFAULT NULL,
    `date_premiere_immat` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_vehicules_utilisateur_id` (`utilisateur_id`)
) ENGINE = InnoDB AUTO_INCREMENT = 4 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `vehicules`
--

INSERT INTO
    `vehicules` (
        `id`,
        `utilisateur_id`,
        `marque`,
        `modele`,
        `couleur`,
        `energie`,
        `plaque_immatriculation`,
        `date_premiere_immat`
    )
VALUES (
        1,
        1,
        'Toyota',
        'Prius',
        'Gris',
        'hybride',
        'AB-123-CD',
        '2020-05-15'
    ),
    (
        2,
        3,
        'Tesla',
        'Model 3',
        'Bleu',
        'electrique',
        'EF-456-GH',
        '2023-01-20'
    ),
    (
        3,
        7,
        'Tesla',
        'Cybertruck',
        'noir',
        'electrique',
        'ab-123-bc',
        '2025-05-08'
    );

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `covoiturages`
--
ALTER TABLE `covoiturages`
ADD CONSTRAINT `fk_covoiturages_chauffeur_id` FOREIGN KEY (`chauffeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_covoiturages_vehicule_id` FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `incidents`
--
ALTER TABLE `incidents`
ADD CONSTRAINT `fk_incidents_reservation_id` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
ADD CONSTRAINT `fk_notifications_utilisateur_id` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `profils_utilisateur`
--
ALTER TABLE `profils_utilisateur`
ADD CONSTRAINT `fk_profils_utilisateur_id` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
ADD CONSTRAINT `fk_reservations_covoiturage_id` FOREIGN KEY (`covoiturage_id`) REFERENCES `covoiturages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_reservations_utilisateur_id` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `vehicules`
--
ALTER TABLE `vehicules`
ADD CONSTRAINT `fk_vehicules_utilisateur_id` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;