<?php

namespace App\Models;

use PDO;
use PDOException;

class CovoiturageModel
{
    private $pdo;

    // Le constructeur reçoit la connexion PDO
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère les covoiturages filtrés. 
     * C'est la fonction qui était dans votre contrôleur.
     */
    public function getFiltered(array $filters = [])
    {
        // Le SQL est identique à celui de votre contrôleur
        $sql = "SELECT c.*, u.id AS chauffeur_id, u.pseudo AS chauffeur_pseudo, u.note_moyenne AS chauffeur_note, v.marque AS vehicule_marque, v.modele AS vehicule_modele, v.energie AS vehicule_energie
        FROM covoiturages c
        JOIN utilisateurs u ON c.chauffeur_id = u.id
        JOIN vehicules v ON c.vehicule_id = v.id
        WHERE c.statut = 'planifié' AND c.places_disponibles > 0";

        $params = [];

        // Application des filtres
        if (!empty($filters['depart'])) {
            $sql .= " AND LOWER(c.depart) LIKE LOWER(?)";
            $params[] = '%' . $filters['depart'] . '%';
        }
        if (!empty($filters['arrivee'])) {
            $sql .= " AND LOWER(c.arrivee) LIKE LOWER(?)";
            $params[] = '%' . $filters['arrivee'] . '%';
        }
        if (!empty($filters['date'])) {
            $sql .= " AND DATE(c.date_depart) = ?";
            $params[] = $filters['date'];
        }
        if (isset($filters['ecologique']) && $filters['ecologique'] == 1) {
            $sql .= " AND c.est_ecologique = 1";
        }
        if (isset($filters['prix_max']) && is_numeric($filters['prix_max'])) {
            $sql .= " AND c.prix <= ?";
            $params[] = $filters['prix_max'];
        }
        if (isset($filters['duree_max']) && is_numeric($filters['duree_max'])) {
            $sql .= " AND c.duree <= ?";
            $params[] = $filters['duree_max'];
        }
        if (isset($filters['note_min']) && is_numeric($filters['note_min'])) {
            $sql .= " AND u.note_moyenne >= ?";
            $params[] = $filters['note_min'];
        }

        $sql .= " ORDER BY c.date_depart ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur dans CovoiturageModel::getFiltered : " . $e->getMessage());
            return []; // Toujours retourner un tableau en cas d'erreur
        }
    }

    /**
     * Récupère la prochaine date disponible pour un trajet.
     * Cette logique était aussi dans votre contrôleur.
     */
    public function getNextAvailableDate(string $depart, string $arrivee)
    {
        try {
            $sqlNextDate = "SELECT MIN(date_depart) AS prochaine_date 
                            FROM covoiturages 
                            WHERE LOWER(depart) LIKE LOWER(?) 
                              AND LOWER(arrivee) LIKE LOWER(?)
                              AND date_depart > NOW()"; // S'assurer que la date est future

            $stmtNextDate = $this->pdo->prepare($sqlNextDate);
            $stmtNextDate->execute(['%' . $depart . '%', '%' . $arrivee . '%']);
            $result = $stmtNextDate->fetch(PDO::FETCH_ASSOC);

            return $result['prochaine_date'] ?? null;
        } catch (PDOException $e) {
            error_log("Erreur dans CovoiturageModel::getNextAvailableDate : " . $e->getMessage());
            return null;
        }
    }
}
