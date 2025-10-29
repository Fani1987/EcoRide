<?php

namespace App\Controllers;

use PDO;
use PDOException;
// On importe TOUS les modèles dont on a besoin
use App\Models\UserModel;
use App\Models\ProfilModel;
use App\Models\VehiculeModel;
use App\Controllers\UserController; // Pour la logique MongoDB des préférences

class AuthController
{
    /**
     * Gère la connexion (déjà refactorisé, mais inclus pour la complétude).
     */
    public static function login(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $password = $postData['mot_de_passe'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs.'];
            header("Location: /login");
            exit;
        }

        try {
            // 1. Appel au Modèle
            $userModel = new UserModel($pdo);
            $user = $userModel->findUserByEmail($email);

            // 2. Logique métier (Contrôleur)
            if ($user && $user['actif'] == 1 && password_verify($password, $user['mot_de_passe'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];

                switch ($user['role']) {
                    case 'admin':
                        header("Location: /admin");
                        break;
                    case 'employe':
                        header("Location: /employees");
                        break;
                    default:
                        header("Location: /profile");
                        break;
                }
                exit;
            } else {
                throw new \Exception('Identifiants incorrects ou compte inactif.');
            }
        } catch (\Exception $e) {
            error_log("Erreur login : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => $e->getMessage()];
            header("Location: /login");
            exit;
        }
    }

    /**
     * Gère l'inscription (transaction complexe).
     */
    public static function register(PDO $pdo, array $postData)
    {
        // 1. Nettoyage et validation des données
        $pseudo = trim($postData['pseudo'] ?? '');
        $email = trim($postData['email'] ?? '');
        $mdp = $postData['mot_de_passe'] ?? '';
        $type = $postData['type'] ?? '';
        $description = trim($postData['description'] ?? '');

        $isChauffeur = ($type === 'Chauffeur' || $type === 'Passager/Chauffeur');
        $isPassager = ($type === 'Passager' || $type === 'Passager/Chauffeur');

        // ... (Validations initiales) ...
        if (empty($pseudo) || empty($email) || empty($mdp) || empty($type)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs obligatoires (pseudo, email, mot de passe, type de compte).'];
            header("Location: /register");
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // ... (message erreur email) ...
            header("Location: /register");
            exit;
        }
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{12,}$/', $mdp)) {
            // ... (message erreur mdp) ...
            header("Location: /register");
            exit;
        }

        $hash = password_hash($mdp, PASSWORD_DEFAULT);
        $defaultCredits = 20;

        try {
            // 2. Démarrer la transaction
            $pdo->beginTransaction();

            // 3. Instancier les modèles
            $userModel = new UserModel($pdo);
            $profilModel = new ProfilModel($pdo);
            $vehiculeModel = new VehiculeModel($pdo);

            // 4. Logique métier : Vérifier si l'email existe
            if ($userModel->checkEmailExists($email)) {
                throw new \Exception('Cette adresse email est déjà utilisée.');
            }

            // 5. Exécution via les modèles
            // Créer l'utilisateur
            $userId = $userModel->createUser($pseudo, $email, $hash, $defaultCredits, $description);
            if (!$userId) {
                throw new \Exception('Erreur lors de la création du compte utilisateur.');
            }

            // Créer le profil
            if (!$profilModel->createProfil($userId, $isChauffeur, $isPassager)) {
                throw new \Exception('Erreur lors de la création du profil.');
            }

            // Si c'est un chauffeur, créer le véhicule
            if ($isChauffeur) {
                $marque = trim($postData['marque'] ?? '');
                $modele = trim($postData['modele'] ?? '');
                $couleur = trim($postData['couleur'] ?? '');
                $plaque = trim($postData['plaque_immatriculation'] ?? '');
                $energie = trim($postData['energie'] ?? '');
                $immat = trim($postData['date_premiere_immat'] ?? '');

                if (empty($marque) || empty($modele) || empty($plaque) || empty($energie) || empty($immat)) {
                    throw new \Exception('Veuillez renseigner toutes les informations de votre véhicule.');
                }

                if (!$vehiculeModel->create($userId, $marque, $modele, $couleur, $plaque, $energie, $immat)) {
                    throw new \Exception('Erreur lors de l\'ajout du véhicule.');
                }
            }

            // 6. Gérer MongoDB (logique gardée dans UserController)
            $prefs = $postData['prefs'] ?? [];
            if (!empty($prefs)) {
                $userController = new UserController();
                $userController->saveUserPreferences($userId, $prefs);
            }

            // 7. Valider la transaction
            $pdo->commit();

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Inscription réussie ! Vous pouvez maintenant vous connecter.'];
            header("Location: /login");
            exit;
        } catch (\Exception $e) { // Attrape PDOException et nos \Exception
            // 8. Gestion des erreurs
            $pdo->rollBack();
            error_log("Erreur d'inscription : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => $e->getMessage()];
            header("Location: /register");
            exit;
        }
    }

    /**
     * Crée un compte employé (admin).
     */
    public static function createEmployee(PDO $pdo, array $postData)
    {
        $nom = trim($postData['nom'] ?? '');
        $email = trim($postData['email'] ?? '');
        $motDePasse = $postData['mot_de_passe'] ?? '';

        // ... (Validations) ...
        if (empty($nom) || empty($email) || empty($motDePasse)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tous les champs sont requis.'];
            header("Location: /admin");
            exit;
        }

        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

        try {
            $userModel = new UserModel($pdo);

            if ($userModel->checkEmailExists($email)) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cet email est déjà utilisé.'];
            } else if ($userModel->createEmployee($nom, $email, $hash)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Employé créé avec succès.'];
            } else {
                throw new \Exception('Erreur inconnue lors de la création.');
            }
        } catch (\Exception $e) {
            error_log("Erreur création employé : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la création de l\'employé.'];
        }

        header("Location: /admin");
        exit;
    }

    /**
     * Suspend un compte (admin).
     */
    public static function suspendAccount(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Email ou type de compte invalide.'];
            header("Location: /admin");
            exit;
        }

        try {
            $userModel = new UserModel($pdo);
            $rowsAffected = $userModel->updateUserActiveStatus($email, $type, 0); // 0 = inactif

            if ($rowsAffected > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte suspendu avec succès.'];
            } else {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Compte non trouvé ou déjà suspendu.'];
            }
        } catch (PDOException $e) {
            error_log("Erreur suspension compte : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la suspension du compte.'];
        }

        header("Location: /admin");
        exit;
    }

    /**
     * Réactive un compte (admin).
     */
    public static function reactivateAccount(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            // ... (message erreur) ...
            header("Location: /admin");
            exit;
        }

        try {
            $userModel = new UserModel($pdo);
            $rowsAffected = $userModel->updateUserActiveStatus($email, $type, 1); // 1 = actif

            if ($rowsAffected > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte réactivé avec succès.'];
            } else {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Compte non trouvé ou déjà actif.'];
            }
        } catch (PDOException $e) {
            // ... (message erreur) ...
        }
        header("Location: /admin");
        exit;
    }

    /**
     * Gère la déconnexion. (Aucun SQL, aucune modification)
     */
    public static function logout()
    {
        session_unset();
        session_destroy();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Vous avez été déconnecté.'];
        header("Location: /");
        exit;
    }
}
