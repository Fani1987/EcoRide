<?php

namespace App\Controllers;

use PDO;
use PDOException;

class AvisController
{
    /**
     * Permet à un passager de valider un trajet terminé, de laisser un avis,
     * et déclenche le paiement du chauffeur.
     */
    public static function validateTrajet(PDO $pdo, int $reservation_id, array $avisData)
    {
        header('Content-Type: application/json');

        // Sécurité : Vérifier la connexion
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        $note = $avisData['note'] ?? null;
        $commentaire = $avisData['commentaire'] ?? '';

        if (empty($note) || !is_numeric($note) || $note < 1 || $note > 5) {
            echo json_encode(['success' => false, 'message' => 'Une note entre 1 et 5 est requise.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Vérifier que la réservation appartient bien au passager et qu'elle est en attente de validation
            $stmt = $pdo->prepare("
                SELECT r.utilisateur_id, r.covoiturage_id, c.chauffeur_id, c.prix
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND r.statut = 'confirmée' AND c.statut = 'terminé'
            ");
            $stmt->execute([$reservation_id]);
            $reservation = $stmt->fetch();

            if (!$reservation || $reservation['utilisateur_id'] != $passagerId) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Impossible de valider ce trajet.']);
                exit;
            }

            // 1. Mettre à jour le statut de la réservation à 'validée'
            $stmtUpdateRes = $pdo->prepare("UPDATE reservations SET statut = 'validée' WHERE id = ?");
            $stmtUpdateRes->execute([$reservation_id]);

            // 2. Insérer l'avis (avec statut de validation pour l'employé, par exemple NULL)
            $stmtInsertAvis = $pdo->prepare("INSERT INTO avis (utilisateur_id, covoiturage_id, note, commentaire) VALUES (?, ?, ?, ?)");
            $stmtInsertAvis->execute([$passagerId, $reservation['covoiturage_id'], $note, $commentaire]);

            // 3. Transférer les crédits au chauffeur
            $stmtPayChauffeur = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
            $stmtPayChauffeur->execute([$reservation['prix'], $reservation['chauffeur_id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trajet validé et avis enregistré ! Le chauffeur a été crédité.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur dans validateTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la validation.']);
        }
    }

    /**
     * Permet à un passager de signaler un incident, ce qui bloque le paiement.
     */
    public static function reportIncident(PDO $pdo, int $reservation_id, string $commentaire)
    {
        header('Content-Type: application/json');

        // Mêmes vérifications de sécurité que pour validateTrajet...
        if (!isset($_SESSION['user_id'])) { /* ... */
        }
        $passagerId = $_SESSION['user_id'];

        if (empty($commentaire)) {
            echo json_encode(['success' => false, 'message' => 'Un commentaire est requis pour signaler un incident.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Vérifier que la réservation existe et appartient au passager
            $stmt = $pdo->prepare("SELECT id FROM reservations WHERE id = ? AND utilisateur_id = ? AND statut = 'confirmée'");
            $stmt->execute([$reservation_id, $passagerId]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Impossible de signaler un incident pour ce trajet.']);
                exit;
            }

            // 1. Mettre à jour le statut de la réservation à 'en_litige'
            $stmtUpdateRes = $pdo->prepare("UPDATE reservations SET statut = 'en_litige' WHERE id = ?");
            $stmtUpdateRes->execute([$reservation_id]);

            // 2. Créer une entrée dans la table des incidents
            $stmtInsertIncident = $pdo->prepare("INSERT INTO incidents (reservation_id, commentaire) VALUES (?, ?)");
            $stmtInsertIncident->execute([$reservation_id, $commentaire]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'L\'incident a bien été signalé. Un employé examinera la situation.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur dans reportIncident : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors du signalement.']);
        }
    }
}
