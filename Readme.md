# EcoRide

## 🚗 Application de Covoiturage Écologique

EcoRide est une plateforme web innovante conçue pour faciliter le covoiturage en mettant l'accent sur l'aspect écologique. Elle connecte chauffeurs et passagers pour des trajets partagés, tout en valorisant les véhicules respectueux de l'environnement grâce à un système de notation et de classification.

## ✨ Fonctionnalités

- **Gestion des Utilisateurs :** Inscription, connexion, profils utilisateurs (chauffeur, passager, ou les deux).
- **Profil Utilisateur Détaillé :** Informations personnelles, véhicules enregistrés (pour les chauffeurs), historique des trajets proposés et réservés.
- **Système de Notation :** Les passagers peuvent noter et laisser des commentaires sur les chauffeurs après un covoiturage.
- **Note Moyenne du Chauffeur :** Une note moyenne est calculée et affichée sur le profil de chaque chauffeur, basée sur les avis reçus.
- **Gestion des Véhicules :** Les chauffeurs peuvent enregistrer leurs véhicules, avec des détails sur la marque, le modèle, la couleur, l'énergie (électrique, hybride, essence, diesel) et l'immatriculation.
- **Covoiturages Écologiques :** Les trajets sont marqués comme "écologiques" si le véhicule utilisé est électrique, mettant en avant les options plus vertes.
- **Historique des Trajets :** Consultation des trajets proposés (pour les chauffeurs) et réservés (pour les passagers).
- **Préférences Utilisateur :** Possibilité de définir des préférences de voyage (fumeurs, animaux, musique, etc.).
- **Formulaire d'Inscription Dynamique :** Affichage conditionnel des champs spécifiques au véhicule lors de l'inscription pour les chauffeurs.

## 🛠 Technologies Utilisées

- **Backend :** PHP
- **Base de Données :** MySQL / MariaDB (via PDO)
- **Frontend :** HTML, CSS (Bootstrap), JavaScript
- **Serveur Web :** Apache / Nginx (généralement avec XAMPP/WAMP pour le développement local)

## 🚀 Installation et Démarrage

Suivez ces étapes pour configurer et lancer le projet EcoRide sur votre machine locale.

### Prérequis

Assurez-vous d'avoir les éléments suivants installés :

- **PHP** (version 8.x recommandée)
- **MySQL** ou **MariaDB**
- **Un serveur web** (Apache ou Nginx), souvent fourni via XAMPP, WAMP ou MAMP.

### Étapes d'installation

