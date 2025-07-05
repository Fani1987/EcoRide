<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Toujours démarrer la session au début

// 1. Inclusion de l'autoloader de Composer (pour phpdotenv et futures classes)
require_once __DIR__ . '/vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 2. Connexion à la base de données (maintenant sécurisée via .env)
require_once 'db_connection.php';

// 3. Inclure les contrôleurs uniquement si leurs méthodes sont appelées
// Pour l'instant, nous allons les garder car ils sont appelés dans les routes API ci-dessous.
require_once 'controllers/AuthController.php';
require_once 'controllers/CovoiturageController.php';
require_once 'controllers/TrajetController.php';
require_once 'controllers/UserController.php';

// Le script PHP principal gère le squelette de l'application et les points d'API.
// Le routage des pages se fera côté client avec router.js.

// Fonction pour inclure les vues
function renderView($viewName, $data = [])
{
    extract($data); // Rend les variables du tableau $data disponibles dans la vue
    require_once __DIR__ . '/views/' . $viewName . '.php';
}

// Inclure le header
require_once 'header_template.php';

// Le conteneur principal où le contenu des pages sera injecté par JavaScript
echo '<div id="main-page">';
// Initialisation du contenu de la page d'accueil par défaut si nécessaire
// ou laisser le router.js charger la bonne page au chargement initial.
// Pour les routes HTML/JS, le contenu sera chargé par le front-end.
echo '</div>'; // Fin de main-page

// Gérer les routes API et les actions spécifiques côté serveur
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($requestUri) {
    case '/':
    case '/home':
        renderView('home');
        break;
    case '/covoiturage':
        CovoiturageController::showCovoituragePage($pdo, $_GET);
        break;
    case '/legalNotice':
        renderView('legalNotice');
        break;
    case '/profile':
        if (isset($_SESSION['user_id'])) {
            UserController::showProfilePage($pdo, $_SESSION['user_id']);
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez vous connecter pour accéder à votre profil.'];
            header("Location: /login");
            exit;
        }
        break;
    case '/employees':
        renderView('employees');
        break;
    case '/admin':
        renderView('admin');
        break;
    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::login($pdo, $_POST);
        } else {
            renderView('login');
        }
        break;
    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::register($pdo, $_POST);
        } else {
            renderView('register');
        }
        break;

    case '/api/trajet.php':
        // Cette route est gérée par trajet.php directement car c'est une API
        // Vous n'avez pas besoin de la traiter ici si trajet.php est un endpoint direct.
        // Sinon, si c'est censé être géré par un contrôleur:
        // TrajetController::getTrajetDetails($pdo, $_GET['id']);
        // Pour l'instant, on suppose que trajet.php est un fichier séparé accessible directement.
        // Si ce n'est pas le cas, il faudrait inclure ou appeler la logique ici.
        break;

    // API endpoints pour les actions POST
    case '/api/ajouterTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::ajouterTrajet($pdo, $_POST);
        } else {
            http_response_code(405); // méthode non autorisée
            // Répondre avec un message JSON
            echo json_encode(['error' => 'Méthode non autorisée pour ajouter un trajet.']);
        }
        break;

    case '/api/reserverTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::reserverTrajet($pdo, $_POST);
        } else {
            http_response_code(405); // méthode non autorisée
            // Répondre avec un message JSON
            echo json_encode(['error' => 'Méthode non autorisée pour réserver un trajet.']);
        }
        break;

    case '/logout': // La déconnexion est une action PHP
        AuthController::logout();
        break;

    // Toutes les autres routes (pages) sont gérées par JavaScript.

    default:
        // Pour toutes les requêtes de pages (GET), PHP ne fait rien d'autre que servir le squelette.
        // Le contenu spécifique de la page sera chargé par router.js.
        // Si la route n'est pas une API et n'est pas une page gérée par PHP, on peut inclure le 404.
        if (!file_exists(__DIR__ . '/pages/' . str_replace('/', '', $requestUri) . '.php')) {
            renderView('404');
            http_response_code(404);
        }
        break;
}

// Inclure le footer
require_once 'footer_template.php';
