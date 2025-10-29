<?php

namespace App\Models;

use PDO;
use PDOException;

class TrajetModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouveau trajet
     */
    public function create(int $chauffeurId, int $vehiculeId, string $depart, string $arrivee, string $dateDepart, float $prix, int $places, int $estEcologique): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, prix, places_disponibles, est_ecologique, statut) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planifié')"
        );
        return $stmt->execute([$chauffeurId, $vehiculeId, $depart, $arrivee, $dateDepart, $prix, $places, $estEcologique]);
    }

    /**
     * Trouve un trajet complet par son ID pour la page de détail
     */
    public function findFullTrajetById(int $trajetId)
    {
        $sql = "SELECT c.*, u.pseudo AS chauffeur_pseudo, u.id AS chauffeur_id,
                       v.marque AS vehicule_marque, v.modele AS vehicule_modele,
                       v.couleur AS vehicule_couleur, v.energie AS vehicule_energie,
                       v.plaque_immatriculation AS vehicule_immatriculation
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE c.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$trajetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un trajet pour une mise à jour (verrouille la ligne)
     */
    public function findByIdForUpdate(int $trajetId)
    {
        $stmtTrajet = $this->pdo->prepare("SELECT id, prix, places_disponibles, chauffeur_id, statut, depart, arrivee FROM covoiturages WHERE id = ? FOR UPDATE");
        $stmtTrajet->execute([$trajetId]);
        return $stmtTrajet->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les passagers d'un trajet
     */
    public function getPassagers(int $trajetId)
    {
        $sqlPassagers = "SELECT u.id, u.pseudo FROM reservations r JOIN utilisateurs u ON r.utilisateur_id = u.id WHERE r.covoiturage_id = ?";
        $stmtPassagers = $this->pdo->prepare($sqlPassagers);
        $stmtPassagers->execute([$trajetId]);
        return $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Change le statut d'un trajet (ex: 'planifié' -> 'en_cours')
     */
    public function updateStatusConditional(int $trajetId, string $newStatus, string $conditionStatus): int
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET statut = ? WHERE id = ? AND statut = ?");
        $stmt->execute([$newStatus, $trajetId, $conditionStatus]);
        return $stmt->rowCount(); // Retourne le nombre de lignes affectées (1 ou 0)
    }

    /**
     * Change le statut d'un trajet (ex: 'planifié' -> 'annulé')
     */
    public function updateStatus(int $trajetId, string $newStatus): int
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatus, $trajetId]);
        return $stmt->rowCount();
    }

    /**
     * Réduit le nombre de places de 1
     */
    public function decrementPlaces(int $trajetId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
        return $stmt->execute([$trajetId]);
    }

    /**
     * Augmente le nombre de places de 1
     */
    public function incrementPlaces(int $trajetId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles + 1 WHERE id = ?");
        return $stmt->execute([$trajetId]);
    }

    /**
     * Récupère tous les trajets (covoiturages) proposés par un chauffeur.
     */
    public function findByChauffeurId(int $chauffeurId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM covoiturages 
             WHERE chauffeur_id = ? 
             ORDER BY date_depart DESC"
        );
        $stmt->execute([$chauffeurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
