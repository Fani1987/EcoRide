<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class CovoiturageModel
 *
 * Ce Modèle est responsable de l'accès aux données des trajets (covoiturages).
 * Il fait partie de la couche "M" (Modèle) du MVC.
 *
 * Rôle principal :
 * - Construire des requêtes SQL complexes pour la recherche.
 * - Faire le lien (JOINTURES) entre les tables covoiturages, utilisateurs (chauffeurs) et véhicules.
 */
class CovoiturageModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     */
    private $pdo;

    /**
     * Constructeur.
     * On utilise l'injection de dépendance : le contrôleur fournit la connexion PDO ouverte.
     * Cela évite de réouvrir une connexion à chaque fois.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste des covoiturages correspondant aux filtres de recherche.
     *
     * Cette méthode construit dynamiquement une requête SQL en fonction des critères
     * remplis par l'utilisateur (Départ, Arrivée, Prix max, etc.).
     *
     * @param array $filters Tableau associatif des filtres (venant du $_GET).
     * @return array La liste des trajets trouvés.
     */
    public function getFiltered(array $filters = [])
    {
        // 1. REQUÊTE DE BASE (Squelette)
        // On sélectionne toutes les infos du trajet (c.*).
        // On fait des JOINTURES (JOIN) pour récupérer en même temps :
        // - Le pseudo et la note du chauffeur (table utilisateurs).
        // - La marque et le modèle de la voiture (table vehicules).
        //
        // Condition de base (WHERE) :
        // - Le trajet doit être 'planifié' (pas annulé ou terminé).
        // - Il doit rester des places (> 0).
        $sql = "SELECT c.*, u.id AS chauffeur_id, u.pseudo AS chauffeur_pseudo, u.note_moyenne AS chauffeur_note, 
                       v.marque AS vehicule_marque, v.modele AS vehicule_modele, v.energie AS vehicule_energie
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE c.statut = 'planifié' AND c.places_disponibles > 0";

        // Tableau qui contiendra les valeurs à insérer dans les '?' (sécurité)
        $params = [];

        // 2. CONSTRUCTION DYNAMIQUE DE LA REQUÊTE
        // On ajoute des conditions "AND ..." seulement si le filtre est rempli.

        // Filtre : Ville de départ
        if (!empty($filters['depart'])) {
            // On utilise LOWER() pour rendre la recherche insensible à la casse (Paris = paris).
            $sql .= " AND LOWER(c.depart) LIKE LOWER(?)";
            // On n'utilise PAS htmlspecialchars ici. C'est une requête SQL préparée.
            // Les données doivent être brutes pour correspondre à la BDD.
            $params[] = '%' . $filters['depart'] . '%'; // % pour le "contient"
        }

        // Filtre : Ville d'arrivée
        if (!empty($filters['arrivee'])) {
            $sql .= " AND LOWER(c.arrivee) LIKE LOWER(?)";
            $params[] = '%' . $filters['arrivee'] . '%';
        }

        // Filtre : Date précise
        if (!empty($filters['date'])) {
            // On compare uniquement la partie DATE (sans l'heure)
            $sql .= " AND DATE(c.date_depart) = ?";
            $params[] = $filters['date'];
        }

        // Filtre : Écologique (Checkbox)
        if (isset($filters['ecologique']) && $filters['ecologique'] == 1) {
            $sql .= " AND c.est_ecologique = 1";
        }

        // Filtre : Prix Maximum
        if (isset($filters['prix_max']) && is_numeric($filters['prix_max'])) {
            $sql .= " AND c.prix <= ?";
            $params[] = $filters['prix_max'];
        }

        // Filtre : Durée Maximum
        if (isset($filters['duree_max']) && is_numeric($filters['duree_max'])) {
            // La durée est stockée en texte (ex: "2h30"), mais si stockée en minutes/entier,
            // la comparaison numérique fonctionne mieux. Ici on suppose un format compatible.
            $sql .= " AND c.duree <= ?";
            $params[] = $filters['duree_max'];
        }

        // Filtre : Note minimale du chauffeur
        if (isset($filters['note_min']) && is_numeric($filters['note_min'])) {
            $sql .= " AND u.note_moyenne >= ?";
            $params[] = $filters['note_min'];
        }

        // 3. TRI DES RÉSULTATS
        // On affiche les départs les plus proches en premier.
        $sql .= " ORDER BY c.date_depart ASC";

        try {
            // 4. PRÉPARATION ET EXÉCUTION
            // On prépare la requête construite dynamiquement.
            $stmt = $this->pdo->prepare($sql);

            // On exécute en passant le tableau $params qui contient les valeurs dans le bon ordre.
            $stmt->execute($params);

            // On retourne un tableau associatif contenant tous les résultats.
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Gestion d'erreur silencieuse pour l'utilisateur, mais loguée pour le dév.
            error_log("Erreur dans CovoiturageModel::getFiltered : " . $e->getMessage());
            return []; // On retourne un tableau vide pour ne pas faire planter la Vue (foreach).
        }
    }

    /**
     * Amélioration UX : Trouve la prochaine date disponible si aucun trajet n'est trouvé aujourd'hui.
     *
     * @param string $depart Ville de départ
     * @param string $arrivee Ville d'arrivée
     * @return string|null La date (AAAA-MM-JJ) ou null si rien trouvé.
     */
    public function getNextAvailableDate(string $depart, string $arrivee)
    {
        try {
            // On cherche la date minimale (MIN) supérieure à maintenant (NOW).
            $sqlNextDate = "SELECT MIN(date_depart) AS prochaine_date 
                            FROM covoiturages 
                            WHERE LOWER(depart) LIKE LOWER(?) 
                              AND LOWER(arrivee) LIKE LOWER(?)
                              AND date_depart > NOW()"; // Uniquement les trajets futurs

            $stmtNextDate = $this->pdo->prepare($sqlNextDate);
            $stmtNextDate->execute(['%' . $depart . '%', '%' . $arrivee . '%']);
            $result = $stmtNextDate->fetch(PDO::FETCH_ASSOC);

            // Retourne la date ou null
            return $result['prochaine_date'] ?? null;
        } catch (PDOException $e) {
            error_log("Erreur dans CovoiturageModel::getNextAvailableDate : " . $e->getMessage());
            return null;
        }
    }
}
