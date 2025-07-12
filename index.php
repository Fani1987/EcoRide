<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';

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

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

function renderView($viewName, $data = [])
{
    extract($data);
    require_once __DIR__ . '/header_template.php';
    $viewPath = __DIR__ . '/views/' . $viewName . '.php';
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        http_response_code(404);
        include __DIR__ . '/views/404.php';
    }
    require_once __DIR__ . '/footer_template.php';
}

$pdo = Database::getInstance();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ROUTAGE PRINCIPAL
switch ($path) {
    case '/':
    case '/home':
        renderView('home');
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

    case '/logout':
        AuthController::logout();
        break;

    // Version corrigée à mettre dans index.php

    case '/profile':
        $profileIdToShow = null;

        // On vérifie d'abord si un ID est passé dans l'URL
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $profileIdToShow = $_GET['id'];
        }
        // Sinon, si aucun ID n'est dans l'URL, on vérifie si l'utilisateur est connecté pour afficher son propre profil
        elseif (isset($_SESSION['user_id'])) {
            $profileIdToShow = $_SESSION['user_id'];
        }

        // Si on a un ID (soit de l'URL, soit de la session), on affiche le profil
        if ($profileIdToShow) {
            UserController::showProfilePage($pdo, $profileIdToShow);
        }
        // Sinon, si on n'a ni ID dans l'URL ni utilisateur connecté, on redirige vers la connexion
        else {
            header('Location: /login');
            exit();
        }
        break;

    case '/profile/edit':
        UserController::showEditProfilePage($pdo);
        break;

    case '/covoiturage':
        CovoiturageController::showCovoituragePage($pdo, $_GET);
        break;

    case '/covoiturage-detail':
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $trajetId = (int)$_GET['id'];
            TrajetController::showTrajetDetail($pdo, $trajetId);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de trajet manquant ou invalide.'];
            header('Location: /covoiturage');
            exit();
        }
        break;

    case '/legalNotice':
        renderView('legalNotice');
        break;

    case '/employees':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employe') {
            header('Location: /login');
            exit();
        }
        renderView('employees');
        break;

    case '/admin':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /login');
            exit();
        }
        renderView('admin');
        break;

    case '/reserver':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (isset($data['trajet_id'])) {
                TrajetController::participerTrajet($pdo, $data['trajet_id'], $_SESSION['user_id']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de trajet manquant dans la requête.']);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou utilisateur non connecté.']);
            exit;
        }
        break;

    case '/buy-credits':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            PaymentController::processCreditPurchase($pdo, $_POST);
        } else {
            \renderView('buy_credits');
        }
        break;

    // ROUTES API (requêtes AJAX)
    case '/api/ajouterTrajet':
        // Cette route est traitée comme une API : lecture JSON et pas de templates HTML
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Pour les requêtes AJAX POST qui envoient des FormData, $_POST fonctionne.
            // Si c'est du JSON, il faudrait file_get_contents('php://input')
            TrajetController::ajouterTrajet($pdo, $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
            http_response_code(405);
        }
        break;

    case '/api/reserverTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true); // Décode en tableau associatif

            if (isset($data['trajet_id'])) {
                TrajetController::participerTrajet($pdo, $data['trajet_id'], $_SESSION['user_id']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de trajet manquant dans la requête JSON.']);
                http_response_code(400);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/updateFullProfile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            UserController::updateFullProfile($pdo, $_POST);
        }
        break;

    case '/api/updateVehicle':
        // Cette route est traitée comme une API : pas de templates HTML
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            UserController::updateVehicle($pdo, $_SESSION['user_id'], $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/createEmployee':
        // Cette route est traitée comme une API : pas de templates HTML
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_role'] === 'admin') {
            AuthController::createEmployee($pdo, $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/suspendAccount':
        // Cette route est traitée comme une API : pas de templates HTML
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_role'] === 'admin') {
            AuthController::suspendAccount($pdo, $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/stats':
        // Cette route est traitée comme une API : pas de templates HTML
        if ($_SESSION['user_role'] === 'admin') {
            AdminController::getStats($pdo);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            http_response_code(403);
        }
        break;

    case '/api/validateAvis':
        // Sécurité : on vérifie que la méthode est POST et que l'utilisateur est bien un employé
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employe') {
            EmployeeController::validateAvis($pdo, $_POST);
            // On redirige l'employé vers sa page pour voir le résultat
            header('Location: /employees');
            exit();
        }
        break;

    case '/api/refuseAvis':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employe') {
            EmployeeController::refuseAvis($pdo, $_POST);
            header('Location: /employees');
            exit();
        }
        break;

    case '/api/markIncidentHandled':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employe') {
            EmployeeController::markIncidentHandled($pdo, $_POST);
            header('Location: /employees');
            exit();
        }
        break;

    case '/confirmer-reservation':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // On vérifie que les données du formulaire sont bien là
            if (isset($_POST['reservation_id'], $_POST['statut'])) {
                // On appelle la méthode du contrôleur
                TrajetController::confirmerReservation($pdo, $_POST['reservation_id'], $_POST['statut']);
                // On met un message de succès en session
                $_SESSION['message'] = ['type' => 'success', 'text' => 'La réservation a bien été traitée.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Données manquantes pour traiter la réservation.'];
            }
            // Dans tous les cas, on redirige vers le profil pour voir le résultat
            header('Location: /profile');
            exit();
        }
        break;

    case '/api/startTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::startTrajet($pdo, $data['trajet_id']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de trajet manquant.']);
            }
        }
        break;

    case '/api/endTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::endTrajet($pdo, $data['trajet_id']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de trajet manquant.']);
            }
        }
        break;

    case '/api/validateTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            // Vérification que les données requises sont bien présentes
            if (isset($data['reservation_id'], $data['note'])) {
                AvisController::validateTrajet($pdo, $data['reservation_id'], $data);
            } else {
                // Si des données sont manquantes, on envoie une erreur claire
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données manquantes : reservation_id et note sont requis.']);
            }
        } else {
            // Si la méthode n'est pas POST
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;

    case '/api/reportIncident':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            // Vérification que les données requises sont bien présentes
            if (isset($data['reservation_id'], $data['commentaire'])) {
                AvisController::reportIncident($pdo, $data['reservation_id'], $data['commentaire']);
            } else {
                // Si des données sont manquantes, on envoie une erreur claire
                header('Content-Type: application/json');
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Données manquantes : reservation_id et commentaire sont requis.']);
            }
        } else {
            // Si la méthode n'est pas POST
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;


    case '/api/cancelReservation':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['reservation_id'])) {
                TrajetController::cancelReservation($pdo, $data['reservation_id']);
            } else {
                // Gestion de l'erreur si l'ID est manquant
                header('Content-Type: application/json');
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'Données manquantes : reservation_id est requis.']);
            }
        } else {
            // Gestion de l'erreur si la méthode n'est pas POST
            header('Content-Type: application/json');
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;

    case '/api/cancelTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['trajet_id'])) {
                TrajetController::cancelTrajet($pdo, $data['trajet_id']);
            } else {
                // Gestion de l'erreur si l'ID est manquant
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données manquantes : trajet_id est requis.']);
            }
        } else {
            // Gestion de l'erreur si la méthode n'est pas POST
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        }
        break;

    default:
        http_response_code(404);
        renderView('404');
        break;
}
