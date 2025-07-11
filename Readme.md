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
- **Système de Rôles :** Espace Employé pour la modération et Espace Admin pour la supervision.
- **Cycle de vie des trajets :** Démarrage, fin, annulation et validation des trajets.
- **Système de notifications :** Notifications sur le profil pour les événements clés.
- **Économie de crédits :** Achat de crédits et système de paiement pour la réservation et la publication.

## 🛠 Technologies Utilisées

- **Backend :** PHP
- **Base de Données :** MySQL / MariaDB (via PDO) / MongoDB (géstion des préférences)
- **Frontend :** HTML, CSS (Bootstrap), JavaScript
- **Serveur Web :** Apache / Nginx (généralement avec XAMPP/WAMP pour le développement local)

- **PHPMailer :** Pour la gestion de l'envoi (simulé) des e-mails.
- **Dotenv :** Pour la gestion des variables d'environnement.
- **Chart.js :** Pour l'affichage des graphiques dans le dashboard admin

## 🚀 Installation et Démarrage

Suivez ces étapes pour configurer et lancer le projet EcoRide sur votre machine locale.

### 1. Prérequis

Assurez-vous d'avoir les éléments suivants installés :

- **PHP** (version 8.x recommandée)
- **MySQL** ou **MariaDB**
- **Un serveur web** (Apache ou Nginx), souvent fourni via XAMPP, WAMP ou MAMP.

### 2. Cloner le projet

    ```bash
    git clone [Dépôt GitHub de mon projet](https://github.com/Fani1987/EcoRide)
    cd EcoRide
    ```

### 3. Installer les dépendances PHP

composer install

### 4. Installer les dépendances front-end

npm install

### 5. Configuration de l’environnement

Créer un fichier .env à la racine avec les variables suivantes :

DB_HOST=localhost
DB_NAME=ecoride
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

MONGO_DB_HOST=localhost
MONGO_DB_PORT=27017
MONGO_DB_NAME=ecoride

MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_USERNAME=xxxxxxxx
MAIL_PASSWORD=xxxxxxxx
MAIL_PORT=2525

### 6. Importer la Base de Données

Une fois votre base de données ecoride créée et votre fichier .env configuré, vous devez importer la structure des tables et les données initiales.

**Avec un outil graphique (phpMyAdmin) :**

Connectez-vous à phpMyAdmin.

Sélectionnez votre base de données ecoride dans la liste à gauche.

Allez dans l'onglet "Importer".

Cliquez sur "Parcourir" et sélectionnez le fichier .sql (ecoride.sql) qui se trouve à la racine du projet.

Cliquez sur "Exécuter" en bas de la page.

**(Alternative) En ligne de commande :**

Ouvrez un terminal, naviguez jusqu'à la racine de votre projet et exécutez la commande suivante (en remplaçant root par votre nom d'utilisateur MySQL si nécessaire) :

mysql -u root -p ecoride < ecoride.sql

Le terminal vous demandera le mot de passe de votre base de données.

### 7 Lancer le serveur

Configurer un Hôte Virtuel (Virtual Host) dans votre WAMP/XAMPP qui pointe vers le dossier racine de votre projet, puis y accéder via l'URL que vous avez définie (ex: <http://ecoride.local>)

## 🗂️ Structure du projet

├── index.php # Point d’entrée principal
├── composer.json # Dépendances PHP
├── package.json # Dépendances JS
├── assets # css et images
├── src/
│ └── Controllers/ # Contrôleurs MVC
│ └── Core/ # Connexion BDD (Database.php)
├── views/ # Fichiers de vues HTML/PHP
├── header_template.php # En-tête commun
├── footer_template.php # Pied de page commun

## 👤 Auteur

Estefania Capitao
📧 <estefania.capitao@gmail.com>

## 📄 Licence

Ce projet est sous licence propriétaire. Toute reproduction ou diffusion sans autorisation est interdite.
