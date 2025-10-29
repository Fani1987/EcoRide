<?php

namespace App\Models;

use PDO;
use PDOException;

class ReservationModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie si un utilisateur a déjà une réservation
     */
    public function hasActiveReservation(int $userId, int $trajetId): bool
    {
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM reservations WHERE utilisateur_id = ? AND covoiturage_id = ? AND statut NOT IN ('annulée', 'refusée')");
        $stmtCheck->execute([$userId, $trajetId]);
        return $stmtCheck->fetchColumn() > 0;
    }

    /**
     * Crée une nouvelle réservation
     */
    public function create(int $userId, int $trajetId, string $statut = 'en_attente'): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO reservations (utilisateur_id, covoiturage_id, date_reservation, statut) VALUES (?, ?, NOW(), ?)");
        return $stmt->execute([$userId, $trajetId, $statut]);
    }

    /**
     * Met à jour le statut d'une réservation (confirmer/refuser)
     */
    public function updateStatus(int $reservationId, string $statut): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $reservationId]);
    }


    /**
     * Trouve une réservation et vérifie qu'elle appartient au chauffeur
     */
    public function findReservationForChauffeur(int $reservationId, int $chauffeurId)
    {
        $sql = "SELECT r.utilisateur_id, c.depart, c.arrivee
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND c.chauffeur_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reservationId, $chauffeurId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Trouve une réservation pour un passager (pour annulation)
     */
    public function findReservationForPassager(int $reservationId, int $passagerId)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.covoiturage_id, c.prix, c.statut AS trajet_statut 
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            WHERE r.id = ? AND r.utilisateur_id = ? AND r.statut = 'confirmée'
        ");
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les passagers (confirmés) d'un trajet (pour remboursement/notification)
     */
    public function getConfirmedPassagers(int $trajetId)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.utilisateur_id, u.email, u.pseudo 
            FROM reservations r
            JOIN utilisateurs u ON r.utilisateur_id = u.id
            WHERE r.covoiturage_id = ? AND r.statut = 'confirmée'
        ");
        $stmt->execute([$trajetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les passagers (tous statuts) d'un trajet (pour page détail)
     */
    public function getPassagersByTrajet(int $trajetId)
    {
        $sqlPassagers = "SELECT u.id, u.pseudo, r.statut 
                         FROM reservations r 
                         JOIN utilisateurs u ON r.utilisateur_id = u.id 
                         WHERE r.covoiturage_id = ?";

        try {
            $stmtPassagers = $this->pdo->prepare($sqlPassagers);
            $stmtPassagers->execute([$trajetId]);
            return $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur dans ReservationModel::getPassagersByTrajet : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère une réservation pour validation par un passager.
     * [cite_start]Vérifie que le passager est correct, que la résa est 'confirmée' et le trajet 'terminé'.
     */
    public function findForValidation(int $reservationId, int $passagerId)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.utilisateur_id, r.covoiturage_id, c.chauffeur_id, c.prix
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            WHERE r.id = ? AND r.utilisateur_id = ? AND r.statut = 'confirmée' AND c.statut = 'terminé'
            FOR UPDATE 
        "); // FOR UPDATE pour la transaction
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une réservation pour un signalement d'incident.
     * [cite_start]Vérifie que le passager est correct et que la résa est 'confirmée'.
     */
    public function findForIncidentReport(int $reservationId, int $passagerId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT id 
             FROM reservations 
             WHERE id = ? AND utilisateur_id = ? AND statut = 'confirmée'
             FOR UPDATE"
        );
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les trajets réservés par un passager.
     */
    public function getTrajetsReservesByUserId(int $passagerId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.id, c.depart, c.arrivee, c.date_depart, 
                c.statut AS trajet_statut,
                u.pseudo AS chauffeur_pseudo,
                r.id AS reservation_id,
                r.statut AS reservation_statut
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            JOIN utilisateurs u ON c.chauffeur_id = u.id
            WHERE r.utilisateur_id = ?
            ORDER BY c.date_depart DESC
        ");
        $stmt->execute([$passagerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les réservations en attente pour un chauffeur.
     */
    public function getPendingReservationsForChauffeur(int $chauffeurId)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.statut, u.pseudo AS passager_pseudo, 
                   c.depart, c.arrivee, c.date_depart
            FROM reservations r
            JOIN utilisateurs u ON r.utilisateur_id = u.id
            JOIN covoiturages c ON r.covoiturage_id = c.id
            WHERE c.chauffeur_id = ? AND r.statut = 'en_attente'
            ORDER BY r.date_reservation ASC
        ");
        $stmt->execute([$chauffeurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statuts des réservations d'un utilisateur.
     * (Optimisé pour la page de recherche)
     */
    public function getUserReservationsStatus(int $userId)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT covoiturage_id, statut 
                 FROM reservations 
                 WHERE utilisateur_id = ?"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            error_log("Erreur ReservationModel::getUserReservationsStatus : " . $e->getMessage());
            return [];
        }
    }
}
