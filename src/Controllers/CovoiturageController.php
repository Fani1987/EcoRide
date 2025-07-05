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
        $sql = "SELECT c.*, u.pseudo AS chauffeur_pseudo, v.marque AS vehicule_marque, v.modele AS vehicule_modele
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id  -- CORRECTION : c.user_id -> c.chauffeur_id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE 1=1"; // Clause WHERE de base pour faciliter l'ajout de conditions

        $params = [];

        // Appliquer les filtres
        if (!empty($filters['depart'])) {
            $sql .= " AND c.depart LIKE ?";
            $params[] = '%' . htmlspecialchars($filters['depart']) . '%';
        }
        if (!empty($filters['arrivee'])) {
            $sql .= " AND c.arrivee LIKE ?";
            $params[] = '%' . htmlspecialchars($filters['arrivee']) . '%';
        }
        if (!empty($filters['date'])) {
            // Assurez-vous que le format de date correspond à celui de votre base de données (ex: YYYY-MM-DD)
            $sql .= " AND c.date_depart = ?";
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

        $sql .= " ORDER BY c.date_depart ASC, c.heure_depart ASC";

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
    public static function showCovoituragePage($pdo, $queryParams) {
        // Les $queryParams proviennent de $_GET dans index.php
        $filters = [
            'depart' => $queryParams['depart'] ?? '',
            'arrivee' => $queryParams['arrivee'] ?? '',
            'date' => $queryParams['date'] ?? '',
            'ecologique' => $queryParams['ecologique'] ?? 0,
            'prix_min' => $queryParams['prix_min'] ?? '',
            'prix_max' => $queryParams['prix_max'] ?? ''
        ];

        // Récupère les covoiturages filtrés
        $covoiturages = self::getFilteredCovoiturages($pdo, $filters);

        // Prépare les données à passer à la vue
        $data = [
            'covoiturages' => $covoiturages,
            'filters' => $filters // Pour pré-remplir les champs de filtre dans la vue
        ];

        // Rendre la vue 'covoiturage' (qui sera covoiturage.php)
        \renderView('covoiturage', $data);
    }
}