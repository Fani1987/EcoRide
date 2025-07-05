// public/Router/allRoutes.js
import Route from "./Route.js";

export const allRoutes = [
  new Route("/", "Accueil", "/get-view/home"), // Endpoint pour la vue home.php
  new Route("/login", "Connexion/Deconnexion", "/get-view/login"), // Endpoint pour la vue login.php
  new Route("/register", "Inscription", "/get-view/register"), // Endpoint pour la vue register.php
  new Route("/profile", "Profil", "/get-view/profile"), // Endpoint pour la vue profile.php
  new Route("/legalNotice", "Mentions légales", "/get-view/legalNotice"), // Endpoint pour la vue legalNotice.php
  new Route("/covoiturage", "Covoiturages", "/get-view/covoiturage"), // Endpoint pour la vue covoiturage.php

  // Pour le détail du covoiturage, si le contenu est aussi généré dynamiquement par PHP
  // et que le JS spécifique dépend du contenu HTML chargé.
  new Route(
    "/covoiturage-detail",
    "Détail covoiturage",
    "/get-view/covoiturage-detail", // Endpoint pour la vue covoiturage-detail.php
    "/Router/CovoiturageDetail.js" // Le chemin du JS reste le même (dans public/Router)
  ),

  new Route("/employees", "Employés", "/get-view/employees"), // Endpoint pour la vue employees.php
  new Route("/admin", "Administration", "/get-view/admin"), // Endpoint pour la vue admin.php
];

export const websiteName = "EcoRide";
