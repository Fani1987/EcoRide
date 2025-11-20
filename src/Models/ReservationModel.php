<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class ReservationModel
 *
 * Ce Modèle gère la table de liaison `reservations`.
 * C'est l'entité centrale qui relie un Utilisateur (Passager) à un Covoiturage (Trajet).
 *
 * Rôle :
 * - Créer une réservation (demande).
 * - Gérer le cycle de vie (En attente -> Confirmée -> Validée/Annulée).
 * - Récupérer les listes pour les tableaux de bord (Passager et Chauffeur).
 */
class ReservationModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     */
    private $pdo;

    /**
     * Constructeur avec Injection de Dépendance.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie si un utilisateur a déjà une réservation active pour un trajet donné.
     * Empêche les doublons (réserver 2 fois le même trajet).
     *
     * @param int $userId L'ID du passager.
     * @param int $trajetId L'ID du trajet.
     * @return bool Vrai s'il existe une réservation non annulée/refusée.
     */
    public function hasActiveReservation(int $userId, int $trajetId): bool
    {
        // On compte les réservations qui ne sont NI annulées NI refusées.
        $stmtCheck = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM reservations 
            WHERE utilisateur_id = ? 
              AND covoiturage_id = ? 
              AND statut NOT IN ('annulée', 'refusée')
        ");
        $stmtCheck->execute([$userId, $trajetId]);
        return $stmtCheck->fetchColumn() > 0;
    }

    /**
     * Crée une nouvelle réservation.
     * Appelée lors du clic sur "Réserver" (après débit des crédits).
     *
     * @param int $userId
     * @param int $trajetId
     * @param string $statut Par défaut 'en_attente' (doit être validée par le chauffeur).
     * @return bool Succès.
     */
    public function create(int $userId, int $trajetId, string $statut = 'en_attente'): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reservations (utilisateur_id, covoiturage_id, date_reservation, statut) 
             VALUES (?, ?, NOW(), ?)"
        );
        return $stmt->execute([$userId, $trajetId, $statut]);
    }

    /**
     * Met à jour le statut d'une réservation.
     * Utilisé pour :
     * - Confirmation/Refus par le chauffeur.
     * - Annulation par le passager.
     * - Validation finale après trajet.
     */
    public function updateStatus(int $reservationId, string $statut): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $reservationId]);
    }

    /**
     * Trouve une réservation spécifique pour la validation de fin de trajet.
     *
     * C'est une méthode CRITIQUE pour la transaction de paiement.
     * Elle utilise une JOINTURE pour vérifier le statut du TRAJET associé.
     *
     * @param int $reservationId
     * @param int $passagerId
     * @return array|false Les données de la réservation (avec prix et id chauffeur) ou false.
     */
    public function findForValidation(int $reservationId, int $passagerId)
    {
        // SELECT ... FOR UPDATE : Verrouille la ligne pour éviter les accès concurrents pendant la transaction.
        $query = "
            SELECT r.*, c.prix, c.chauffeur_id, c.statut as trajet_statut
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            WHERE r.id = ? 
              AND r.utilisateur_id = ?
              AND r.statut = 'confirmée' -- On ne peut valider qu'une résa confirmée
              AND c.statut = 'terminé'   -- Le trajet doit être marqué comme fini par le chauffeur
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une réservation pour un chauffeur afin qu'il puisse la confirmer/refuser.
     * Vérifie que le chauffeur est bien le propriétaire du trajet lié à la réservation.
     */
    public function findReservationForChauffeur(int $reservationId, int $chauffeurId)
    {
        $sql = "SELECT r.* FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND c.chauffeur_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reservationId, $chauffeurId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une réservation pour un passager afin qu'il puisse l'annuler.
     * Récupère aussi le statut du trajet pour vérifier si l'annulation est encore possible.
     */
    public function findReservationForPassager(int $reservationId, int $passagerId)
    {
        $sql = "SELECT r.*, c.statut as trajet_statut, c.prix, c.covoiturage_id 
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND r.utilisateur_id = ?";
        // Note: c.covoiturage_id n'existe pas, c'est c.id, corrigé implicitement dans la logique
        // Correction SQL :
        $sql = "SELECT r.*, c.statut as trajet_statut, c.prix, c.id as covoiturage_id
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND r.utilisateur_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Trouve une réservation pour signaler un incident.
     * Similaire à findForValidation, mais le trajet n'a pas besoin d'être terminé.
     */
    public function findForIncidentReport(int $reservationId, int $passagerId)
    {
        $query = "
            SELECT r.* FROM reservations r
            WHERE r.id = ? 
              AND r.utilisateur_id = ?
              AND r.statut = 'confirmée' -- Seul un trajet confirmé peut avoir un incident
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$reservationId, $passagerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des passagers ayant une réservation CONFIRMÉE pour un trajet.
     * Utile pour envoyer les notifications de fin de trajet ou pour les remboursements en cas d'annulation.
     */
    public function getConfirmedPassagers(int $trajetId)
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id as utilisateur_id, u.email, u.pseudo, r.id
            FROM reservations r
            JOIN utilisateurs u ON r.utilisateur_id = u.id
            WHERE r.covoiturage_id = ? AND r.statut = 'confirmée'
        ");
        $stmt->execute([$trajetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des passagers pour un trajet donné (pour l'affichage détail trajet).
     * Affiche le pseudo des gens inscrits.
     */
    public function getPassagersByTrajet(int $trajetId)
    {
        $sqlPassagers = "SELECT u.id, u.pseudo 
                         FROM reservations r 
                         JOIN utilisateurs u ON r.utilisateur_id = u.id 
                         WHERE r.covoiturage_id = ? AND r.statut IN ('confirmée', 'validée')";
        $stmtPassagers = $this->pdo->prepare($sqlPassagers);
        $stmtPassagers->execute([$trajetId]);
        return $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'historique complet des trajets réservés par un passager.
     * Affiche les détails du trajet (Départ/Arrivée/Date) et le statut de la réservation.
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
     * Récupère les réservations "En attente" pour un chauffeur donné.
     * Permet au chauffeur de voir qui veut monter dans sa voiture et d'accepter/refuser.
     *
     * La requête est complexe car on part du chauffeur -> ses trajets -> les réservations sur ces trajets.
     */
    public function getPendingReservationsForChauffeur(int $chauffeurId)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.statut, u.pseudo AS passager_pseudo, 
                   c.depart, c.arrivee, c.date_depart
            FROM reservations r
            JOIN utilisateurs u ON r.utilisateur_id = u.id -- Infos du passager
            JOIN covoiturages c ON r.covoiturage_id = c.id -- Infos du trajet
            WHERE c.chauffeur_id = ? -- On filtre sur les trajets de CE chauffeur
              AND r.statut = 'en_attente'
            ORDER BY r.date_reservation ASC
        ");
        $stmt->execute([$chauffeurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statuts des réservations d'un utilisateur sous forme de tableau simple.
     * Optimisé pour la page de recherche afin de griser les boutons "Réserver".
     *
     * @return array Format: [id_covoiturage => 'statut'] (ex: [12 => 'confirmée'])
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
            // FETCH_KEY_PAIR est une astuce PDO géniale :
            // Elle prend la 1ère colonne comme CLÉ et la 2ème comme VALEUR.
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            return [];
        }
    }
}
