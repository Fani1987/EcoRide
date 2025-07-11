<?php

namespace App\Controllers;

use PDO; // Importe la classe PDO du namespace global
use PDOException; // Importe la classe PDOException du namespace global

class AuthController
{
    /**
     * Gère la connexion des utilisateurs.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $postData Les données POST du formulaire de connexion.
     */
    public static function login($pdo, $postData)
    {
        // Utilisation de trim() pour supprimer les espaces avant et après l'email et le mot de passe
        $email = trim($postData['email'] ?? '');
        $password = $postData['mot_de_passe'] ?? ''; // Le nom du champ dans login.html est 'mot_de_passe'

        // Vérification des champs vides
        if (empty($email) || empty($password)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs.'];
            header("Location: /login");
            exit;
        }

        try {
            // Récupération de l'utilisateur par email
            // Utilisation de LIMIT 1 car on s'attend à un seul résultat
            $stmt = $pdo->prepare("SELECT id, mot_de_passe, role, actif FROM utilisateurs WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Vérification de l'existence de l'utilisateur, s'il est actif et du mot de passe
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
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Identifiants incorrects ou compte inactif.'];
                header("Location: /login");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erreur login : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la connexion.'];
            header("Location: /login");
            exit;
        }
    }

    /**
     * Gère l'inscription des nouveaux utilisateurs.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $postData Les données POST du formulaire d'inscription.
     */
    public static function register($pdo, $postData)
    {
        // Nettoyage et validation des données d'entrée
        $pseudo = trim($postData['pseudo'] ?? '');
        $email = trim($postData['email'] ?? '');
        $mdp = $postData['mot_de_passe'] ?? '';
        $type = $postData['type'] ?? '';
        $prefs = $postData['prefs'] ?? []; // Assurez-vous que c'est un tableau, même si vide
        $description = trim($postData['description'] ?? '');

        $isChauffeur = ($type === 'Chauffeur' || $type === 'Passager/Chauffeur');
        $isPassager = ($type === 'Passager' || $type === 'Passager/Chauffeur');

        // Validation côté serveur des champs requis
        if (empty($pseudo) || empty($email) || empty($mdp) || empty($type)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez remplir tous les champs obligatoires (pseudo, email, mot de passe, type de compte).'];
            header("Location: /register");
            exit;
        }

        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'L\'adresse email n\'est pas valide.'];
            header("Location: /register");
            exit;
        }

