<?php

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
            $stmt = $pdo->prepare("SELECT id, mot_de_passe, role FROM utilisateurs WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Vérification de l'existence de l'utilisateur et du mot de passe
            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Régénération de l'ID de session pour prévenir les attaques de fixation de session
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role']; // Stocker le rôle de l'utilisateur

                $_SESSION['message'] = ['type' => 'success', 'text' => 'Connexion réussie !'];

                // Redirection basée sur le rôle de l'utilisateur
                if ($user['role'] === 'admin') {
                    header("Location: /admin");
                } elseif ($user['role'] === 'employee') {
                    header("Location: /employees");
                } else {
                    header("Location: /profile"); // Redirige vers la page de profil par défaut pour les utilisateurs
                }
                exit;
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Identifiants incorrects.'];
                header("Location: /login");
                exit;
            }
        } catch (PDOException $e) {
            // Enregistrement de l'erreur pour le débogage (ne pas afficher à l'utilisateur final)
            error_log("Erreur de connexion dans AuthController::login: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de la connexion. Veuillez réessayer.'];
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

        // Validation de la force du mot de passe (doit correspondre au pattern HTML si possible)
        // Ce pattern est un bon début, mais une vérification plus robuste est recommandée en production.
        // Exemple : au moins 12 caractères, mélange de lettres, chiffres et symboles.
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

            // Insertion dans la table utilisateurs
            // Le rôle par défaut est 'utilisateur'
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, description) VALUES (?, ?, ?, 'utilisateur', ?)");
            $stmt->execute([$pseudo, $email, $hash, $description]);
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

            // Enregistrer les préférences de l'utilisateur (si la table existe)
            // Assurez-vous que 'preferences' est une table séparée ou un champ JSON dans 'utilisateurs'
            // Cet exemple suppose une table 'preferences_utilisateur' avec user_id et preference_id ou des colonnes booléennes
            if (!empty($prefs)) {
                foreach ($prefs as $pref_id) {
                    // Exemple : insertion dans une table de liaison pour les préférences
                    // $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_id) VALUES (?, ?)");
                    // $stmt->execute([$user_id, $pref_id]);
                }
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

    /**
     * Gère la déconnexion des utilisateurs.
     */
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
