<?php

namespace App\Models;

use PDO;
use PDOException;

class IncidentModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Met à jour le statut d'un incident (ex: 'fermé')
     */
    public function updateStatus(int $incidentId, string $statut): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE incidents SET statut = ? WHERE id = ?");
            return $stmt->execute([$statut, $incidentId]);
        } catch (PDOException $e) {
            error_log('Erreur IncidentModel::updateStatus : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée un nouvel incident.
     * Le statut est 'ouvert' par défaut.
     */
    public function create(int $reservationId, string $commentaire): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO incidents (reservation_id, commentaire, statut) 
                 VALUES (?, ?, 'ouvert')"
            );
            return $stmt->execute([$reservationId, $commentaire]);
        } catch (PDOException $e) {
            error_log('Erreur IncidentModel::create : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les incidents par statut ('ouvert', 'fermé').
     */
    public function getByStatus(string $statut)
    {
        try {
            $query = "
                SELECT 
                    i.id AS incident_id, i.reservation_id,
                    c.id AS covoiturage_id,
                    u_conducteur.pseudo AS conducteur_pseudo,
                    u_passager.pseudo AS passager_pseudo,
                    DATE_FORMAT(c.date_depart, '%d/%m/%Y') AS date_trajet,
                    CONCAT(c.depart, ' → ', c.arrivee) AS lieu,
                    i.commentaire AS description
                FROM incidents i
                JOIN reservations r ON i.reservation_id = r.id
                JOIN covoiturages c ON r.covoiturage_id = c.id
                JOIN utilisateurs u_conducteur ON c.chauffeur_id = u_conducteur.id
                JOIN utilisateurs u_passager ON r.utilisateur_id = u_passager.id
                WHERE i.statut = ?
                ORDER BY i.date_creation DESC
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$statut]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur IncidentModel::getByStatus : ' . $e->getMessage());
            return [];
        }
    }
}
