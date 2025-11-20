<?php

namespace App\Controllers;

use PDO;
use PDOException;

// Importation des Modèles nécessaires.
// Le contrôleur agit comme un chef d'orchestre : il ne manipule pas les données lui-même,
// il demande aux Modèles de le faire.
use App\Models\AvisModel;
use App\Models\ReservationModel;
use App\Models\IncidentModel;
use App\Models\UserModel;

/**
 * Class AvisController
 *
 * Cette classe gère les interactions liées à la fin d'un trajet :
 * 1. La validation du trajet par le passager (qui déclenche le paiement et l'avis).
 * 2. Le signalement d'un problème (incident).
 */
class AvisController
{
    /**
     * Valide un trajet, publie un avis et effectue le paiement du chauffeur.
     *
     * C'est une TRANSACTION CRITIQUE :
     * Il est impératif que le paiement, la mise à jour du statut et l'avis se fassent
     * tous ensemble. Si l'un échoue, tout doit être annulé pour éviter les incohérences
     * (ex: un chauffeur payé alors que le trajet n'est pas validé).
     *
     * Route : POST /api/validateTrajet
     *
     * @param PDO $pdo Instance de connexion à la base de données.
     * @param int $reservation_id L'ID de la réservation concernée.
     * @param array $avisData Tableau contenant la note et le commentaire.
     */
    public static function validateTrajet(PDO $pdo, int $reservation_id, array $avisData)
    {
        // On indique que la réponse sera au format JSON (pour l'appel AJAX/fetch).
        header('Content-Type: application/json');

        // 1. SÉCURITÉ : Vérification de l'authentification
        // Seul un utilisateur connecté peut valider un trajet.
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        // 2. VALIDATION DES DONNÉES (Inputs)
        // On récupère la note et le commentaire.
        $note = $avisData['note'] ?? null;
        // On nettoie le commentaire (sécurité XSS basique) bien que l'échappement principal se fasse à l'affichage.
        $commentaire = htmlspecialchars($avisData['commentaire'] ?? '');

        // On vérifie que la note est valide (un entier entre 1 et 5).
        if (empty($note) || !is_numeric($note) || $note < 1 || $note > 5) {
            echo json_encode(['success' => false, 'message' => 'Une note entre 1 et 5 est requise.']);
            exit;
        }

        try {
            // =================================================================
            // 3. DÉBUT DE LA TRANSACTION (ACID)
            // =================================================================
            // On signale à MySQL qu'on commence une série d'opérations indivisibles.
            $pdo->beginTransaction();

            // Instanciation des modèles nécessaires pour cette opération.
            $reservationModel = new ReservationModel($pdo);
            $avisModel = new AvisModel($pdo);
            $userModel = new UserModel($pdo);

            // 4. VÉRIFICATION DES DROITS (Règle Métier)
            // On vérifie que :
            // - La réservation existe.
            // - Elle appartient bien à l'utilisateur connecté ($passagerId).
            // - Le statut est bien 'confirmée' (et pas déjà validée ou annulée).
            // - Le trajet est bien 'terminé' (le chauffeur a fini la course).
            // Note : Le modèle utilise 'FOR UPDATE' pour verrouiller la ligne pendant la transaction.
            $reservation = $reservationModel->findForValidation($reservation_id, $passagerId);

            if (!$reservation) {
                // Si une condition n'est pas remplie, on lève une exception pour arrêter le processus.
                throw new \Exception('Impossible de valider ce trajet (il n\'est pas terminé ou n\'est pas le vôtre).');
            }

            // =================================================================
            // 5. EXÉCUTION DES OPÉRATIONS (Écriture)
            // =================================================================

            // A. Mise à jour du statut de la réservation
            // Elle passe de 'confirmée' à 'validée'.
            $reservationModel->updateStatus($reservation_id, 'validée');

            // B. Création de l'avis
            // On insère la note et le commentaire liés à ce trajet.
            $avisModel->create($passagerId, $reservation['covoiturage_id'], (int)$note, $commentaire);

            // C. PAIEMENT DU CHAUFFEUR (Transaction Financière)
            // On crédite le compte du chauffeur du montant du trajet.
            // $reservation['chauffeur_id'] et ['prix'] viennent de la requête de vérification (étape 4).
            $userModel->creditCredits($reservation['chauffeur_id'], $reservation['prix']);

            // =================================================================
            // 6. VALIDATION FINALE (Commit)
            // =================================================================
            // Tout s'est bien passé, on valide les changements de manière définitive.
            $pdo->commit();

            // On renvoie une réponse positive au Javascript.
            echo json_encode(['success' => true, 'message' => 'Trajet validé et avis enregistré ! Le chauffeur a été crédité.']);
        } catch (\Exception $e) {
            // =================================================================
            // 7. GESTION DES ERREURS (Rollback)
            // =================================================================
            // Si une seule erreur survient (dans le try), on annule TOUT.
            // - La réservation ne change pas de statut.
            // - L'avis n'est pas créé.
            // - Le chauffeur n'est pas payé.
            // Cela garantit l'intégrité des données et de l'argent.
            $pdo->rollBack();

            // On loggue l'erreur pour le développeur.
            error_log("Erreur dans validateTrajet : " . $e->getMessage());
            // On renvoie un message d'erreur générique à l'utilisateur.
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la validation.']);
        }
        exit();
    }

    /**
     * Signale un incident sur un trajet.
     * Cette action bloque le processus normal (pas de paiement, pas d'avis).
     *
     * Route : POST /api/reportIncident
     */
    public static function reportIncident(PDO $pdo, int $reservation_id, string $commentaire)
    {
        header('Content-Type: application/json');

        // 1. Vérification Auth
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        // 2. Validation Input
        $commentaire = htmlspecialchars($commentaire); // Nettoyage XSS
        if (empty($commentaire)) {
            echo json_encode(['success' => false, 'message' => 'Un commentaire est requis pour signaler un incident.']);
            exit;
        }

        try {
            // 3. Début Transaction
            $pdo->beginTransaction();

            $reservationModel = new ReservationModel($pdo);
            $incidentModel = new IncidentModel($pdo);

            // 4. Vérification des droits
            // On vérifie que la réservation existe et appartient à l'utilisateur.
            $reservation = $reservationModel->findForIncidentReport($reservation_id, $passagerId);
            if (!$reservation) {
                throw new \Exception('Impossible de signaler un incident pour ce trajet.');
            }

            // 5. Exécution des opérations

            // A. Changement de statut de la réservation
            // Le statut 'en_litige' permet aux employés de filtrer ces réservations dans leur dashboard.
            $reservationModel->updateStatus($reservation_id, 'en_litige');

            // B. Création de l'incident en base
            $incidentModel->create($reservation_id, $commentaire);

            // 6. Validation (Commit)
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'L\'incident a bien été signalé. Un employé examinera la situation.']);
        } catch (\Exception $e) {
            // 7. Annulation (Rollback) en cas d'erreur
            $pdo->rollBack();
            error_log("Erreur dans reportIncident : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors du signalement.']);
        }
        exit();
    }
}
