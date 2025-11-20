<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;
use PDOException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Import de TOUS les modèles nécessaires
use App\Models\UserModel;
use App\Models\AvisModel;
use App\Models\VehiculeModel;
use App\Models\TrajetModel;
use App\Models\ReservationModel;
use App\Models\NotificationModel;

class UserController
{
    // -----------------------------------------------------------------
    // LOGIQUE MONGODB (NON-SQL) - ON LA LAISSE TELLE QUELLE
    // -----------------------------------------------------------------

    public function getUserPreferences(int $mysqlUserId)
    {
        $client = Database::getMongoClient();
        $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
        $preferencesCollection = $database->selectCollection('preferences');

        try {
            $userPreferences = $preferencesCollection->findOne(['mysql_user_id' => $mysqlUserId]);
            return $userPreferences ? (array)$userPreferences->preferences : [];
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            error_log("Erreur MongoDB (get) : " . $e->getMessage());
            return [];
        }
    }

    public function saveUserPreferences(int $mysqlUserId, array $preferences)
    {
        $client = Database::getMongoClient();
        $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
        $preferencesCollection = $database->selectCollection('preferences');

        try {
            $updateResult = $preferencesCollection->updateOne(
                ['mysql_user_id' => $mysqlUserId],
                ['$set' => ['preferences' => $preferences]],
                ['upsert' => true]
            );
            return $updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0;
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            error_log("Erreur MongoDB (save) : " . $e->getMessage());
            return false;
        }
    }

    // -----------------------------------------------------------------
    // LOGIQUE SQL REFACTORISÉE
    // -----------------------------------------------------------------

    /**
     * Affiche la page de profil complète.
     * C'est le "chef d'orchestre" qui appelle tous les modèles.
     */
    public static function showProfilePage(PDO $pdo, int $userId)
    {
        try {
            // 1. Instancier tous les modèles
            $userModel = new UserModel($pdo);
            $avisModel = new AvisModel($pdo);
            $vehiculeModel = new VehiculeModel($pdo);
            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $notificationModel = new NotificationModel($pdo);
            $userController = new UserController(); // Pour la logique Mongo

            // 2. Récupérer les données via les modèles
            $user = $userModel->findProfilById($userId);
            if (!$user) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Utilisateur non trouvé.'];
                header('Location: /');
                exit();
            }

            $preferences = $userController->getUserPreferences($userId);
            $vehicules = $vehiculeModel->findByUserId($userId);
            $trajetsProposes = $trajetModel->findByChauffeurId($userId);
            $trajetsReserves = $reservationModel->getTrajetsReservesByUserId($userId);
            $avis = [];
            $reservationsEnAttente = [];

            if ($user['est_chauffeur']) {
                $avis = $avisModel->getChauffeurAvisValides($userId); // On ne montre que les avis validés
                $reservationsEnAttente = $reservationModel->getPendingReservationsForChauffeur($userId);

                // Logique pour attacher les passagers aux trajets proposés
                if (!empty($trajetsProposes)) {
                    foreach ($trajetsProposes as $key => $trajet) {
                        $trajetsProposes[$key]['passagers'] = $reservationModel->getPassagersByTrajet($trajet['id']);
                    }
                }
            }

            // 3. Gérer la logique métier (type d'utilisateur)
            $type_utilisateur = '';
            if ($user['est_chauffeur'] && $user['est_passager']) {
                $type_utilisateur = 'Chauffeur / Passager';
            } elseif ($user['est_chauffeur']) {
                $type_utilisateur = 'Chauffeur';
            } elseif ($user['est_passager']) {
                $type_utilisateur = 'Passager';
            }

            // 4. Gérer les notifications
            $isOwner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId);
            $notificationsNonLues = [];
            if ($isOwner) {
                $notificationsNonLues = $notificationModel->getUnread($userId);
                if (!empty($notificationsNonLues)) {
                    $notificationIds = array_column($notificationsNonLues, 'id');
                    $notificationModel->markAsRead($notificationIds);
                }
            }

