<?php

namespace App\Controllers;

use App\Core\Database;
use MongoDB\BSON\ObjectId;
// Pour travailler avec l'_id de MongoDB

use PDO;
use PDOException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController
{

    public function getUserPreferences(int $mysqlUserId)
    {
        $client = Database::getMongoClient();
        $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
        $preferencesCollection = $database->selectCollection('preferences'); // Collection dédiée

        try {
            // On cherche le document par l'ID de l'utilisateur MySQL
            $userPreferences = $preferencesCollection->findOne(['mysql_user_id' => $mysqlUserId]);
            return $userPreferences ? (array)$userPreferences->preferences : [];
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            error_log("Erreur MongoDB (get) : " . $e->getMessage());
            return []; // Retourner un tableau vide en cas d'erreur
        }
    }


    public function saveUserPreferences(int $mysqlUserId, array $preferences)
    {
        $client = Database::getMongoClient();
        $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
        $preferencesCollection = $database->selectCollection('preferences');

        try {
            // On met à jour ou on insère (upsert) le document basé sur l'ID de l'utilisateur MySQL
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

    public static function showProfilePage($pdo, $userId)
    {
        //récupérer et afficher les données
        $stmtUser = $pdo->prepare("SELECT u.*, pu.est_chauffeur, pu.est_passager FROM utilisateurs u JOIN profils_utilisateur pu ON u.id = pu.utilisateur_id WHERE u.id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $userController = new UserController();
        $preferences = $userController->getUserPreferences($userId);

        if (!$user) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Utilisateur non trouvé.'];
            header('Location: /');
            exit();
        }

        $type_utilisateur = '';
        if ($user['est_chauffeur'] && $user['est_passager']) {
            $type_utilisateur = 'Chauffeur / Passager';
        } elseif ($user['est_chauffeur']) {
            $type_utilisateur = 'Chauffeur';
        } elseif ($user['est_passager']) {
            $type_utilisateur = 'Passager';
        }

        $avis = [];
        if ($user['est_chauffeur']) {
            $stmtAvis = $pdo->prepare("SELECT a.note, a.commentaire, u_auteur.pseudo AS auteur FROM avis a JOIN covoiturages c ON a.covoiturage_id = c.id JOIN utilisateurs u_auteur ON a.utilisateur_id = u_auteur.id WHERE c.chauffeur_id = ? ORDER BY a.id DESC");
            $stmtAvis->execute([$userId]);
            $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmtVehicules = $pdo->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ?");
        $stmtVehicules->execute([$userId]);
        $vehicules = $stmtVehicules->fetchAll(PDO::FETCH_ASSOC);

        // On récupère d'abord tous les trajets proposés par le chauffeur
        $stmtTrajetsProposes = $pdo->prepare("SELECT * FROM covoiturages WHERE chauffeur_id = ? ORDER BY date_depart DESC");
        $stmtTrajetsProposes->execute([$userId]);
        $trajetsProposes = $stmtTrajetsProposes->fetchAll(PDO::FETCH_ASSOC);

        // Si le chauffeur a des trajets, on va chercher tous les passagers pour tous ces trajets en une seule fois
        if (!empty($trajetsProposes)) {
            // On extrait les IDs de tous les trajets
            $trajetIds = array_column($trajetsProposes, 'id');
            $placeholders = implode(',', array_fill(0, count($trajetIds), '?'));

            // On récupère toutes les réservations associées à ces trajets
            $sqlReservations = "
                SELECT r.covoiturage_id, r.statut, u.pseudo AS passager_pseudo
                FROM reservations r
                JOIN utilisateurs u ON r.utilisateur_id = u.id
                WHERE r.covoiturage_id IN ($placeholders)
            ";
            $stmtReservations = $pdo->prepare($sqlReservations);
            $stmtReservations->execute($trajetIds);
            $allReservations = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);

            // On organise les réservations par ID de trajet pour les retrouver facilement
            $reservationsByTrajetId = [];
            foreach ($allReservations as $reservation) {
                $reservationsByTrajetId[$reservation['covoiturage_id']][] = $reservation;
            }

            // Enfin, on ajoute la liste des passagers à chaque trajet dans notre tableau principal
            foreach ($trajetsProposes as $key => $trajet) {
                $trajetsProposes[$key]['passagers'] = $reservationsByTrajetId[$trajet['id']] ?? [];
            }
        }

        $stmtTrajetsReserves = $pdo->prepare("
            SELECT 
                c.id, c.depart, c.arrivee, c.date_depart, 
                c.statut AS trajet_statut, -- On renomme le statut du covoiturage
                u.pseudo AS chauffeur_pseudo,
                r.id AS reservation_id,
                r.statut AS reservation_statut -- On garde le statut de la réservation
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            JOIN utilisateurs u ON c.chauffeur_id = u.id
            WHERE r.utilisateur_id = ?
            ORDER BY c.date_depart DESC
        "); // On récupère les trajets réservés par l'utilisateur
        $stmtTrajetsReserves->execute([$userId]);
        $trajetsReserves = $stmtTrajetsReserves->fetchAll(PDO::FETCH_ASSOC);

        $reservationsEnAttente = [];
        if ($user['est_chauffeur']) {
            $stmtReservationsEnAttente = $pdo->prepare("
        SELECT r.id, r.statut, u.pseudo AS passager_pseudo, c.depart, c.arrivee, c.date_depart
        FROM reservations r
        JOIN utilisateurs u ON r.utilisateur_id = u.id
        JOIN covoiturages c ON r.covoiturage_id = c.id
        WHERE c.chauffeur_id = ? AND r.statut = 'en_attente'
        ORDER BY r.date_reservation ASC
    ");
            $stmtReservationsEnAttente->execute([$userId]);
            $reservationsEnAttente = $stmtReservationsEnAttente->fetchAll(PDO::FETCH_ASSOC);
        }


        $isOwner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId);

        $notificationsNonLues = [];
        if ($isOwner) {
            // 1. On récupère les notifications non lues, comme avant.
            $stmtNotifs = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? AND est_lu = 0 ORDER BY date_creation DESC");
            $stmtNotifs->execute([$userId]);
            $notificationsNonLues = $stmtNotifs->fetchAll(PDO::FETCH_ASSOC);

            // 2. NOUVEAU : Si on a trouvé des notifications, on les marque immédiatement comme lues dans la base de données.
            if (!empty($notificationsNonLues)) {
                // On récupère les IDs de toutes les notifications qu'on vient de chercher
                $notificationIds = array_column($notificationsNonLues, 'id');
                // On crée les '?' pour la requête SQL (ex: ?,?,?)
                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));

                // On exécute une seule requête UPDATE pour toutes les marquer comme lues
                $stmtMarkAsRead = $pdo->prepare("UPDATE notifications SET est_lu = 1 WHERE id IN ($placeholders)");
                $stmtMarkAsRead->execute($notificationIds);
            }
        }
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
    }

    public static function showEditProfilePage($pdo)
    {
        // On ne peut modifier que son propre profil, donc on vérifie la session
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        $userId = $_SESSION['user_id'];

        // On récupère les informations de l'utilisateur et ses préférences
        $stmtUser = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $userController = new UserController();
        $preferences = $userController->getUserPreferences($userId);

        $data = [
            'user' => $user,
            'preferences' => $preferences
        ];

        // On affiche la nouvelle vue en lui passant les données
        \renderView('edit_profile', $data);
    }

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

            $pseudo = trim($postData['pseudo'] ?? '');
            $email = trim($postData['email'] ?? '');
            $description = trim($postData['description'] ?? '');

            $stmtUser = $pdo->prepare("UPDATE utilisateurs SET pseudo = ?, email = ?, description = ? WHERE id = ?");
            $stmtUser->execute([$pseudo, $email, $description, $userId]);

            $pdo->commit();


            // --- LOGIQUE DE MISE À JOUR DES PRÉFÉRENCES (MongoDB) ---
            $finalPreferences = $postData['prefs'] ?? [];
            if (!empty($postData['custom_prefs'])) {
                $customPrefs = explode(',', $postData['custom_prefs']);
                $customPrefs = array_map('trim', $customPrefs);
                $customPrefs = array_filter($customPrefs);
                $finalPreferences = array_merge($finalPreferences, $customPrefs);
            }
            $finalPreferences = array_unique($finalPreferences);

            $userController = new UserController();
            $userController->saveUserPreferences($userId, array_values($finalPreferences));


            // --- Message et redirection ---
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil mis à jour avec succès.'];
            header('Location: /profile/edit');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur lors de la mise à jour du profil complet : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de la mise à jour.'];
            header('Location: /profile/edit');
            exit();
        }
    }

    /**
     * Gère l'ajout ou la mise à jour d'un véhicule pour l'utilisateur.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $userId L'ID de l'utilisateur propriétaire du véhicule.
     * @param array $postData Les données POST du formulaire de véhicule.
     */
    public static function updateVehicle($pdo, $userId, $postData)
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            exit();
        }

        $vehiculeId = $postData['id'] ?? null; // Peut être null si c'est un nouveau véhicule
        $marque = trim($postData['marque'] ?? '');
        $modele = trim($postData['modele'] ?? '');
        $couleur = trim($postData['couleur'] ?? '');
        $energie = trim($postData['energie'] ?? ''); // 'essence','diesel','electrique','hybride'
        $plaque_immatriculation = trim($postData['plaque_immatriculation'] ?? '');
        $date_premiere_immat = trim($postData['date_premiere_immat'] ?? ''); // Format YYYY-MM-DD

        // Validation des champs requis pour un véhicule
        if (empty($marque) || empty($modele) || empty($plaque_immatriculation)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Marque, modèle et plaque d\'immatriculation sont requis.']);
            exit();
        }

        try {
            if ($vehiculeId) {
                // Mise à jour d'un véhicule existant
                $stmt = $pdo->prepare("UPDATE vehicules SET marque = ?, modele = ?, couleur = ?, energie = ?, plaque_immatriculation = ?, date_premiere_immat = ? WHERE id = ? AND utilisateur_id = ?");
                if (!$stmt->execute([$marque, $modele, $couleur, $energie, $plaque_immatriculation, $date_premiere_immat, $vehiculeId, $userId])) {
                    throw new PDOException("Failed to update vehicle.");
                }
                $message = 'Véhicule mis à jour avec succès.';
            } else {

                // Ajout d'un nouveau véhicule
                $stmt = $pdo->prepare("INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, energie, plaque_immatriculation, date_premiere_immat) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt->execute([$userId, $marque, $modele, $couleur, $energie, $plaque_immatriculation, $date_premiere_immat])) {
                    throw new PDOException("Failed to insert vehicle.");
                }
                $message = 'Véhicule ajouté avec succès.';
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour/ajout du véhicule (UserController::updateVehicle) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'opération sur le véhicule.']);
            exit();
        }
    }

    public static function createNotification(PDO $pdo, int $userId, string $message)
    { // Fonction pour créer une notification
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (utilisateur_id, message) VALUES (?, ?)");
            $stmt->execute([$userId, $message]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de la notification : " . $e->getMessage());
        }
    }

    public static function handleContactForm(array $postData)
    { // Fonction pour gérer le formulaire de contact
        // On vérifie que les données POST contiennent les champs requis
        $pseudo = trim($postData['pseudo'] ?? '');
        $emailExpediteur = trim($postData['email'] ?? '');
        $sujet = trim($postData['sujet'] ?? '');
        $message = trim($postData['message'] ?? '');

        // On utilise la variable $pseudo dans la validation
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
            $mail->addAddress('contact@ecoride.fr', 'Support EcoRide');

            $mail->addReplyTo($emailExpediteur, $pseudo);

            // Contenu de l'email
            $mail->isHTML(true);
            $mail->Subject = 'Nouveau message de contact : ' . htmlspecialchars($sujet);
            $mail->Body    = "Vous avez reçu un nouveau message de <b>" . htmlspecialchars($pseudo) . "</b> (" . htmlspecialchars($emailExpediteur) . ").<br><br><hr><br>" . nl2br(htmlspecialchars($message));
            $mail->AltBody = "Vous avez reçu un nouveau message de " . htmlspecialchars($pseudo) . " (" . htmlspecialchars($emailExpediteur) . ").\n\n" . htmlspecialchars($message);

            error_log("SIMULATION: Email de contact envoyé de " . $emailExpediteur . " avec le sujet : " . $sujet);

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Votre message a bien été envoyé. Nous vous répondrons dès que possible.'];
        } catch (Exception $e) {
            error_log("Erreur PHPMailer (contact) : {$mail->ErrorInfo}");
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le message n\'a pas pu être envoyé. Veuillez réessayer.'];
        }

        header('Location: /contact');
        exit;
    }
}
