<?php

/**
 * POINT D'ENTRÉE UNIQUE DE L'APPLICATION (FRONT CONTROLLER)
 * * Ce fichier intercepte 100% des requêtes HTTP grâce au fichier .htaccess.
 * Il agit comme un aiguilleur (Routeur) qui dirige la demande vers le bon Contrôleur.
 */

// 1. Démarrage de la session
// Indispensable pour mémoriser l'utilisateur connecté ($_SESSION['user_id'])
// entre les différentes pages. Doit être la toute première instruction.
session_start();

// 2. Chargement de l'autoloader de Composer
// C'est le mécanisme standard PHP (PSR-4) qui permet de charger automatiquement
// les classes (Controllers, Models) sans avoir à faire des 'require' partout.
require_once __DIR__ . '/vendor/autoload.php';

// 3. Importation des classes nécessaires (Namespaces)
// On déclare ici toutes les classes qu'on va utiliser dans le routeur.
use Dotenv\Dotenv;
use App\Controllers\AuthController;
use App\Controllers\CovoiturageController;
use App\Controllers\TrajetController;
use App\Controllers\UserController;
use App\Core\Database;
use App\Controllers\AdminController;
use App\Controllers\EmployeeController;
use App\Controllers\AvisController;
use App\Controllers\PaymentController;
use App\Controllers\ContactController;
use App\Controllers\VehicleController;

// 4. Chargement de la configuration sécurisée (.env)
// On utilise la librairie vlucas/phpdotenv pour charger les variables d'environnement
// (mots de passe BDD, clés API) depuis le fichier .env qui est ignoré par Git.
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

/**
 * Fonction helper pour l'affichage des Vues (View)
 * Cette fonction factorise le code d'affichage HTML.
 * * @param string $viewName Le nom du fichier dans /views (ex: 'home')
 * @param array $data Les données à transmettre à la vue
 */
function renderView($viewName, $data = [])
{
    // Transforme le tableau associatif ['user' => $u] en variable $user.
    // Cela permet d'utiliser directement $user dans le fichier HTML.
    extract($data);

    // Inclusion du template d'en-tête (Menu, CSS...)
    require_once __DIR__ . '/header_template.php';

    // Inclusion du contenu spécifique de la page
    $viewPath = __DIR__ . '/views/' . $viewName . '.php';
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        // Gestion de l'erreur 404 si le fichier n'existe pas
        http_response_code(404);
        include __DIR__ . '/views/404.php';
    }

    // Inclusion du template de pied de page (Scripts JS, Copyright...)
    require_once __DIR__ . '/footer_template.php';
}

// 5. Initialisation de la Base de Données
// On récupère l'instance unique de PDO via le pattern Singleton (Database::getInstance()).
$pdo = Database::getInstance();

// 6. Analyse de l'URL (Routing)
// On récupère le chemin demandé par l'utilisateur (ex: /login ou /api/stats)
// On ignore les paramètres GET (?id=12) pour ne garder que le chemin.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ============================================================
// SYSTÈME DE ROUTAGE (SWITCH)
// ============================================================