1.  **Cloner le dépôt :**

    ```bash
    git clone [https://github.com/votre_utilisateur/EcoRide.git](https://github.com/votre_utilisateur/EcoRide.git)
    cd EcoRide
    ```

    _(Remplacez `https://github.com/votre_utilisateur/EcoRide.git` par l'URL de votre propre dépôt si vous en utilisez un.)_

2.  **Configuration de la base de données :**

    - Créez une nouvelle base de données MySQL nommée `ecoride`.
    - Importez le schéma de la base de données fourni :

      ```bash
      # Via ligne de commande MySQL (remplacez [utilisateur] et [mot_de_passe])
      mysql -u [utilisateur] -p ecoride < ecoride.sql
      ```

      Ou utilisez un outil comme **phpMyAdmin** :

      - Accédez à phpMyAdmin.
      - Créez la base de données `ecoride`.
      - Sélectionnez la base de données `ecoride`.
      - Allez dans l'onglet "Importer" et choisissez le fichier `ecoride.sql` pour l'importer.

    - **Appliquez les déclencheurs (Triggers) et modifications récentes :**
      Même si le fichier `ecoride.sql` contient les déclencheurs initiaux, il est crucial de s'assurer que le déclencheur `maj_note_moyenne_apres_insertion` est mis à jour pour le calcul de la moyenne du chauffeur. Exécutez cette requête après l'importation initiale :

      ```sql
      -- Dans votre outil de gestion de BDD (phpMyAdmin, MySQL Workbench, etc.)
      -- Sélectionnez la base de données `ecoride` et exécutez cette requête SQL :

      -- D'abord, renommer la table notations en avis (si ce n'est pas déjà fait)
      ALTER TABLE notations RENAME TO avis;

      -- Ensuite, supprimer l'ancien déclencheur s'il existe
      DROP TRIGGER IF EXISTS `maj_note_moyenne_apres_insertion`;

      DELIMITER $$
      CREATE TRIGGER `maj_note_moyenne_apres_insertion` AFTER INSERT ON `avis` FOR EACH ROW
      BEGIN
          DECLARE id_chauffeur_covoiturage INT;

          -- Récupérer l'ID du chauffeur associé au covoiturage noté
          SELECT chauffeur_id INTO id_chauffeur_covoiturage
          FROM covoiturages
          WHERE id = NEW.covoiturage_id;

          -- Mettre à jour la note moyenne de l'utilisateur (chauffeur)
          UPDATE utilisateurs
          SET note_moyenne = (
              SELECT ROUND(AVG(a.note), 2)
              FROM avis a
              JOIN covoiturages c ON a.covoiturage_id = c.id
              WHERE c.chauffeur_id = id_chauffeur_covoiturage
          )
          WHERE id = id_chauffeur_covoiturage;
      END$$
      DELIMITER ;
      ```

3.  **Configuration du serveur web (Apache/Nginx) :**

    - Placez les fichiers du projet dans le répertoire de votre serveur web (ex: `htdocs` pour Apache sous XAMPP).
    - Configurez votre serveur web pour pointer vers le dossier `public` de votre projet (si vous avez un dossier `public` comme point d'entrée pour les requêtes) ou directement à la racine si `index.php` est à la racine.
    - Assurez-vous que les réécritures d'URL (`mod_rewrite` pour Apache) sont activées pour permettre le routage de votre application (ex: `/profile/10` vers `index.php`).

4.  **Configuration de la connexion à la base de données (PHP) :**

    - Vous aurez probablement un fichier de configuration pour la base de données (ex: `config/database.php` ou similaire). Mettez à jour les identifiants de connexion (hôte, nom de la base de données, utilisateur, mot de passe) pour qu'ils correspondent à votre installation MySQL.
    - Voici un exemple de ce à quoi cela pourrait ressembler (adaptez le chemin et les informations d'identification) :

      ```php
      // config/database.php (Exemple)
      <?php
      $host = 'localhost';
      $db   = 'ecoride';
      $user = 'EstefaniaCapitao'; // Votre utilisateur MySQL
      $pass = 'Mael06012014!';     // Votre mot de passe MySQL
      $charset = 'utf8mb4';

      $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
      $options = [
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES   => false,
      ];

      try {
          $pdo = new PDO($dsn, $user, $pass, $options);
      } catch (\PDOException $e) {
          throw new \PDOException($e->getMessage(), (int)$e->getCode());
      }
      ```

5.  **Vérification de la fonction `renderView` :**
    Assurez-vous que votre fonction `renderView` (souvent définie dans `index.php` ou un fichier d'utilitaires) utilise l'extension `.php` pour inclure les vues, comme nous l'avons corrigé :
    ```php
    // Dans index.php ou votre fichier d'aide/routeur
    function renderView($viewName, $data = []) {
        extract($data); // Rend les variables du tableau $data disponibles dans la vue
        require_once __DIR__ . '/views/' . $viewName . '.php'; // Assurez-vous que c'est .php
    }
    ```
    _Si votre `index.php` contient directement `require_once 'views/profile.php'`, c'est également correct._

### Lancement de l'application

1.  Démarrez votre serveur Apache/Nginx et votre serveur MySQL.
2.  Ouvrez votre navigateur web et naviguez vers l'URL de votre projet (ex: `http://localhost/EcoRide` ou `http://ecoride.local` si vous avez configuré un hôte virtuel).

## 📄 Licence

Ce projet est sous licence [Nom de la Licence, ex: MIT]. Voir le fichier `LICENSE` pour plus de détails.

## 📞 Contact

Pour toute question ou suggestion, n'hésitez pas à me contacter :

- **Votre Nom/Pseudo :** Estefania Capitao
- **Votre Email :** <estefania.capitao@gmail.com>
- **Profil GitHub :** [Lien vers votre profil GitHub si applicable]
