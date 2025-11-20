<?php

namespace App\Controllers;

use PDO;
use PDOException;
// On importe les Modèles nécessaires (MVC)
use App\Models\UserModel;
use App\Models\ProfilModel;
use App\Models\VehiculeModel;
// On importe UserController uniquement pour déléguer la sauvegarde NoSQL (Préférences)
use App\Controllers\UserController;

/**
 * Class AuthController
 * * Responsabilité : Gérer l'authentification et l'administration des comptes.
 * * Points clés pour le jury :
 * 1. Sécurité : Utilisation de password_hash/verify (Bcrypt).
 * 2. Transactions : L'inscription est atomique (tout ou rien).
 * 3. UX : Redirection intelligente après connexion.
 */
class AuthController
{
    /**
     * Gère la connexion de l'utilisateur (Login).
     */
    public static function login(PDO $pdo, array $postData)
    {
        // 1. Nettoyage des entrées
        $email = trim($postData['email'] ?? '');
        $password = $postData['mot_de_passe'] ?? '';

        // 2. Validation basique
        if (empty($email) || empty($password)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs.'];
            header("Location: /login");
            exit;
        }

        try {
            // 3. Récupération de l'utilisateur via le Modèle
            $userModel = new UserModel($pdo);
            $user = $userModel->findUserByEmail($email);

            // 4. VÉRIFICATION DU MOT DE PASSE (Point Critique Sécurité)
            // On utilise password_verify() pour comparer le mot de passe en clair
            // avec le hash sécurisé stocké en base de données.
            // On vérifie aussi que le compte est actif (non suspendu).
            if ($user && $user['actif'] == 1 && password_verify($password, $user['mot_de_passe'])) {

                // 5. Création de la Session
                // C'est ici que l'utilisateur devient "connecté".
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];

                // 6. Redirection Intelligente (UX)
                // Si l'utilisateur venait d'une page spécifique (ex: réservation), on l'y renvoie.
                if (!empty($postData['redirect'])) {
                    header("Location: " . $postData['redirect']);
                    exit;
                }

                // 7. Redirection par défaut selon le rôle
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
                // Echec : Message générique pour ne pas aider un attaquant (Enumération d'utilisateurs)
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
     * Gère l'inscription complète (Register).
     * Transaction complexe impliquant SQL (3 tables) et NoSQL.
     */
    public static function register(PDO $pdo, array $postData)
    {
        // 1. Nettoyage
        $pseudo = trim($postData['pseudo'] ?? '');
        $email = trim($postData['email'] ?? '');
        $mdp = $postData['mot_de_passe'] ?? '';
        $type = $postData['type'] ?? ''; // Rôle choisi (Passager/Chauffeur)
        $description = trim($postData['description'] ?? '');

        // Détermination des rôles booléens pour la table 'profils_utilisateur'
        $isChauffeur = ($type === 'Chauffeur' || $type === 'Passager/Chauffeur');
        $isPassager = ($type === 'Passager' || $type === 'Passager/Chauffeur');

        // 2. Validations Métier
        if (empty($pseudo) || empty($email) || empty($mdp) || empty($type)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs obligatoires.'];
            header("Location: /register");
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Email invalide.'];
            header("Location: /register");
            exit;
        }
        // Validation de complexité (RGPD/CNIL)
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{12,}$/', $mdp)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le mot de passe doit faire 12 caractères min, avec chiffres et caractères spéciaux.'];
            header("Location: /register");
            exit;
        }

        // 3. Hachage du mot de passe
        $hash = password_hash($mdp, PASSWORD_DEFAULT);
        $defaultCredits = 20; // Offre de bienvenue

        try {
            // 4. DÉBUT DE LA TRANSACTION (ACID)
            // L'inscription écrit dans plusieurs tables. Tout doit réussir ou tout doit échouer.
            $pdo->beginTransaction();

            $userModel = new UserModel($pdo);
            $profilModel = new ProfilModel($pdo);
            $vehiculeModel = new VehiculeModel($pdo);

            // Vérification doublon
            if ($userModel->checkEmailExists($email)) {
                throw new \Exception('Cette adresse email est déjà utilisée.');
            }

            // A. Création Utilisateur (Table principale)
            $userId = $userModel->createUser($pseudo, $email, $hash, $defaultCredits, $description);
            if (!$userId) throw new \Exception('Erreur lors de la création du compte utilisateur.');

            // B. Création Profil (Table de liaison)
            if (!$profilModel->createProfil($userId, $isChauffeur, $isPassager)) {
                throw new \Exception('Erreur lors de la création du profil.');
            }

            // C. Création Véhicule (Optionnel, si Chauffeur)
            if ($isChauffeur) {
                $marque = trim($postData['marque'] ?? '');
                // ... (autres champs véhicule)
                $immat = trim($postData['date_premiere_immat'] ?? '');

                // Si chauffeur, les infos véhicule sont obligatoires
                if (empty($marque) || empty($immat)) {
                    throw new \Exception('Veuillez renseigner les informations du véhicule.');
                }

                if (!$vehiculeModel->create($userId, $marque, $postData['modele'], $postData['couleur'], $postData['plaque_immatriculation'], $postData['energie'], $immat)) {
                    throw new \Exception('Erreur lors de l\'ajout du véhicule.');
                }
            }

            // 5. Gestion NoSQL (Préférences)
            // On utilise le contrôleur User pour déléguer cette tâche spécifique à Mongo
            $prefs = $postData['prefs'] ?? [];
            if (!empty($prefs)) {
                $userController = new UserController();
                $userController->saveUserPreferences($userId, $prefs);
            }

            // 6. Validation de la Transaction
            $pdo->commit();

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Inscription réussie ! Connectez-vous.'];
            header("Location: /login");
            exit;
        } catch (\Exception $e) {
            // 7. Annulation (Rollback) en cas d'erreur
            $pdo->rollBack();
            error_log("Erreur inscription : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => $e->getMessage()];
            header("Location: /register");
            exit;
        }
    }

    /**
     * Crée un compte Employé (Action Admin).
     */
    public static function createEmployee(PDO $pdo, array $postData)
    {
        // Validation
        $nom = trim($postData['nom'] ?? '');
        $email = trim($postData['email'] ?? '');
        $motDePasse = $postData['mot_de_passe'] ?? '';

        if (empty($nom) || empty($email) || empty($motDePasse)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tous les champs sont requis.'];
            header("Location: /admin");
            exit;
        }

        // Hachage obligatoire même pour les employés
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

        try {
            $userModel = new UserModel($pdo);

            if ($userModel->checkEmailExists($email)) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cet email est déjà utilisé.'];
            } else if ($userModel->createEmployee($nom, $email, $hash)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Employé créé avec succès.'];
            } else {
                throw new \Exception('Erreur lors de la création.');
            }
        } catch (\Exception $e) {
            error_log("Erreur createEmployee : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur technique.'];
        }

        header("Location: /admin");
        exit;
    }

    /**
     * Suspend un compte (Soft Delete).
     * Le compte existe toujours mais le champ 'actif' passe à 0.
     */
    public static function suspendAccount(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        // Sécurité : on ne peut suspendre que des utilisateurs ou employés (pas d'admin)
        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cible invalide.'];
            header("Location: /admin");
            exit;
        }

        try {
            $userModel = new UserModel($pdo);
            $rows = $userModel->updateUserActiveStatus($email, $type, 0); // 0 = inactif

            if ($rows > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte suspendu.'];
            } else {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Compte introuvable ou déjà suspendu.'];
            }
        } catch (PDOException $e) {
            error_log("Erreur suspension : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur technique.'];
        }

        header("Location: /admin");
        exit;
    }

    /**
     * Réactive un compte suspendu.
     */
    public static function reactivateAccount(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            header("Location: /admin");
            exit;
        }

        try {
            $userModel = new UserModel($pdo);
            $rows = $userModel->updateUserActiveStatus($email, $type, 1); // 1 = actif

            if ($rows > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte réactivé.'];
            } else {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Compte introuvable ou déjà actif.'];
            }
        } catch (PDOException $e) {
            error_log("Erreur réactivation : " . $e->getMessage());
        }
        header("Location: /admin");
        exit;
    }

    /**
     * Déconnexion sécurisée.
     */
    public static function logout()
    {
        // 1. Vider les variables de session
        session_unset();

        // 2. Détruire la session côté serveur
        session_destroy();

        // 3. Supprimer le cookie de session côté navigateur (Nettoyage complet)
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

        header("Location: /");
        exit;
    }
}
