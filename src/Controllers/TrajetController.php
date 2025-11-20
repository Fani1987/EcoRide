<?php

namespace App\Controllers;

// Imports des classes nécessaires
use PDO;
use Exception;

// On importe notre nouveau Service d'Email (pour ne plus polluer le contrôleur)
use App\Services\MailerService;

// On importe les Modèles (MVC) pour interagir avec la BDD
use App\Models\TrajetModel;
use App\Models\ReservationModel;
use App\Models\UserModel;
use App\Models\AvisModel;
// On importe UserController uniquement pour accéder à la méthode MongoDB (préférences)
use App\Controllers\UserController;

class TrajetController
{
    /**
     * Gère la publication d'un nouveau trajet par un chauffeur.
     * Route : POST /api/ajouterTrajet
     */
    public static function ajouterTrajet(PDO $pdo, array $postData)
    {
        // 1. Gardien de sécurité : L'utilisateur doit être connecté
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit();
        }
        $userId = $_SESSION['user_id'];
        $costToAddTrajet = 2; // Coût en crédits pour publier une annonce

        // 2. Validation des champs obligatoires
        $requiredFields = ['depart', 'arrivee', 'date_depart', 'prix', 'places_disponibles', 'vehicule_id'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Le champ $field est requis."]);
                exit();
            }
        }

        try {
            // 3. DÉBUT DE LA TRANSACTION (ACID)
            // On doit débiter l'utilisateur ET créer le trajet. Tout ou rien.
            $pdo->beginTransaction();

            $userModel = new UserModel($pdo);

            // On verrouille la ligne de l'utilisateur pour éviter qu'il dépense ses crédits ailleurs en même temps
            $credits = $userModel->getCreditsForUpdate($userId);

            if ($credits < $costToAddTrajet) {
                // Pas assez de crédits ? On annule tout.
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Crédits insuffisants (2 crédits requis).']);
                exit();
            }

            // 4. Exécution des modifications
            $trajetModel = new TrajetModel($pdo);
            $estEcologique = isset($postData['est_ecologique']) ? 1 : 0;

            // a. Débit des crédits
            $userModel->debitCredits($userId, $costToAddTrajet);

            // b. Création du trajet en base
            $trajetModel->create(
                $userId,
                (int)$postData['vehicule_id'],
                $postData['depart'],
                $postData['arrivee'],
                $postData['date_depart'],
                (float)$postData['prix'],
                (int)$postData['places_disponibles'],
                $estEcologique
            );

            // 5. Validation finale
            $pdo->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Trajet publié avec succès !']);
        } catch (Exception $e) {
            // En cas d'erreur technique, on annule tout pour ne pas laisser la BDD incohérente
            $pdo->rollBack();
            error_log("Erreur ajouterTrajet : " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
        }
    }

    /**
     * Gère la réservation d'un trajet par un passager.
     * C'est la transaction la plus critique du site.
     * Route : POST /api/reserverTrajet
     */
    public static function participerTrajet(PDO $pdo, int $trajetId, int $passagerId)
    {
        header('Content-Type: application/json');
        $coutReservation = 2; // Frais de réservation

        try {
            // 1. DÉBUT DE LA TRANSACTION
            $pdo->beginTransaction();

            // 2. Vérifications Préalables (Lecture)
            $reservationModel = new ReservationModel($pdo);
            if ($reservationModel->hasActiveReservation($passagerId, $trajetId)) {
                throw new Exception('Vous avez déjà réservé ce trajet.');
            }

            $trajetModel = new TrajetModel($pdo);
            // Important : On verrouille le trajet (FOR UPDATE) pour éviter qu'une autre personne
            // ne prenne la dernière place pendant qu'on fait les vérifications.
            $trajet = $trajetModel->findByIdForUpdate($trajetId);

            if (!$trajet) throw new Exception('Trajet introuvable.');
            if ($trajet['places_disponibles'] <= 0) throw new Exception('Plus de places disponibles.');
            if ($trajet['chauffeur_id'] == $passagerId) throw new Exception('Vous ne pouvez pas réserver votre propre trajet.');
            if ($trajet['statut'] !== 'planifié') throw new Exception('Ce trajet n\'est plus disponible.');

            // 3. Vérification des crédits
            $userModel = new UserModel($pdo);
            $credits = $userModel->getCreditsForUpdate($passagerId);

            if ($credits < $coutReservation) throw new Exception('Crédits insuffisants pour réserver.');

            // 4. Exécution des modifications (Écriture)

            // a. Débit du passager
            $userModel->debitCredits($passagerId, $coutReservation);

            // b. Décrémentation des places disponibles
            $trajetModel->decrementPlaces($trajetId);

            // c. Création de la réservation
            $reservationModel->create($passagerId, $trajetId, 'en_attente');

            // 5. Validation finale
            $pdo->commit();

            // 6. Notification (Hors Transaction)
            // On utilise notre Service Mailer pour garder le contrôleur propre.
            // (Dans une version avancée, on récupérerait l'email du chauffeur ici)
            MailerService::send(
                'chauffeur@test.com', // Simulation : email du chauffeur
                'Chauffeur',
                'Nouvelle réservation',
                "Un passager a réservé votre trajet. Connectez-vous pour confirmer."
            );

            echo json_encode(['success' => true, 'message' => 'Réservation effectuée. En attente de validation du chauffeur.']);
        } catch (Exception $e) {
            // Annulation totale en cas d'erreur
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Marque un trajet comme "Démarré".
     * Route : POST /api/startTrajet
     */
    public static function startTrajet(PDO $pdo, int $trajetId)
    {
        self::changeTrajetStatus($pdo, $trajetId, 'en_cours', 'planifié', 'Le trajet a démarré.');
    }

    /**
     * Marque un trajet comme "Terminé".
     * Déclenche l'envoi d'emails aux passagers pour qu'ils laissent un avis.
     * Route : POST /api/endTrajet
     */
    public static function endTrajet(PDO $pdo, int $trajetId)
    {
        // On change d'abord le statut
        $success = self::changeTrajetStatus($pdo, $trajetId, 'terminé', 'en_cours', 'Le trajet est terminé.');

        if ($success) {
            // Si le statut a bien changé, on notifie les passagers
            $reservationModel = new ReservationModel($pdo);
            $passagers = $reservationModel->getConfirmedPassagers($trajetId);

            foreach ($passagers as $passager) {
                // Appel au Service Mailer pour chaque passager
                MailerService::send(
                    $passager['email'],
                    $passager['pseudo'],
                    'Trajet terminé',
                    "Le trajet est terminé. Merci de le valider sur votre espace pour laisser un avis et payer le chauffeur."
                );
            }
        }
    }

    /**
     * Méthode privée utilitaire pour changer le statut d'un trajet.
     * Factorise le code de startTrajet et endTrajet.
     */
    private static function changeTrajetStatus(PDO $pdo, int $trajetId, string $newStatus, string $requiredOldStatus, string $successMessage)
    {
        header('Content-Type: application/json');
        try {
            $trajetModel = new TrajetModel($pdo);
            // updateStatusConditional vérifie que l'ancien statut est bien celui attendu
            // (ex: on ne peut pas finir un trajet qui n'a pas démarré)
            $rowCount = $trajetModel->updateStatusConditional($trajetId, $newStatus, $requiredOldStatus);

            if ($rowCount > 0) {
                echo json_encode(['success' => true, 'message' => $successMessage]);
                return true;
            } else {
                echo json_encode(['success' => false, 'message' => 'Impossible de changer le statut (état incorrect).']);
                return false;
            }
        } catch (Exception $e) {
            error_log("Erreur changeTrajetStatus : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
            return false;
        }
    }

    /**
     * Permet au chauffeur de confirmer ou refuser une réservation.
     * Route : POST /api/confirmReservation
     */
    public static function confirmerReservation(PDO $pdo, int $reservationId, string $statut)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        try {
            $reservationModel = new ReservationModel($pdo);
            // Vérification de sécurité : le chauffeur ne peut confirmer QUE ses propres trajets
            $reservation = $reservationModel->findReservationForChauffeur($reservationId, $_SESSION['user_id']);

            if (!$reservation) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Réservation introuvable ou droits insuffisants.'];
            } else {
                // Mise à jour du statut
                $reservationModel->updateStatus($reservationId, $statut);
                $_SESSION['message'] = ['type' => 'success', 'text' => "Réservation $statut."];

                // (Optionnel : Notifier le passager via MailerService ici)
            }
        } catch (Exception $e) {
            error_log("Erreur confirmerReservation : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur serveur.'];
        }

        header('Location: /profile');
        exit;
    }

    /**
     * Annulation par le Passager.
     * Remboursement des crédits si annulé à temps.
     * Route : POST /api/cancelReservation
     */
    public static function cancelReservation(PDO $pdo, int $reservationId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non connecté.']);
            exit;
        }

        try {
            // 1. Transaction (Remboursement + Annulation)
            $pdo->beginTransaction();

            $reservationModel = new ReservationModel($pdo);
            $trajetModel = new TrajetModel($pdo);
            $userModel = new UserModel($pdo);

            // 2. Vérifications
            $reservation = $reservationModel->findReservationForPassager($reservationId, $_SESSION['user_id']);
            if (!$reservation) throw new Exception('Réservation introuvable.');
            // On ne peut annuler que si le trajet n'a pas encore démarré
            if ($reservation['trajet_statut'] !== 'planifié') throw new Exception('Trop tard pour annuler.');

            // 3. Modifications
            $reservationModel->updateStatus($reservationId, 'annulée');
            // On libère la place pour quelqu'un d'autre
            $trajetModel->incrementPlaces($reservation['covoiturage_id']);

            // 4. Remboursement
            // Prix du trajet + frais de réservation (2 crédits)
            $refundAmount = $reservation['prix'] + 2;
            $userModel->creditCredits($_SESSION['user_id'], $refundAmount);

            // 5. Validation
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Réservation annulée et crédits remboursés.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Annulation du trajet complet par le Chauffeur.
     * Doit rembourser TOUS les passagers.
     * Route : POST /api/cancelTrajet
     */
    public static function cancelTrajet(PDO $pdo, int $trajetId)
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non connecté.']);
            exit;
        }

        try {
            // 1. Transaction de masse
            $pdo->beginTransaction();

            $trajetModel = new TrajetModel($pdo);
            $reservationModel = new ReservationModel($pdo);
            $userModel = new UserModel($pdo);

            // 2. Vérification droits chauffeur
            $trajet = $trajetModel->findFullTrajetById($trajetId);
            if (!$trajet || $trajet['chauffeur_id'] != $_SESSION['user_id']) {
                throw new Exception('Trajet invalide ou accès refusé.');
            }

            // 3. Annulation du trajet
            $trajetModel->updateStatus($trajetId, 'annulé');

            // 4. Remboursement en boucle des passagers
            $passagers = $reservationModel->getConfirmedPassagers($trajetId);
            foreach ($passagers as $passager) {
                $refundAmount = $trajet['prix'] + 2;

                // Remboursement
                $userModel->creditCredits($passager['utilisateur_id'], $refundAmount);
                // Annulation de la réservation
                $reservationModel->updateStatus($passager['id'], 'annulée');

                // Notification Email via Service
                MailerService::send(
                    $passager['email'],
                    $passager['pseudo'],
                    'Trajet annulé',
                    "Le conducteur a annulé le trajet. Vous avez été intégralement remboursé de $refundAmount crédits."
                );
            }

            // 5. Validation
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trajet annulé. Tous les passagers ont été remboursés.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur cancelTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
        }
    }

    /**
     * Affiche la page de détail d'un trajet (GET).
     * Appelle plusieurs modèles pour tout afficher (Trajet, Passagers, Avis).
     */
    public static function showTrajetDetail(PDO $pdo, int $trajetId)
    {
        try {
            $trajetModel = new TrajetModel($pdo);
            $userController = new UserController(); // Instance pour accéder à MongoDB
            $reservationModel = new ReservationModel($pdo);
            $avisModel = new AvisModel($pdo);

            // 1. Infos principales
            $trajet = $trajetModel->findFullTrajetById($trajetId);

            if (!$trajet) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Trajet non trouvé.'];
                header('Location: /covoiturage');
                exit();
            }

            // 2. Infos complémentaires (Hybride SQL/NoSQL)
            $preferences = $userController->getUserPreferences($trajet['chauffeur_id']); // MongoDB
            $passagers = $reservationModel->getPassagersByTrajet($trajetId); // SQL
            $avis = $avisModel->getChauffeurAvisValides($trajet['chauffeur_id']); // SQL

            // 3. Envoi à la vue
            $data = [
                'trajet' => $trajet,
                'preferences' => $preferences,
                'passagers' => $passagers,
                'avis' => $avis
            ];

            \renderView('covoiturage-detail', $data);
        } catch (Exception $e) {
            error_log("Erreur showTrajetDetail : " . $e->getMessage());
            header('Location: /covoiturage');
            exit();
        }
    }
}
