<?php
// Démarrer la session PHP au tout début du script
session_start();

// Inclure l'autoloader de Composer en premier.
require_once __DIR__ . '/vendor/autoload.php';

// Optionnel: Charger les variables d'environnement avec Dotenv
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__); // Si .env est à la racine du projet
$dotenv->load();

// --- FONCTION D'AIDE POUR LE RENDU DES VUES ---
/**
 * Fonction globale pour rendre les fichiers de vue.
 * Les données passées seront disponibles comme des variables dans le fichier de vue.
 *
 * @param string $viewName Le nom du fichier de vue (sans l'extension .php, ex: 'home', 'profile').
 * @param array $data Un tableau associatif de données à rendre disponibles dans la vue.
 */
function renderView($viewName, $data = [])
{
    // Rend les clés du tableau $data accessibles comme des variables locales dans la vue.
    extract($data);

    // Construit le chemin absolu vers le fichier de vue.
    $viewPath = __DIR__ . '/views/' . $viewName . '.php';

    if (file_exists($viewPath)) {
        // Inclut le fichier de vue.
        include $viewPath;
    } else {
        // Gérer le cas où le fichier de vue n'existe pas (ex: afficher une erreur 404).
        http_response_code(404);
        include __DIR__ . '/views/404.php';
    }
}
// --- FIN DE LA FONCTION D'AIDE ---


// Utiliser les classes des contrôleurs avec leurs namespaces
use App\Controllers\AuthController;
use App\Controllers\CovoiturageController;
use App\Controllers\TrajetController;
use App\Controllers\UserController;
use App\Core\Database;

// Obtenir l'instance PDO via la classe Database Singleton
$pdo = Database::getInstance();

// Récupérer l'URL demandée et la nettoyer
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// INCLUSION DE L'ENTÊTE
require_once __DIR__ . '/header_template.php';

// Afficher les messages flash stockés en session, s'il y en a.
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message']['type']) . '">';
    echo htmlspecialchars($_SESSION['message']['text']);
    echo '</div>';
    unset($_SESSION['message']); // Supprimer le message après l'affichage
}

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

    case '/profile':
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        UserController::showProfilePage($pdo, $_SESSION['user_id']);
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
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employe') {
            header('Location: /login');
            exit();
        }
        renderView('employees');
        break;

    case '/admin':
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit();
        }
        renderView('admin');
        break;

    // ROUTES API (requêtes AJAX)
    case '/api/ajouterTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            TrajetController::ajouterTrajet($pdo, $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
            http_response_code(405);
        }
        break;

    case '/api/reserverTrajet':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trajet_id']) && isset($_SESSION['user_id'])) {
            TrajetController::participerTrajet($pdo, $_POST['trajet_id'], $_SESSION['user_id']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/updateProfile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            UserController::updateProfile($pdo, $_SESSION['user_id'], $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    case '/api/updateVehicle':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            UserController::updateVehicle($pdo, $_SESSION['user_id'], $_POST);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Requête invalide ou non autorisée.']);
            http_response_code(400);
        }
        break;

    default:
        http_response_code(404);
        renderView('404');
        break;
}

// INCLUSION DU PIED DE PAGE
require_once __DIR__ . '/footer_template.php';

?>