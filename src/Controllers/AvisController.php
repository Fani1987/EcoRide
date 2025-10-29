<?php

namespace App\Controllers; // <-- Assurez-vous que le namespace est Controllers

use PDO;
use PDOException;
// On importe TOUS les modèles nécessaires
use App\Models\AvisModel;
use App\Models\ReservationModel;
use App\Models\IncidentModel;
use App\Models\UserModel;

class AvisController // <-- Le nom de la classe doit correspondre au fichier
{
    /**
     * Permet à un passager de valider un trajet terminé, de laisser un avis,
     * et déclenche le paiement du chauffeur. (US 11) [cite_start][cite: 1145, 1146, 1147]
     */
    public static function validateTrajet(PDO $pdo, int $reservation_id, array $avisData)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        // 1. Validation des données
        $note = $avisData['note'] ?? null;
        $commentaire = ($avisData['commentaire'] ?? '');

        if (empty($note) || !is_numeric($note) || $note < 1 || $note > 5) {
            echo json_encode(['success' => false, 'message' => 'Une note entre 1 et 5 est requise.']);
            exit;
        }

        try {
            // 2. Démarrer la transaction
            $pdo->beginTransaction();

            // 3. Instancier les modèles
            $reservationModel = new ReservationModel($pdo);
            $avisModel = new AvisModel($pdo);
            $userModel = new UserModel($pdo);

            // 4. Logique métier : Vérifier les droits
            $reservation = $reservationModel->findForValidation($reservation_id, $passagerId);
            if (!$reservation) {
                throw new \Exception('Impossible de valider ce trajet (il n\'est pas terminé ou n\'est pas le vôtre).');
            }

            // 5. Exécution via les modèles (l'ordre est important)

            // a. Mettre à jour la réservation
            $reservationModel->updateStatus($reservation_id, 'validée');

            // b. Insérer l'avis
            $avisModel->create($passagerId, $reservation['covoiturage_id'], (int)$note, $commentaire);

            // c. [cite_start]Payer le chauffeur [cite: 1146]
            $userModel->creditCredits($reservation['chauffeur_id'], $reservation['prix']);

            // 6. Valider la transaction
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trajet validé et avis enregistré ! Le chauffeur a été crédité.']);
        } catch (\Exception $e) {
            // 7. Gestion des erreurs
            $pdo->rollBack();
            error_log("Erreur dans validateTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la validation.']);
        }
        exit();
    }

    /**
     * Permet à un passager de signaler un incident, ce qui bloque le paiement. (US 11) [cite_start][cite: 1148]
     */
    public static function reportIncident(PDO $pdo, int $reservation_id, string $commentaire)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        $commentaire = ($commentaire);
        if (empty($commentaire)) {
            echo json_encode(['success' => false, 'message' => 'Un commentaire est requis pour signaler un incident.']);
            exit;
        }

        try {
            // 1. Démarrer la transaction
            $pdo->beginTransaction();

            // 2. Instancier les modèles
            $reservationModel = new ReservationModel($pdo);
            $incidentModel = new IncidentModel($pdo);

            // 3. Logique métier : Vérifier les droits
            $reservation = $reservationModel->findForIncidentReport($reservation_id, $passagerId);
            if (!$reservation) {
                throw new \Exception('Impossible de signaler un incident pour ce trajet.');
            }

            // 4. Exécution via les modèles

            // a. Mettre à jour la réservation
            $reservationModel->updateStatus($reservation_id, 'en_litige');

            // b. Créer l'incident
            $incidentModel->create($reservation_id, $commentaire);

            // 5. Valider la transaction
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'L\'incident a bien été signalé. Un employé examinera la situation.']);
        } catch (\Exception $e) {
            // 6. Gestion des erreurs
            $pdo->rollBack();
            error_log("Erreur dans reportIncident : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors du signalement.']);
        }
        exit();
    }
}
