<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;
use PDOException;
// Modèles nécessaires pour l'agrégation de données du profil
use App\Models\UserModel;
use App\Models\AvisModel;
use App\Models\VehiculeModel;
use App\Models\TrajetModel;
use App\Models\ReservationModel;
use App\Models\NotificationModel;

/**
 * Class UserController
 * * Responsabilité : Gérer le profil de l'utilisateur et ses préférences.
 * C'est le contrôleur "Central" pour l'espace membre.
 */
class UserController
{
    // =================================================================
    // LOGIQUE MONGODB (PRÉFÉRENCES)
    // Les préférences sont gérées ici car elles sont intrinsèquement liées à l'utilisateur.
    // =================================================================

    public function getUserPreferences(int $mysqlUserId)
    {
        $client = Database::getMongoClient();
        $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
        $preferencesCollection = $database->selectCollection('preferences');

        try {
            // Recherche par ID externe (la clé étrangère vers MySQL)
            $userPreferences = $preferencesCollection->findOne(['mysql_user_id' => $mysqlUserId]);
            return $userPreferences ? (array)$userPreferences->preferences : [];
        } catch (\Exception $e) {
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
            // Utilisation de 'upsert' (Update or Insert)
            // Si le profil de préférences n'existe pas, il est créé.
            $updateResult = $preferencesCollection->updateOne(
                ['mysql_user_id' => $mysqlUserId],
                ['$set' => ['preferences' => $preferences]],
                ['upsert' => true]
            );
            return $updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0;
        } catch (\Exception $e) {
            error_log("Erreur MongoDB (save) : " . $e->getMessage());
            return false;
        }
    }

    // =================================================================
    // LOGIQUE SQL (AFFICHAGE ET ÉDITION DE PROFIL)
    // =================================================================

    /**
     * Affiche la page de profil complète (Dashboard utilisateur).
     * Agit comme un agrégateur de données provenant de multiples modèles.
     */
    public static function showProfilePage(PDO $pdo, int $userId)
    {
        try {
            // 1. Instanciation des modèles
            $userModel = new UserModel($pdo);
            $avisModel = new AvisModel($pdo);
            $vehiculeModel = new VehiculeModel($pdo);
            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $notificationModel = new NotificationModel($pdo);
            $userController = new UserController(); // Pour accéder aux méthodes Mongo (non statiques)

            // 2. Récupération de l'utilisateur principal
            $user = $userModel->findProfilById($userId);
            if (!$user) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Utilisateur non trouvé.'];
                header('Location: /');
                exit();
            }

            // 3. Récupération des données associées
            // Note : On mélange ici des données SQL et NoSQL
            $preferences = $userController->getUserPreferences($userId); // NoSQL
            $vehicules = $vehiculeModel->findByUserId($userId); // SQL
            $trajetsProposes = $trajetModel->findByChauffeurId($userId); // SQL
            $trajetsReserves = $reservationModel->getTrajetsReservesByUserId($userId); // SQL

            $avis = [];
            $reservationsEnAttente = [];

            // 4. Chargement conditionnel (seulement si chauffeur)
            if ($user['est_chauffeur']) {
                $avis = $avisModel->getChauffeurAvisValides($userId);
                $reservationsEnAttente = $reservationModel->getPendingReservationsForChauffeur($userId);

                // Récupération des passagers pour chaque trajet proposé
                if (!empty($trajetsProposes)) {
                    foreach ($trajetsProposes as $key => $trajet) {
                        $trajetsProposes[$key]['passagers'] = $reservationModel->getPassagersByTrajet($trajet['id']);
                    }
                }
            }

            // 5. Détermination du type d'affichage
            $type_utilisateur = '';
            if ($user['est_chauffeur'] && $user['est_passager']) {
                $type_utilisateur = 'Chauffeur / Passager';
            } elseif ($user['est_chauffeur']) {
                $type_utilisateur = 'Chauffeur';
            } elseif ($user['est_passager']) {
                $type_utilisateur = 'Passager';
            }

            // 6. Gestion des Notifications
            // On vérifie si l'utilisateur consulte son PROPRE profil
            $isOwner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId);
            $notificationsNonLues = [];

            if ($isOwner) {
                $notificationsNonLues = $notificationModel->getUnread($userId);
                // Si des notifications sont affichées, on les marque comme lues
                if (!empty($notificationsNonLues)) {
                    $notificationIds = array_column($notificationsNonLues, 'id');
                    $notificationModel->markAsRead($notificationIds);
                }
            }

            // 7. Envoi des données à la vue
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

            \renderView('profile', $data);
        } catch (PDOException $e) {
            error_log("Erreur showProfilePage : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du chargement du profil.'];
            header('Location: /');
            exit();
        }
    }

    /**
     * Affiche le formulaire d'édition de profil.
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
            $userController = new UserController();

            // On récupère les données actuelles pour pré-remplir les champs
            $user = $userModel->findById($userId);
            $preferences = $userController->getUserPreferences($userId);

            $data = [
                'user' => $user,
                'preferences' => $preferences
            ];
            \renderView('edit_profile', $data);
        } catch (PDOException $e) {
            error_log("Erreur showEditProfilePage : " . $e->getMessage());
            header('Location: /profile');
            exit();
        }
    }

    /**
     * Gère la mise à jour du profil complet (Infos SQL + Préférences NoSQL).
     */
    public static function updateFullProfile(PDO $pdo, array $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        $userId = $_SESSION['user_id'];

        try {
            // --- ÉTAPE 1 : SQL (Transactionnel) ---
            $pdo->beginTransaction();

            $userModel = new UserModel($pdo);
            $pseudo = trim($postData['pseudo'] ?? '');
            $email = trim($postData['email'] ?? '');
            $description = trim($postData['description'] ?? '');

            $userModel->updateProfile($userId, $pseudo, $email, $description);

            $pdo->commit();

            // --- ÉTAPE 2 : NoSQL (Flexible) ---
            // Fusion des préférences cochées (checkbox) et personnalisées (input text)
            $finalPreferences = $postData['prefs'] ?? [];

            if (!empty($postData['custom_prefs'])) {
                // Transformation de la chaîne "Vélo, Bagages" en tableau
                $customPrefs = array_map('trim', explode(',', $postData['custom_prefs']));
                $finalPreferences = array_merge($finalPreferences, array_filter($customPrefs));
            }
            // Suppression des doublons
            $finalPreferences = array_unique($finalPreferences);

            // Sauvegarde dans MongoDB
            $userController = new UserController();
            $userController->saveUserPreferences($userId, array_values($finalPreferences));

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil mis à jour avec succès.'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur SQL lors de la mise à jour.'];
        } catch (\Exception $e) {
            // Si Mongo échoue mais que SQL a réussi, on affiche un avertissement (pas une erreur fatale)
            $_SESSION['message'] = ['type' => 'warning', 'text' => 'Profil mis à jour, mais erreur sur les préférences.'];
        }

        header('Location: /profile/edit');
        exit();
    }

    /**
     * Fonction utilitaire pour le Cron Job.
     * Maintient la connexion MongoDB active sur les hébergements gratuits.
     */
    public static function ping()
    {
        header('Content-Type: application/json');
        try {
            $client = Database::getMongoClient();
            $client->listDatabases();
            echo json_encode(['status' => 'success', 'message' => 'MongoDB est réveillé !']);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }
}
