<?php

class CovoiturageController
{

    /**
     * Récupère tous les covoiturages ou des covoiturages filtrés.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $filters Tableau associatif des filtres (ex: ['typeTrajet' => 'ecologique', 'prixMax' => 20]).
     * @return array Tableau des covoiturages trouvés.
     */
    public static function getFilteredCovoiturages($pdo, $filters = [])
    {
        $type = $filters['typeTrajet'] ?? '';
        $prixMax = $filters['prixMax'] ?? '';
        $dureeMax = $filters['dureeMax'] ?? '';
        $noteMin = $filters['noteMin'] ?? '';
        $depart = $filters['depart'] ?? ''; // Ajout des champs de recherche de home.html
        $arrivee = $filters['arrivee'] ?? '';
        $date_recherche = $filters['date'] ?? '';


        // Construction de la requête SQL
        $sql = "SELECT
                    c.id,
                    c.depart,
                    c.arrivee,
                    c.date_depart,
                    c.prix,
                    c.places_disponibles,
                    c.est_ecologique,
                    u.pseudo AS conducteur_pseudo,
                    u.note AS conducteur_note,
                    v.marque AS vehicule_marque,
                    v.modele AS vehicule_modele,
                    v.energie AS vehicule_energie
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE 1=1"; // Clause WHERE 1=1 pour faciliter l'ajout conditionnel de AND

        $params = [];

        // Filtres
        if (!empty($depart)) {
            $sql .= " AND c.depart LIKE ?";
            $params[] = '%' . $depart . '%';
        }
        if (!empty($arrivee)) {
            $sql .= " AND c.arrivee LIKE ?";
            $params[] = '%' . $arrivee . '%';
        }
        if (!empty($date_recherche)) {
            $sql .= " AND DATE(c.date_depart) = ?"; // Comparer uniquement la date
            $params[] = $date_recherche;
        }

        if ($type === 'ecologique') {
            $sql .= " AND c.est_ecologique = 1";
        } elseif ($type === 'standard') { // Utilisez elseif pour éviter les conflits si les deux étaient valides
            $sql .= " AND c.est_ecologique = 0";
        }

        if ($prixMax !== '') { // Use !== '' for strict empty check
            $sql .= " AND c.prix <= ?";
            $params[] = $prixMax;
        }

        // Pour la durée, il faut une colonne ou un calcul spécifique. Si 'duree_estimee' existe:
        // if ($dureeMax !== '') {
        //     $sql .= " AND c.duree_estimee <= ?"; // Assurez-vous d'avoir cette colonne ou une logique pour la calculer
        //     $params[] = $dureeMax;
        // }
        // Si vous calculiez la durée avec TIMESTAMPDIFF, cela pourrait ressembler à :
        // if ($dureeMax !== '') {
        //     $sql .= " AND TIMESTAMPDIFF(MINUTE, c.date_depart, c.date_arrivee) <= ?";
        //     $params[] = $dureeMax;
        // }
        // Si vous n'avez pas de colonne durée ou une logique pour la calculer, vous pouvez ignorer ce filtre pour l'instant.

        if ($noteMin !== '') {
            $sql .= " AND u.note >= ?";
            $params[] = $noteMin;
        }

        $sql .= " ORDER BY c.date_depart ASC"; // Ordonner les résultats, par exemple par date

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retourne les résultats
        } catch (PDOException $e) {
            // En cas d'erreur, on peut logguer l'erreur et retourner un tableau vide
            error_log("Erreur de base de données lors de la récupération des covoiturages: " . $e->getMessage());
            // Si vous utilisez un système de message flash, vous pouvez le décommenter :
            // $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors du chargement des covoiturages.'];
            return []; // Retourne un tableau vide en cas d'erreur
        }
    }

    /**
     * Affiche la vue des covoiturages avec les filtres appliqués.
     * Cette méthode sera appelée par index.php pour la route '/covoiturage'.
     * Elle gère à la fois les requêtes GET pour l'affichage initial et le filtrage.
     *
     * @param PDO $pdo L'objet PDO.
     * @param array $queryParams Les paramètres GET du formulaire de recherche/filtre.
     */
    public static function showCovoituragePage($pdo, $queryParams)
    {
        // Récupérer les covoiturages en utilisant la méthode de filtrage
        $covoiturages = self::getFilteredCovoiturages($pdo, $queryParams);

        // Passer les covoiturages à la vue
        // Assurez-vous que la fonction renderView est définie et accessible globalement (par exemple, dans index.php)
        if (function_exists('renderView')) {
            renderView('covoiturage', ['covoiturages' => $covoiturages]);
        } else {
            error_log("Erreur: La fonction renderView() n'est pas définie dans CovoiturageController::showCovoituragePage.");
            // Gérer l'absence de renderView, par exemple en affichant un message d'erreur
            echo "Erreur interne: Impossible de charger la page. (renderView non définie)";
        }
    }
}