        // Validation de la force du mot de passe
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{12,}$/', $mdp)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le mot de passe doit contenir au moins 12 caractères, incluant des lettres, des chiffres et des symboles.'];
            header("Location: /register");
            exit;
        }

        // Hachage du mot de passe
        $hash = password_hash($mdp, PASSWORD_DEFAULT);

        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Cette adresse email est déjà utilisée.'];
                header("Location: /register");
                exit;
            }

            // Début de la transaction pour assurer l'atomicité
            $pdo->beginTransaction();

            // Définir les crédits par défaut pour un nouvel utilisateur
            $defaultCredits = 20; // Définit les crédits initiaux à 20

            // Insertion dans la table utilisateurs
            // Le rôle par défaut est 'utilisateur'
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credit, description) VALUES (?, ?, ?, 'utilisateur', ?, ?)");
            $stmt->execute([$pseudo, $email, $hash, $description, $defaultCredits]);
            $user_id = $pdo->lastInsertId();

            // Insertion dans la table profils_utilisateur
            $stmt = $pdo->prepare("INSERT INTO profils_utilisateur (utilisateur_id, est_chauffeur, est_passager) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $isChauffeur ? 1 : 0, $isPassager ? 1 : 0]);

            // Enregistrer le véhicule si c'est un chauffeur
            if ($isChauffeur) {
                $marque = trim($postData['marque'] ?? '');
                $modele = trim($postData['modele'] ?? '');
                $couleur = trim($postData['couleur'] ?? '');
                $plaque = trim($postData['plaque_immatriculation'] ?? '');
                $energie = trim($postData['energie'] ?? ''); // Assurez-vous que ce champ est dans votre formulaire
                $immat = trim($postData['date_premiere_immat'] ?? ''); // Correction de la faute de frappe et trim

                // Valider les champs du véhicule
                if (empty($marque) || empty($modele) || empty($couleur) || empty($plaque) || empty($energie) || empty($immat)) {
                    // Annuler la transaction si des champs du véhicule sont manquants
                    $pdo->rollBack();
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez renseigner toutes les informations de votre véhicule.'];
                    header("Location: /register");
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, plaque_immatriculation, energie, date_premiere_immat) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $marque, $modele, $couleur, $plaque, $energie, $immat]);
            }

            // Enregistrer les préférences de l'utilisateur
            if (!empty($prefs)) {
                foreach ($prefs as $pref_id) {
                    // Exemple : insertion dans une table de liaison pour les préférences
                    // $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_id) VALUES (?, ?)");
                    // $stmt->execute([$user_id, $pref_id]);
                }
            }

            // Enregistrer les préférences dans MongoDB
            $prefs = $postData['prefs'] ?? [];
            if (!empty($prefs)) {
                $userController = new UserController(); // On instancie le contrôleur
                $userController->saveUserPreferences($user_id, $prefs);
            }

            // Si tout s'est bien passé
            $pdo->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Inscription réussie ! Vous pouvez maintenant vous connecter.'];
            header("Location: /login");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack(); // Annule la transaction en cas d'erreur
            error_log("Erreur d'inscription dans AuthController::register: " . $e->getMessage());
            // Message générique pour l'utilisateur, ne pas exposer les détails de l'erreur BDD
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.'];
            header("Location: /register");
            exit;
        }
    }

    public static function createEmployee(PDO $pdo, array $postData)
    {
        // Gère la création d'un nouvel employé par un administrateur.
        $nom = trim($postData['nom'] ?? '');
        $email = trim($postData['email'] ?? '');
        $motDePasse = $postData['mot_de_passe'] ?? '';

        if (empty($nom) || empty($email) || empty($motDePasse)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tous les champs sont requis.'];
            header("Location: /admin");
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Adresse email invalide.'];
            header("Location: /admin");
            exit;
        }

        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, actif) VALUES (?, ?, ?, 'employe', 1)");
            $stmt->execute([$nom, $email, $hash]);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Employé créé avec succès.'];
        } catch (PDOException $e) {
            error_log("Erreur création employé : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la création de l\'employé.'];
        }

        header("Location: /admin");
        exit;
    }

    public static function suspendAccount(PDO $pdo, array $postData)
    {
        // Gère la suspension d'un compte utilisateur ou employé par un administrateur.
        // Vérifie que l'email et le type de compte sont fournis
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Email ou type de compte invalide.'];
            header("Location: /admin");
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = 0 WHERE email = ? AND role = ?");
            $stmt->execute([$email, $type]);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte suspendu avec succès.'];
        } catch (PDOException $e) {
            error_log("Erreur suspension compte : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la suspension du compte.'];
        }

        header("Location: /admin");
        exit;
    }

    public static function reactivateAccount(PDO $pdo, array $postData)
    {
        $email = trim($postData['email'] ?? '');
        $type = trim($postData['type'] ?? '');

        if (empty($email) || !in_array($type, ['utilisateur', 'employe'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Email ou type de compte invalide.'];
            header("Location: /admin");
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = 1 WHERE email = ? AND role = ?");
            $stmt->execute([$email, $type]);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Compte réactivé avec succès.'];
        } catch (PDOException $e) {
            error_log("Erreur réactivation compte : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la réactivation du compte.'];
        }

        header("Location: /admin");
        exit;
    }

    public static function logout()
    {
        // Détruit toutes les données de session
        session_destroy();
        // Désactive les variables de session
        session_unset();

        // Efface le cookie de session si présent
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
        header("Location: /"); // Redirige vers la page d'accueil
        exit;
    }
}
