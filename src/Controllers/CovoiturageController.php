<?php

namespace App\Controllers;

use PDO;
use App\Models\CovoiturageModel;
use App\Models\ReservationModel; // <-- 1. IMPORTER le ReservationModel

class CovoiturageController
{
    /**
     * Affiche la page de recherche de covoiturages.
     * Le contrôleur ne fait plus de SQL, il coordonne.
     */
    public static function showCovoituragePage(PDO $pdo, array $queryParams)
    {
        // 1. Instancier les Modèles en leur passant la connexion PDO
        $covoiturageModel = new CovoiturageModel($pdo);
        $reservationModel = new ReservationModel($pdo); // <-- 2. INSTANCIER le ReservationModel

        // 2. Préparer les filtres pour le modèle
        $filters = [
            'depart' => $queryParams['depart'] ?? '',
            'arrivee' => $queryParams['arrivee'] ?? '',
            'date' => $queryParams['date'] ?? '',
            'ecologique' => $queryParams['ecologique'] ?? 0,
            'prix_max' => $queryParams['prix_max'] ?? '',
            'duree_max' => $queryParams['duree_max'] ?? '',
            'note_min' => $queryParams['note_min'] ?? '',
        ];

        // 3. Demander les données au Modèle
        $covoiturages = $covoiturageModel->getFiltered($filters);

        // 4. Gérer la logique "métier" (que faire si la recherche est vide)
        $prochaine_date = null;
        if (empty($covoiturages) && !empty($filters['depart']) && !empty($filters['arrivee'])) {
            // On demande au modèle de faire la recherche
            $prochaine_date = $covoiturageModel->getNextAvailableDate($filters['depart'], $filters['arrivee']);
        }

        // 5. Gérer la logique de SESSION (données propres à l'utilisateur)
        $mes_reservations = [];
        if (isset($_SESSION['user_id'])) {
            // <-- 3. MODIFICATION : On appelle le modèle au lieu de faire du SQL
            $mes_reservations = $reservationModel->getUserReservationsStatus($_SESSION['user_id']);
        }

        // 6. Préparer les données pour la VUE
        $data = [
            'covoiturages' => $covoiturages,
            'filters' => $filters,
            'prochaine_date' => $prochaine_date,
            'mes_reservations' => $mes_reservations
        ];

        // 7. Appeler la VUE
        \renderView('covoiturage', $data);
    }
}