            // 5. Préparer les données pour la vue
            $data = [
                'user' => $user,
                'isOwner' => $isOwner,
                'preferences' => $preferences,
                'vehicules' => $vehicules,
                'trajetsProposes' => $trajetsProposes,
                'trajetsReserves' => $trajetsReserves,
                'type_utilisateur' => $type_utilisateur,
                'avis' => $avis,
                'reservationsEnAttente' => $reservationsEnAttente,
                'notificationsNonLues' => $notificationsNonLues,
            ];

            // 6. Appeler la vue
            \renderView('profile', $data);
        } catch (PDOException $e) {
            error_log("Erreur showProfilePage : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du chargement du profil.'];
            header('Location: /');
            exit();
        }
    }

    /**
     * Affiche la page d'édition de profil.
     */
    public static function showEditProfilePage(PDO $pdo)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        $userId = $_SESSION['user_id'];

        try {
            $userModel = new UserModel($pdo);
            $userController = new UserController(); // Pour Mongo

            $user = $userModel->findById($userId); // Utilise la méthode simple
            $preferences = $userController->getUserPreferences($userId);

            $data = [
                'user' => $user,
                'preferences' => $preferences
            ];
            \renderView('edit_profile', $data);
        } catch (PDOException $e) {
            error_log("Erreur showEditProfilePage : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du chargement.'];
            header('Location: /profile');
            exit();
        }
    }

    /**
     * Gère la mise à jour du profil (SQL + Mongo).
     */
    public static function updateFullProfile(PDO $pdo, array $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        $userId = $_SESSION['user_id'];

        try {
            // --- LOGIQUE DE MISE À JOUR DU PROFIL (MySQL) ---
            $pdo->beginTransaction();

            $userModel = new UserModel($pdo);

            $pseudo = trim($postData['pseudo'] ?? '');
            $email = trim($postData['email'] ?? '');
            $description = trim($postData['description'] ?? '');

            $userModel->updateProfile($userId, $pseudo, $email, $description);

            $pdo->commit();

            // --- LOGIQUE DE MISE À JOUR DES PRÉFÉRENCES (MongoDB) ---
            $finalPreferences = $postData['prefs'] ?? [];
            if (!empty($postData['custom_prefs'])) {
                $customPrefs = array_map('trim', explode(',', $postData['custom_prefs']));
                $finalPreferences = array_merge($finalPreferences, array_filter($customPrefs));
            }
            $finalPreferences = array_unique($finalPreferences);

            $userController = new UserController();
            $userController->saveUserPreferences($userId, array_values($finalPreferences));

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil mis à jour avec succès.'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur updateFullProfile (SQL) : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur SQL est survenue.'];
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            // La transaction SQL a réussi, mais Mongo a échoué
            error_log("Erreur updateFullProfile (Mongo) : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'warning', 'text' => 'Profil mis à jour, mais les préférences n\'ont pas pu être sauvegardées.'];
        }

        header('Location: /profile/edit');
        exit();
    }

    /**
     * Gère l'ajout ou la mise à jour d'un véhicule (API).
     */
    public static function updateVehicle(PDO $pdo, int $userId, array $postData)
    {
        header('Content-Type: application/json');

        // 1. Validation des données
        $vehiculeId = $postData['id'] ?? null;
        $marque = trim($postData['marque'] ?? '');
        $modele = trim($postData['modele'] ?? '');
        $plaque = trim($postData['plaque_immatriculation'] ?? '');
        // ... (récupérer les autres champs) ...
        $couleur = trim($postData['couleur'] ?? '');
        $energie = trim($postData['energie'] ?? '');
        $date_immat = trim($postData['date_premiere_immat'] ?? '');

        if (empty($marque) || empty($modele) || empty($plaque)) {
            echo json_encode(['success' => false, 'message' => 'Marque, modèle et plaque sont requis.']);
            exit();
        }

        try {
            // 2. Instancier le modèle
            $vehiculeModel = new VehiculeModel($pdo);
            $success = false;

            // 3. Appeler la bonne méthode du modèle
            if ($vehiculeId) {
                // Mise à jour
                $success = $vehiculeModel->update(
                    (int)$vehiculeId,
                    $userId,
                    $marque,
                    $modele,
                    $couleur,
                    $plaque,
                    $energie,
                    $date_immat
                );
                $message = 'Véhicule mis à jour.';
            } else {
                // Création
                $success = $vehiculeModel->create(
                    $userId,
                    $marque,
                    $modele,
                    $couleur,
                    $plaque,
                    $energie,
                    $date_immat
                );
                $message = 'Véhicule ajouté.';
            }

            if (!$success) {
                throw new \Exception('L\'opération sur le véhicule a échoué.');
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            error_log("Erreur updateVehicle : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'opération.']);
        }
        exit();
    }

    // -----------------------------------------------------------------
    // LOGIQUE SANS SQL (Envoi d'email) - ON LA LAISSE TELLE QUELLE
    // -----------------------------------------------------------------

    /**
     * Gère l'envoi du formulaire de contact.
     * Cette fonction ne touche pas à la BDD SQL, elle est correcte.
     */
    public static function handleContactForm(array $postData)
    {
        $pseudo = trim($postData['pseudo'] ?? '');
        $emailExpediteur = trim($postData['email'] ?? '');
        $sujet = trim($postData['sujet'] ?? '');
        $message = trim($postData['message'] ?? '');

        if (empty($pseudo) || empty($emailExpediteur) || !filter_var($emailExpediteur, FILTER_VALIDATE_EMAIL) || empty($sujet) || empty($message)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tous les champs sont requis et l\'email doit être valide.'];
            header('Location: /contact');
            exit;
        }

        $mail = new PHPMailer(true);

        try {
            // Configuration du serveur SMTP
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['MAIL_PORT'];
            $mail->CharSet    = 'UTF-8';

            // Destinataires
            $mail->setFrom($_ENV['MAIL_USERNAME'], 'Formulaire de Contact EcoRide');
            $mail->addAddress('contact@ecoride.fr', 'Support EcoRide'); // Email de destination
            $mail->addReplyTo($emailExpediteur, $pseudo);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = 'Nouveau message de contact : ' . htmlspecialchars($sujet);
            $mail->Body    = "Message de <b>" . htmlspecialchars($pseudo) . "</b> (" . htmlspecialchars($emailExpediteur) . ").<br><br><hr><br>" . nl2br(htmlspecialchars($message));
            $mail->AltBody = "Message de " . htmlspecialchars($pseudo) . " (" . htmlspecialchars($emailExpediteur) . ").\n\n" . htmlspecialchars($message);

            // $mail->send(); // Production
            error_log("SIMULATION: Email de contact envoyé de " . $emailExpediteur); // Dev

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Votre message a bien été envoyé.'];
        } catch (Exception $e) {
            error_log("Erreur PHPMailer (contact) : {$mail->ErrorInfo}");
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le message n\'a pas pu être envoyé.'];
        }

        header('Location: /contact');
        exit;
    }

    /**
     * Fonction simple pour réveiller MongoDB (utilisée par le Cron Job)
     */
    public static function ping()
    {
        header('Content-Type: application/json');
        try {
            // On utilise votre classe Database existante pour récupérer la connexion
            $client = Database::getMongoClient();

            // On fait une opération légère (lister les bases) pour forcer le réveil
            $client->listDatabases();

            echo json_encode(['status' => 'success', 'message' => 'MongoDB est réveillé !']);
        } catch (\Exception $e) {
            // Même si ça échoue, le fait d'avoir essayé a probablement réveillé le service
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }
}
