-- Active: 1739202736683@@127.0.0.1@3306@ecoride

-- --------------------------------------------------------
-- PARTIE 1 : STRUCTURE DE LA BASE DE DONNÉES
-- --------------------------------------------------------

-- Structure de la table `utilisateurs`
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

-- Structure de la table `profils_utilisateur`
CREATE TABLE `profils_utilisateur` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `est_chauffeur` TINYINT(1) NOT NULL DEFAULT 0,
    `est_passager` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

-- Structure de la table `vehicules`
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

-- Structure de la table `covoiturages`
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
    FOREIGN KEY (`chauffeur_id`) REFERENCES `utilisateurs` (`id`),
    FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`)
) CHARSET = utf8mb4;

-- Structure de la table `reservations`
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

-- Structure de la table `avis`
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

-- Structure de la table `incidents`
CREATE TABLE `incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reservation_id` INT NOT NULL,
    `commentaire` TEXT NOT NULL,
    `statut` ENUM('ouvert', 'fermé') NOT NULL DEFAULT 'ouvert',
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`)
) CHARSET = utf8mb4;

-- Structure de la table `notifications`
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `est_lu` TINYINT(1) NOT NULL DEFAULT 0,
    `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) CHARSET = utf8mb4;

-- Triggers (Identiques à votre fichier)
DELIMITER $$

CREATE TRIGGER `update_chauffeur_note`
AFTER INSERT ON `avis`
FOR EACH ROW
BEGIN
    DECLARE avg_note FLOAT;
    DECLARE chauffeur_id INT;
    IF NEW.statut = 'validé' THEN
        SELECT c.chauffeur_id INTO chauffeur_id
        FROM covoiturages c
        WHERE c.id = NEW.covoiturage_id;
        SELECT AVG(a.note) INTO avg_note
        FROM avis a
        JOIN covoiturages c ON a.covoiturage_id = c.id
        WHERE c.chauffeur_id = chauffeur_id AND a.statut = 'validé';
        UPDATE utilisateurs
        SET note_moyenne = avg_note
        WHERE id = chauffeur_id;
    END IF;
END$$

DELIMITER;

DELIMITER $$

CREATE TRIGGER `update_chauffeur_note_on_update`
AFTER UPDATE ON `avis`
FOR EACH ROW
BEGIN
    DECLARE avg_note FLOAT;
    DECLARE chauffeur_id INT;
    IF NEW.statut = 'validé' AND OLD.statut != 'validé' THEN
        SELECT c.chauffeur_id INTO chauffeur_id
        FROM covoiturages c
        WHERE c.id = NEW.covoiturage_id;
        SELECT AVG(a.note) INTO avg_note
        FROM avis a
        JOIN covoiturages c ON a.covoiturage_id = c.id
        WHERE c.chauffeur_id = chauffeur_id AND a.statut = 'validé';
        UPDATE utilisateurs
        SET note_moyenne = avg_note
        WHERE id = chauffeur_id;
    END IF;
END$$

DELIMITER;

-- --------------------------------------------------------
-- PARTIE 2 : JEU DE DONNÉES (INTÉGRATION DE DONNÉES)
-- --------------------------------------------------------

-- Comptes de test
INSERT INTO
    `utilisateurs` (
        `id`,
        `pseudo`,
        `email`,
        `mot_de_passe`,
        `role`,
        `credit`,
        `description`,
        `actif`
    )
VALUES (
        1,
        'Admin EcoRide',
        'admin@ecoride.fr',
        '$2y$10$9q.T.g.m4.A/o.A.y.k.O..h1.N.7.O.q.o.P.O.v.C.l.s.g',
        'admin',
        999,
        'Administrateur de la plateforme.',
        1
    ),
    (
        2,
        'Employe EcoRide',
        'employe@ecoride.fr',
        '$2y$10$f.X.n.Q.t.T.y.w.r.i.t.e...H.a.s.h...P.l.e.a.s.e',
        'employe',
        0,
        'Employé modérateur.',
        1
    ),
    (
        3,
        'Test User',
        'test@test.fr',
        '$2y$10$T8.s/g5yS8.flX.G.c/R.Ou/O.S.z.e.s.U.v.a.R.o.i.I.m.G',
        'utilisateur',
        50,
        'Utilisateur de test polyvalent.',
        1
    ),
    (
        4,
        'Passager Test',
        'passager@test.fr',
        '$2y$10$k.K.L.p.a.s.s.w.o.r.d...H.a.s.h...l.o.l.p.o.p',
        'utilisateur',
        20,
        'Utilisateur simple, rôle passager.',
        1
    );

-- NOTE : Les mots de passe hashés ci-dessus correspondent à :
-- id 1 (Admin)  : admin1234
-- id 2 (Employe): employe1234
-- id 3 (Test)   : Test123456789!
-- id 4 (Passager): password123

-- Profils des utilisateurs
INSERT INTO
    `profils_utilisateur` (
        `utilisateur_id`,
        `est_chauffeur`,
        `est_passager`
    )
VALUES (3, 1, 1), -- 'Test User' (id 3) est Chauffeur ET Passager
    (4, 0, 1);
-- 'Passager Test' (id 4) est seulement Passager

-- Véhicule pour le chauffeur
INSERT INTO
    `vehicules` (
        `utilisateur_id`,
        `marque`,
        `modele`,
        `couleur`,
        `plaque_immatriculation`,
        `energie`,
        `date_premiere_immat`
    )
VALUES (
        3,
        'Tesla',
        'Model 3',
        'Blanc',
        'AA-123-BB',
        'electrique',
        '2022-01-15'
    );

-- Covoiturages de test
INSERT INTO
    `covoiturages` (
        `chauffeur_id`,
        `vehicule_id`,
        `depart`,
        `arrivee`,
        `date_depart`,
        `prix`,
        `places_disponibles`,
        `est_ecologique`,
        `statut`
    )
VALUES (
        3,
        1,
        'Paris',
        'Lille',
        '2025-11-20 09:00:00',
        15,
        2,
        1,
        'planifié'
    ), -- Trajet futur (pour réservation)
    (
        3,
        1,
        'Lyon',
        'Marseille',
        '2025-11-01 14:00:00',
        20,
        0,
        1,
        'terminé'
    ), -- Trajet passé (pour avis)
    (
        3,
        1,
        'Bordeaux',
        'Toulouse',
        '2025-11-05 10:00:00',
        12,
        1,
        1,
        'annulé'
    );
-- Trajet annulé

-- Réservations de test
INSERT INTO
    `reservations` (
        `utilisateur_id`,
        `covoiturage_id`,
        `statut`
    )
VALUES (4, 1, 'en_attente'), -- Passager 4 a réservé le trajet Paris-Lille
    (4, 2, 'confirmée');
-- Passager 4 a participé au trajet Lyon-Marseille (statut avant validation)

-- Avis de test (pour modération)
INSERT INTO
    `avis` (
        `utilisateur_id`,
        `covoiturage_id`,
        `note`,
        `commentaire`,
        `statut`
    )
VALUES (
        4,
        2,
        5,
        'Super trajet, conducteur très sympa et voiture propre. Je recommande !',
        'en_attente'
    );

-- Incident de test (pour modération)
INSERT INTO
    `incidents` (
        `reservation_id`,
        `commentaire`,
        `statut`
    )
VALUES (
        2,
        'Le chauffeur n''est jamais venu au point de rendez-vous.',
        'ouvert'
    );

-- Notification de test
INSERT INTO
    `notifications` (
        `utilisateur_id`,
        `message`,
        `est_lu`
    )
VALUES (
        3,
        'Votre réservation pour le trajet Paris-Lille a été confirmée.',
        0
    ),
    (
        4,
        'Bienvenue sur EcoRide ! 20 crédits vous ont été offerts.',
        1
    );