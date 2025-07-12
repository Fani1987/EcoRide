<?php

namespace App\Controllers;

use PDO;
use PDOException;

class CovoiturageController
{

    /**
     * Récupère les covoiturages filtrés de la base de données.
     * Cette méthode est conçue pour être appelée par showCovoituragePage ou une API AJAX.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $filters Tableau associatif des critères de filtre (ex: 'depart', 'arrivee', 'date').
     * @return array Un tableau de covoiturages correspondants.
     */
    public static function getFilteredCovoiturages($pdo, $filters = [])
    {
        $sql = "SELECT c.*, u.id AS chauffeur_id, u.pseudo AS chauffeur_pseudo, u.note_moyenne AS chauffeur_note, v.marque AS vehicule_marque, v.modele AS vehicule_modele, v.energie AS vehicule_energie
        FROM covoiturages c
        JOIN utilisateurs u ON c.chauffeur_id = u.id
        JOIN vehicules v ON c.vehicule_id = v.id
        WHERE c.statut = 'planifié' AND c.places_disponibles > 0";

        $params = [];

        // Appliquer les filtres
        if (!empty($filters['depart'])) {
            $sql .= " AND LOWER(c.depart) LIKE LOWER(?)";
            $params[] = '%' . htmlspecialchars($filters['depart']) . '%';
        }
        if (!empty($filters['arrivee'])) {
            $sql .= " AND LOWER(c.arrivee) LIKE LOWER(?)";
            $params[] = '%' . htmlspecialchars($filters['arrivee']) . '%';
        }
        if (!empty($filters['date'])) {
            $sql .= " AND DATE(c.date_depart) = ?";
            $params[] = htmlspecialchars($filters['date']);
        }
        if (isset($filters['ecologique']) && $filters['ecologique'] == 1) {
            $sql .= " AND c.est_ecologique = 1";
        }
        if (isset($filters['prix_min']) && is_numeric($filters['prix_min'])) {
            $sql .= " AND c.prix >= ?";
            $params[] = $filters['prix_min'];
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
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des covoiturages filtrés : " . $e->getMessage());
            return []; // Retourne un tableau vide en cas d'erreur
        }
    }

    /**
     * Affiche la page de recherche de covoiturages en appliquant les filtres passés en paramètres GET.
     * Cette méthode sert de point d'entrée pour la page de covoiturage.html
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $queryParams Les paramètres GET de l'URL.
     */
    public static function showCovoituragePage($pdo, $queryParams)
    {
        $mes_reservations = [];
        if (isset($_SESSION['user_id'])) {
            $stmtMesReservations = $pdo->prepare("SELECT covoiturage_id, statut FROM reservations WHERE utilisateur_id = ?");
            $stmtMesReservations->execute([$_SESSION['user_id']]);
            $mes_reservations = $stmtMesReservations->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $filters = [
            'depart' => $queryParams['depart'] ?? '',
            'arrivee' => $queryParams['arrivee'] ?? '',
            'date' => $queryParams['date'] ?? '',
            'ecologique' => $queryParams['ecologique'] ?? 0,
            'prix_max' => $queryParams['prix_max'] ?? '',
            'duree_max' => $queryParams['duree_max'] ?? '',
            'note_min' => $queryParams['note_min'] ?? '',
        ];

        $covoiturages = self::getFilteredCovoiturages($pdo, $filters);

        $prochaine_date = null;
        if (empty($covoiturages) && !empty($filters['depart']) && !empty($filters['arrivee'])) {
            try {
                $sqlNextDate = "SELECT MIN(date_depart) AS prochaine_date 
                                FROM covoiturages 
                                WHERE LOWER(depart) LIKE LOWER(?) 
                                  AND LOWER(arrivee) LIKE LOWER(?)
                                  AND date_depart > NOW()";

                $stmtNextDate = $pdo->prepare($sqlNextDate);
                $stmtNextDate->execute(['%' . $filters['depart'] . '%', '%' . $filters['arrivee'] . '%']);
                $result = $stmtNextDate->fetch(PDO::FETCH_ASSOC);

                if ($result && $result['prochaine_date']) {
                    $prochaine_date = $result['prochaine_date'];
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la recherche de la prochaine date : " . $e->getMessage());
            }
        }

        $data = [
            'covoiturages' => $covoiturages,
            'filters' => $filters,
            'prochaine_date' => $prochaine_date,
            'mes_reservations' => $mes_reservations
        ];

        \renderView('covoiturage', $data);
    }
}
