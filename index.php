<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Toujours démarrer la session au début

// 1. Inclusion de la connexion à la base de données
require_once 'db_connection.php'; // Connexion à la base de données

// 2. Inclusion des contrôleurs
require_once 'controllers/AuthController.php'; // Gère login, register, logout
require_once 'controllers/CovoiturageController.php'; // Gère les covoiturages
require_once 'controllers/TrajetController.php'; // Gère l'ajout/réservation de trajets
require_once 'controllers/UserController.php'; // Gère le profil utilisateur


// 3. Fonction pour inclure les vues avec header et footer
// Déplacez la fonction renderView HORS de la condition if (!function_exists('renderView'))
// Laisser la condition est utile si votre fichier index.php est lui-même inclus ailleurs,
// mais pour un point d'entrée unique, c'est inutile et peut causer des problèmes d'analyse.
function renderView($viewName, $data = [])
{
    global $pdo; // Assurez-vous que $pdo est disponible si nécessaire dans les templates

    extract($data); // Rend les variables du tableau $data disponibles dans la vue

    // Inclusion du header
    require_once 'header_template.php';

    // Bufferisation du contenu de la vue
    ob_start();
    require_once 'views/' . $viewName . '.php'; // Assurez-vous que vos vues sont dans le dossier 'views/'
    $pageContent = ob_get_clean();
    echo $pageContent; // Affiche le contenu

    // Inclusion du footer
    require_once 'footer_template.php';
}

// 4. Récupération de l'URL demandée
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// 5. Système de routage
switch ($requestPath) {
    case '/':
        renderView('home'); // Affiche home.html
        break;

    case '/covoiturage':
        // Le CovoiturageController gère la récupération des filtres depuis $_GET
        CovoiturageController::showCovoituragePage($pdo, $_GET);
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::login($pdo, $_POST);
        } else {
            renderView('login'); // Affiche login.html
        }
        break;

    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::register($pdo, $_POST);
        } else {
            renderView('register'); // Affiche register.html
        }
        break;

    case '/logout':
        AuthController::logout(); // Appel de la méthode du contrôleur
        break;

    case '/profile':
        if (isset($_SESSION['user_id'])) {
            UserController::showProfilePage($pdo, $_SESSION['user_id']); // Appelle la méthode du UserController
        } else {
            header('Location: /login'); // Redirige si non connecté
            exit;
        }
        break;

    case '/legalNotice':
        renderView('legalNotice'); // Affiche legalNotice.html
        break;

    case '/admin':
        // Logique de vérification de rôle ici si nécessaire
        renderView('admin');
        break;

    case '/employees':
        renderView('employees');
        break;

    // --- Routes API (requêtes POST/GET par JavaScript) ---
    case '/api/ajouterTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::ajouterTrajet($pdo, $_POST);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Méthode non autorisée.']);
        }
        break;

    case '/api/reserverTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::reserverTrajet($pdo, $_POST);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée.']);
        }
        break;

    // Ajout d'une route pour la déconnexion
    case 'logout':
        AuthController::logout();
        break;

    default:
        http_response_code(404);
        renderView('404');
        break;
}