switch ($path) {

    // --- PAGES PUBLIQUES ---

    case '/':
    case '/home':
        // Page d'accueil
        renderView('home');
        break;

    case '/covoiturage':
        // Page de recherche (utilise CovoiturageController)
        CovoiturageController::showCovoituragePage($pdo, $_GET);
        break;

    case '/contact':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // On appelle le NOUVEAU contrôleur
            ContactController::handleContactForm($_POST);
        } else {
            renderView('contact');
        }
        break;

    case '/legalNotice':
        // Mentions légales
        renderView('legalNotice');
        break;

    // --- AUTHENTIFICATION ---

    case '/login':
        // Connexion
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::login($pdo, $_POST);
        } else {
            renderView('login');
        }
        break;

    case '/register':
        // Inscription
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::register($pdo, $_POST);
        } else {
            renderView('register');
        }
        break;

    case '/logout':
        // Déconnexion
        AuthController::logout();
        break;

    // --- ESPACES UTILISATEURS (PROTÉGÉS) ---

    case '/profile':
        // Gardien de sécurité : Redirection si non connecté
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }
        // Si un ID est passé dans l'URL (/profile?id=2), on affiche ce profil
        // Sinon, on affiche le profil de l'utilisateur connecté
        $userIdToDisplay = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
        UserController::showProfilePage($pdo, $userIdToDisplay);
        break;

    case '/profile/edit':
        // Édition du profil (et des préférences MongoDB)
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }
        UserController::showEditProfilePage($pdo);
        break;

    case '/buy-credits':
        // Achat de crédits
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            PaymentController::processCreditPurchase($pdo, $_POST);
        } else {
            renderView('buy_credits');
        }
        break;

    // --- ACTIONS FORMULAIRES (POST) ---

    case '/api/updateFullProfile':
        // Mise à jour conjointe SQL (User) et NoSQL (Préférences)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            UserController::updateFullProfile($pdo, $_POST);
        }
        break;

    case '/api/updateVehicle':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            // On appelle le NOUVEAU contrôleur
            VehicleController::updateVehicle($pdo, $_SESSION['user_id'], $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/confirmReservation':
        // Confirmation d'un passager par un chauffeur
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['statut'])) {
            TrajetController::confirmerReservation($pdo, (int)$_POST['reservation_id'], $_POST['statut']);
        }
        break;

    case '/ajouterTrajet':
        // Proposition d'un nouveau trajet
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::ajouterTrajet($pdo, $_POST);
        }
        break;

    // --- ESPACES EMPLOYÉS & ADMIN (HAUTE SÉCURITÉ) ---

    case '/employees':
        // Gardien de sécurité strict : Vérifie le rôle 'employe'
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employe') {
            header('Location: /login'); // Rejet si pas les droits
            exit();
        }
        EmployeeController::showDashboard($pdo); // Appel du tableau de bord employé
        break;

    case '/admin':
        // Gardien de sécurité strict : Vérifie le rôle 'admin'
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /login');
            exit();
        }
        renderView('admin'); // Affichage du dashboard admin (les données sont chargées par API)
        break;

    // --- API (ENDPOINTS JSON POUR JAVASCRIPT) ---
    // Ces routes ne renvoient pas de HTML, mais du JSON pour les requêtes fetch()
    // Elles sont utilisées pour le dynamisme (AJAX).

    case '/api/reserverTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Lecture du corps JSON brut envoyé par le JS
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id']) && isset($_SESSION['user_id'])) {
                // Appel de la logique transactionnelle de réservation
                TrajetController::participerTrajet($pdo, (int)$data['trajet_id'], $_SESSION['user_id']);
            } else {
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Données manquantes ou session expirée.']);
            }
        }
        break;

    case '/api/stats':
        // API pour les graphiques Admin (Chart.js)
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Accès interdit']);
            exit;
        }
        AdminController::getStats($pdo);
        break;

    case '/api/validateTrajet':
        // Validation fin de trajet (Paiement chauffeur + Avis)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['reservation_id'])) {
                AvisController::validateTrajet($pdo, $data['reservation_id'], $data);
            }
        }
        break;

    case '/api/reportIncident':
        // Signalement d'incident
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['reservation_id'], $data['commentaire'])) {
                AvisController::reportIncident($pdo, $data['reservation_id'], $data['commentaire']);
            }
        }
        break;

    case '/api/validateAvis':
        // Modération employé : Validation
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            EmployeeController::validateAvis($pdo, $_POST);
        }
        break;

    case '/api/refuseAvis':
        // Modération employé : Refus
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            EmployeeController::refuseAvis($pdo, $_POST);
        }
        break;

    case '/api/markIncidentHandled':
        // Modération employé : Incident traité
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            EmployeeController::markIncidentHandled($pdo, $_POST);
        }
        break;

    case '/api/createEmployee':
        // Admin : Création de compte
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::createEmployee($pdo, $_POST);
        }
        break;

    case '/api/suspendAccount':
        // Admin : Suspension
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::suspendAccount($pdo, $_POST);
        }
        break;

    case '/api/reactivateAccount':
        // Admin : Réactivation
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthController::reactivateAccount($pdo, $_POST);
        }
        break;

    case '/api/startTrajet':
        // Chauffeur : Démarrage du trajet
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::startTrajet($pdo, $data['trajet_id']);
            }
        }
        break;

    case '/api/endTrajet':
        // Chauffeur : Fin du trajet (déclenche notifications)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::endTrajet($pdo, $data['trajet_id']);
            }
        }
        break;

    case '/api/cancelReservation':
        // Passager : Annulation
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['reservation_id'])) {
                TrajetController::cancelReservation($pdo, $data['reservation_id']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données manquantes : reservation_id est requis.']);
            }
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;

    case '/api/cancelTrajet':
        // Chauffeur : Annulation
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::cancelTrajet($pdo, $data['trajet_id']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données manquantes : trajet_id est requis.']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;

    case '/api/keep_alive':
        // Route spéciale pour le Cron Job
        // Permet de réveiller MongoDB pour éviter les 'Cold Starts' sur Railway
        UserController::ping();
        break;

    // --- GESTION DES ERREURS (ROUTE PAR DÉFAUT) ---

    default:
        // Si l'URL ne correspond à aucun cas, on affiche une erreur 404.
        http_response_code(404);
        renderView('404');
        break;
}
