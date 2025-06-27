import Route from "./Route.js";

//Définir ici vos routes
export const allRoutes = [
  new Route("/", "Accueil", "/Pages/home.html"),
  new Route("/login", "Connexion/Deconnexion", "/Pages/login.html"),
  new Route("/register", "Inscription", "/Pages/register.html"),
  new Route("/profile", "Profil", "/Pages/profile.html"),
  new Route("/legalNotice", "Mentions légales", "/Pages/legalNotice.html"),
  new Route("/covoiturage", "Covoiturages", "/Pages/covoiturage.html"),
  new Route("/employees", "Employés", "/Pages/employees.html"),
  new Route("/admin", "Administration", "/Pages/admin.html"),
];

//Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "EcoRide";
