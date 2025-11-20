<?php

namespace App\Controllers;

use PDO;
// Import des Modèles nécessaires.
// Dans une architecture MVC, le Contrôleur "utilise" les Modèles pour accéder aux données.
use App\Models\CovoiturageModel;
use App\Models\ReservationModel;

/**
 * Class CovoiturageController
 * * Responsabilité : Gérer la page de recherche et d'affichage des covoiturages.
 * Rôle : Chef d'orchestre. Il reçoit les filtres de l'utilisateur (via l'URL),
 * interroge la base de données via les modèles, et envoie les résultats à la vue.
 */
class CovoiturageController
{
    /**
     * Affiche la page de recherche de covoiturages.
     * * Cette méthode est appelée par le routeur (index.php) lorsque l'URL est '/covoiturage'.
     * * @param PDO $pdo L'instance de connexion à la base de données (Injection de dépendance).
     * @param array $queryParams Les paramètres de l'URL ($_GET) contenant les filtres de recherche.
     */
    public static function showCovoituragePage(PDO $pdo, array $queryParams)
    {
        // ============================================================
        // 1. INITIALISATION DES MODÈLES
        // ============================================================
        // On instancie les modèles dont on a besoin pour cette page.
        // On leur passe la connexion $pdo pour qu'ils puissent faire des requêtes SQL.

        // Pour chercher les trajets
        $covoiturageModel = new CovoiturageModel($pdo);
        // Pour vérifier si l'utilisateur a déjà réservé (et griser les boutons)
        $reservationModel = new ReservationModel($pdo);

        // ============================================================
        // 2. PRÉPARATION DES FILTRES (Nettoyage)
        // ============================================================
        // On récupère les données du formulaire de recherche (GET).
        // L'opérateur '??' (Null Coalescing) permet de définir une valeur par défaut (vide)
        // si le paramètre n'existe pas, évitant les erreurs "Undefined index".

        $filters = [
            'depart'      => $queryParams['depart'] ?? '',
            'arrivee'     => $queryParams['arrivee'] ?? '',
            'date'        => $queryParams['date'] ?? '',
            'ecologique'  => $queryParams['ecologique'] ?? 0, // 0 ou 1 (checkbox)
            'prix_max'    => $queryParams['prix_max'] ?? '',
            'duree_max'   => $queryParams['duree_max'] ?? '',
            'note_min'    => $queryParams['note_min'] ?? '',
        ];

        // ============================================================
        // 3. RÉCUPÉRATION DES DONNÉES (Appel au Modèle)
        // ============================================================
        // Le contrôleur ne contient PAS de SQL. Il demande simplement au modèle :
        // "Donne-moi les trajets qui correspondent à ces filtres".

        $covoiturages = $covoiturageModel->getFiltered($filters);

        // ============================================================
        // 4. LOGIQUE MÉTIER UX (Suggestion de date)
        // ============================================================
        // Si l'utilisateur cherche un trajet précis (Départ + Arrivée) mais qu'il n'y a 
        // AUCUN résultat ($covoiturages est vide), on essaie d'être intelligent.
        // On demande au modèle : "Y a-t-il une prochaine date disponible pour ce trajet ?"

        $prochaine_date = null;
        if (empty($covoiturages) && !empty($filters['depart']) && !empty($filters['arrivee'])) {
            // Appel d'une méthode spécifique du modèle pour améliorer l'expérience utilisateur
            $prochaine_date = $covoiturageModel->getNextAvailableDate($filters['depart'], $filters['arrivee']);
        }

        // ============================================================
        // 5. LOGIQUE DE SESSION (Personnalisation)
        // ============================================================
        // On doit savoir si l'utilisateur connecté a déjà réservé certains des trajets affichés
        // pour pouvoir désactiver le bouton "Réserver" ou afficher "Déjà réservé".

        $mes_reservations = [];
        if (isset($_SESSION['user_id'])) {
            // On appelle le modèle Reservation pour obtenir la liste des statuts 
            // des réservations de l'utilisateur courant.
            // Retourne un tableau du type : [ ID_TRAJET => 'confirmée', ID_TRAJET_2 => 'en_attente' ]
            $mes_reservations = $reservationModel->getUserReservationsStatus($_SESSION['user_id']);
        }

        // ============================================================
        // 6. PRÉPARATION DE LA VUE
        // ============================================================
        // On regroupe toutes les données (Résultats, Filtres pour pré-remplir le formulaire, UX, Session)
        // dans un tableau associatif à passer à la vue.

        $data = [
            'covoiturages'     => $covoiturages,     // Les résultats de recherche
            'filters'          => $filters,          // Pour garder les champs du formulaire remplis
            'prochaine_date'   => $prochaine_date,   // Pour afficher le message "Essayez le..."
            'mes_reservations' => $mes_reservations  // Pour gérer l'état des boutons
        ];

        // 7. AFFICHAGE
        // On appelle la fonction helper renderView qui va charger 'views/covoiturage.php'
        // et lui transmettre les données $data.
        \renderView('covoiturage', $data);
    }
}
