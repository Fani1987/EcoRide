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

### 6. Lancer le serveur

php -S localhost:8000

Puis accéder à <http://localhost:8000>

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
