<?php

namespace App\Controllers;

use PDO;
use PDOException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// On importe TOUS les modèles dont on a besoin
use App\Models\TrajetModel;
use App\Models\ReservationModel;
use App\Models\UserModel;
use App\Models\AvisModel;
use App\Controllers\UserController; // On le garde pour la logique MongoDB

class TrajetController
{
    /**
     * Gère la création d'un trajet (covoiturage).
     * Le contrôleur gère la validation, la session, et la transaction.
     */
    public static function ajouterTrajet(PDO $pdo, array $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit();
        }
        $userId = $_SESSION['user_id'];
        $costToAddTrajet = 2; // Coût fixe de 2 crédits

        // 1. Validation des données
        $requiredFields = ['depart', 'arrivee', 'date_depart', 'prix', 'places_disponibles', 'vehicule_id'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
                exit();
            }
        }

        try {
            // 2. Démarrer la transaction
            $pdo->beginTransaction();

            // 3. Instancier les modèles
            $userModel = new UserModel($pdo);
            $trajetModel = new TrajetModel($pdo);

            // 4. Logique métier : Vérifier et déduire les crédits
            $currentCredits = $userModel->getCreditsForUpdate($userId); // FOR UPDATE verrouille la ligne
            if ($currentCredits === false || $currentCredits < $costToAddTrajet) {
                throw new \Exception('Crédits insuffisants pour ajouter un trajet.');
            }

            if (!$userModel->debitCredits($userId, $costToAddTrajet)) {
                throw new \Exception('La mise à jour des crédits a échoué.');
            }

            // 5. Logique métier : Créer le trajet
            $trajetCree = $trajetModel->create(
                $userId,
                (int)$postData['vehicule_id'],
                ($postData['depart']),
                ($postData['arrivee']),
                ($postData['date_depart']),
                (float)$postData['prix'],
                (int)$postData['places_disponibles'],
                isset($postData['est_ecologique']) ? 1 : 0
            );

            if (!$trajetCree) {
                throw new \Exception('La création du trajet a échoué.');
            }

            // 6. Valider la transaction
            $pdo->commit();

            // 7. Réponse (Redirection)
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Trajet ajouté avec succès et ' . $costToAddTrajet . ' crédits déduits !'];
            header('Location: /profile'); // Redirection vers le profil
            exit();
        } catch (\Exception $e) { // On "catch" \Exception pour tout attraper (PDOException et nos \Exception)
            // 8. Gestion des erreurs
            $pdo->rollBack();
            error_log("Erreur lors de l'ajout de trajet : " . $e->getMessage());

            // On peut choisir de répondre en JSON (si c'est une API) ou de rediriger
            $_SESSION['message'] = ['type' => 'danger', 'text' => $e->getMessage()];
            header('Location: /profile'); // Redirection vers le profil avec un message d'erreur
            exit();
        }
    }

    /**
     * Affiche la page de détail d'un trajet
     */
    public static function showTrajetDetail(PDO $pdo, int $trajetId)
    {
        try {
            // 1. Instancier les modèles
            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $avisModel = new AvisModel($pdo);
            $userController = new UserController(); // Pour la logique MongoDB des préférences

            // 2. Récupérer les données via les modèles
            $trajet = $trajetModel->findFullTrajetById($trajetId);

            if (!$trajet) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Trajet non trouvé.'];
                header('Location: /covoiturage');
                exit();
            }

            // On sépare la récupération des données
            $passagers = $reservationModel->getPassagersByTrajet($trajetId);
            $avis = $avisModel->getChauffeurAvisValides($trajet['chauffeur_id']);
            $preferences = $userController->getUserPreferences($trajet['chauffeur_id']); // Logique Mongo

            // 3. Préparer les données pour la vue
            $data = [
                'trajet' => $trajet,
                'passagers' => $passagers,
                'avis' => $avis,
                'preferences' => $preferences,
                'isDriver' => (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $trajet['chauffeur_id'])
            ];

            // 4. Rendre la vue
            \renderView('covoiturage-detail', $data);
        } catch (PDOException $e) {
            error_log("Erreur showTrajetDetail : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erreur lors de la récupération des détails du trajet.'];
            header('Location: /covoiturage');
            exit();
        }
    }

    /**
     * Gère la réservation d'un trajet par un passager
     */
    public static function participerTrajet(PDO $pdo, int $trajetId, int $userId)
    {
        header('Content-Type: application/json');

        try {
            $pdo->beginTransaction();

            // 1. Instancier les modèles
            $trajetModel = new TrajetModel($pdo);
            $userModel = new UserModel($pdo);
            $reservationModel = new ReservationModel($pdo);

            // 2. Logique métier : Vérifications
            if ($reservationModel->hasActiveReservation($userId, $trajetId)) {
                throw new \Exception('Vous êtes déjà inscrit à ce trajet.');
            }

            $trajet = $trajetModel->findByIdForUpdate($trajetId);
            if (!$trajet) {
                throw new \Exception('Trajet non trouvé.');
            }
            if ($trajet['places_disponibles'] <= 0) {
                throw new \Exception('Plus de places disponibles.');
            }
            if ($trajet['chauffeur_id'] == $userId) {
                throw new \Exception('Vous ne pouvez pas réserver votre propre trajet.');
            }
            if ($trajet['statut'] !== 'planifié') {
                throw new \Exception('Ce trajet n\'est plus ouvert aux réservations.');
            }

            $creditsPassager = $userModel->getCreditsForUpdate($userId);
            if ($creditsPassager === false || $creditsPassager < $trajet['prix']) {
                throw new \Exception('Crédits insuffisants.');
            }

            // 3. Exécution via les modèles
            $userModel->debitCredits($userId, $trajet['prix']);
            $trajetModel->decrementPlaces($trajetId);
            $reservationModel->create($userId, $trajetId, 'en_attente'); // Statut US 8

            // 4. Commit et réponse
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Réservation effectuée ! Elle est en attente de confirmation par le chauffeur.']);
        } catch (\Exception $e) {
            // 5. Gestion des erreurs
            $pdo->rollBack();
            error_log("Erreur participerTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Gère la confirmation/refus d'une réservation par un chauffeur
     */
    public static function confirmerReservation(PDO $pdo, int $reservationId, string $statut)
    {
        if (!isset($_SESSION['user_id']) || !in_array($statut, ['confirmée', 'refusée'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Action invalide.'];
            header('Location: /profile');
            exit;
        }
        $chauffeurId = $_SESSION['user_id'];

        try {
            // 1. Instancier les modèles
            $reservationModel = new ReservationModel($pdo);
            $userModel = new UserModel($pdo);

            // 2. Logique métier : Vérifier les droits
            $reservationData = $reservationModel->findReservationForChauffeur($reservationId, $chauffeurId);
            if (!$reservationData) {
                throw new \Exception('Réservation non trouvée ou vous n\'êtes pas le chauffeur de ce trajet.');
            }

            // 3. Exécution via les modèles
            $reservationModel->updateStatus($reservationId, $statut);

            // 4. Logique métier : Notifier le passager
            $message = "";
            if ($statut === 'confirmée') {
                $message = "Bonne nouvelle ! Votre réservation pour le trajet de " . htmlspecialchars($reservationData['depart']) . " à " . htmlspecialchars($reservationData['arrivee']) . " a été confirmée.";
            } else if ($statut === 'refusée') {
                $message = "Désolé, votre réservation pour le trajet de " . htmlspecialchars($reservationData['depart']) . " à " . htmlspecialchars($reservationData['arrivee']) . " a été refusée par le chauffeur.";
                // TODO: Logique de remboursement si l'argent a été pris à la réservation
            }

            if ($message) {
                $userModel->createNotification($reservationData['utilisateur_id'], $message);
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => 'La réservation a bien été traitée.'];
        } catch (\Exception $e) {
            error_log("Erreur confirmerReservation : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => $e->getMessage()];
        }

        header('Location: /profile');
        exit();
    }

    /**
     * Gère le démarrage d'un trajet (US 11)
     */
    public static function startTrajet(PDO $pdo, int $trajetId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $userId = $_SESSION['user_id'];

        try {
            $trajetModel = new TrajetModel($pdo);

            // 1. Vérifier le propriétaire (on pourrait le faire dans le modèle, mais c'est une règle métier)
            $trajet = $trajetModel->findByIdForUpdate($trajetId); // FOR UPDATE pour la transaction
            if (!$trajet || $trajet['chauffeur_id'] != $userId) {
                throw new \Exception('Action non autorisée.');
            }

            // 2. Exécuter la mise à jour
            $rowsAffected = $trajetModel->updateStatusConditional($trajetId, 'en_cours', 'planifié');
            if ($rowsAffected == 0) {
                throw new \Exception('Le trajet n\'a pas pu être démarré (il est peut-être déjà en cours ou terminé).');
            }

            echo json_encode(['success' => true, 'message' => 'Trajet démarré avec succès.']);
        } catch (\Exception $e) {
            error_log("Erreur dans startTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Gère la fin d'un trajet (US 11)
     */
    public static function endTrajet(PDO $pdo, int $trajetId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $userId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $userModel = new UserModel($pdo);

            // 1. Vérifier le propriétaire
            $trajet = $trajetModel->findByIdForUpdate($trajetId);
            if (!$trajet || $trajet['chauffeur_id'] != $userId) {
                throw new \Exception('Action non autorisée.');
            }

            // 2. Mettre à jour le statut du trajet
            $rowsAffected = $trajetModel->updateStatusConditional($trajetId, 'terminé', 'en_cours');
            if ($rowsAffected == 0) {
                throw new \Exception('Le trajet n\'a pas pu être terminé (il n\'était pas en cours).');
            }

            // 3. Notifier les passagers (Email + Notification interne)
            $passagers = $reservationModel->getConfirmedPassagers($trajetId);
            $message = "Votre trajet de " . htmlspecialchars($trajet['depart']) . " à " . htmlspecialchars($trajet['arrivee']) . " est terminé. N'oubliez pas de le valider sur votre profil !";

            foreach ($passagers as $passager) {
                // Notification interne
                $userModel->createNotification($passager['utilisateur_id'], $message);

                // Envoi d'email (simulé)
                self::sendSimulationEmail(
                    $passager['email'],
                    $passager['pseudo'],
                    'Votre trajet EcoRide est terminé !',
                    $message
                );
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trajet terminé. Les passagers ont été notifiés.']);
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Erreur dans endTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Gère l'annulation d'une réservation par le passager
     */
    public static function cancelReservation(PDO $pdo, int $reservationId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // 1. Instancier les modèles
            $reservationModel = new ReservationModel($pdo);
            $trajetModel = new TrajetModel($pdo);
            $userModel = new UserModel($pdo);

            // 2. Logique métier : Vérifier les droits
            $reservation = $reservationModel->findReservationForPassager($reservationId, $passagerId);
            if (!$reservation) {
                throw new \Exception('Réservation non trouvée ou déjà annulée/terminée.');
            }
            if ($reservation['trajet_statut'] !== 'planifié') {
                throw new \Exception('Annulation impossible. Le trajet a peut-être déjà commencé.');
            }

            // 3. Exécution via les modèles
            $reservationModel->updateStatus($reservationId, 'annulée');
            $userModel->creditCredits($passagerId, $reservation['prix']); // Rembourser
            $trajetModel->incrementPlaces($reservation['covoiturage_id']); // Rajouter la place

            // 4. Commit et réponse
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Votre réservation a été annulée et vos crédits vous ont été remboursés.']);
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Erreur dans cancelReservation : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Gère l'annulation d'un trajet par le chauffeur
     */
    public static function cancelTrajet(PDO $pdo, int $trajetId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $chauffeurId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // 1. Instancier les modèles
            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $userModel = new UserModel($pdo);

            // 2. Logique métier : Vérifier les droits
            $trajet = $trajetModel->findByIdForUpdate($trajetId);
            if (!$trajet || $trajet['chauffeur_id'] != $chauffeurId) {
                throw new \Exception('Action non autorisée.');
            }
            if ($trajet['statut'] !== 'planifié') {
                throw new \Exception('Annulation impossible (trajet non planifié).');
            }

            // 3. Mettre à jour le statut du trajet
            $trajetModel->updateStatus($trajetId, 'annulé');

            // 4. Gérer les passagers (Remboursement + Notification)
            $passagers = $reservationModel->getConfirmedPassagers($trajetId);
            $message = "Le trajet de " . htmlspecialchars($trajet['depart']) . " à " . htmlspecialchars($trajet['arrivee']) . " a été annulé par le chauffeur. Vos crédits ont été remboursés.";

            foreach ($passagers as $passager) {
                $userModel->creditCredits($passager['utilisateur_id'], $trajet['prix']); // Rembourser
                $reservationModel->updateStatus($passager['id'], 'annulée'); // Annuler leur réservation
                $userModel->createNotification($passager['utilisateur_id'], $message); // Notifier

                // Envoyer email (simulation)
                self::sendSimulationEmail(
                    $passager['email'],
                    $passager['pseudo'],
                    'Annulation de votre trajet EcoRide',
                    $message
                );
            }

            // 5. Commit et réponse
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Le trajet a été annulé. Les passagers ont été notifiés et remboursés.']);
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Erreur dans cancelTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * Simule l'envoi d'un email en écrivant dans les logs
     * (Remplace le bloc PHPMailer pour la propreté)
     */
    private static function sendSimulationEmail(string $email, string $pseudo, string $sujet, string $corps)
    {
        // Bloc PHPMailer original ici, mais commenté
        error_log("SIMULATION EMAIL: \n Destinataire: $email ($pseudo) \n Sujet: $sujet \n Corps: $corps\n");

        /*
        $mail = new PHPMailer(true);
        try {
            // Configuration du serveur SMTP (à mettre dans votre fichier .env)
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['MAIL_PORT'];

            // Destinataires et expéditeur
            $mail->setFrom('no-reply@ecoride.fr', 'EcoRide');
            $mail->addAddress($email, $pseudo);

            // Contenu de l'e-mail
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body    = 'Bonjour ' . htmlspecialchars($pseudo) . ',<br><br>' . $corps;
            $mail->AltBody = 'Bonjour ' . htmlspecialchars($pseudo) . ', ' . strip_tags($corps);

            $mail->send();
        } catch (Exception $e) {
            // Ne pas bloquer le processus si un email échoue, mais l'enregistrer
            error_log("PHPMailer n'a pas pu envoyer l'email à " . $email . ". Erreur: {$mail->ErrorInfo}");
        }
        */
    }
}
