<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class TrajetModel
 *
 * Ce Modèle est responsable de la gestion de l'entité `covoiturages` dans la base de données.
 * Il centralise toutes les opérations CRUD (Création, Lecture, Mise à jour, Suppression) liées aux trajets.
 *
 * Points techniques clés :
 * - Utilisation de JOINTURES pour récupérer les données liées (Chauffeur, Véhicule).
 * - Utilisation de transactions et de VERROUILLAGE (FOR UPDATE) pour éviter les conflits de réservation.
 * - Mises à jour ATOMIQUES des places disponibles.
 */
class TrajetModel
{
    /**
     * @var PDO Instance de connexion à la base de données (injectée).
     */
    private $pdo;

    /**
     * Constructeur.
     * On utilise l'injection de dépendance pour récupérer l'instance PDO unique.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouveau trajet (Covoiturage).
     *
     * @param int $chauffeurId ID de l'utilisateur qui conduit.
     * @param int $vehiculeId ID du véhicule utilisé.
     * @param string $depart Ville de départ.
     * @param string $arrivee Ville d'arrivée.
     * @param string $dateDepart Date et heure (format YYYY-MM-DD HH:MM:SS).
     * @param float $prix Prix en crédits.
     * @param int $places Nombre de places proposées.
     * @param int $estEcologique 1 si véhicule électrique/hybride, 0 sinon.
     * @return bool Succès de l'insertion.
     */
    public function create(int $chauffeurId, int $vehiculeId, string $depart, string $arrivee, string $dateDepart, float $prix, int $places, int $estEcologique): bool
    {
        // Le statut est forcé à 'planifié' à la création.
        // On utilise une requête préparée pour sécuriser l'insertion contre les injections SQL.
        $stmt = $this->pdo->prepare(
            "INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, prix, places_disponibles, est_ecologique, statut) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planifié')"
        );
        return $stmt->execute([$chauffeurId, $vehiculeId, $depart, $arrivee, $dateDepart, $prix, $places, $estEcologique]);
    }

    /**
     * Récupère les détails COMPLETS d'un trajet pour l'affichage (Page Détail).
     *
     * Cette méthode effectue des JOINTURES (JOIN) pour éviter de faire 3 requêtes séparées.
     * En une seule requête, on récupère :
     * 1. Les infos du trajet (table covoiturages).
     * 2. Le pseudo du chauffeur (table utilisateurs).
     * 3. La marque et le modèle de la voiture (table vehicules).
     *
     * @param int $trajetId
     * @return mixed Tableau associatif des données ou false si non trouvé.
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
     * Récupère un trajet avec VERROUILLAGE de la ligne (Pessimistic Locking).
     *
     * C'est une méthode CRITIQUE pour la méthode `TrajetController::participerTrajet`.
     * L'instruction `FOR UPDATE` dit à la base de données :
     * "Je suis en train de lire cette ligne pour la modifier. Bloque-la pour tout le monde
     * tant que je n'ai pas fini ma transaction."
     *
     * Cela empêche que deux personnes réservent la dernière place exactement au même moment (Race Condition).
     *
     * @param int $trajetId
     * @return mixed
     */
    public function findByIdForUpdate(int $trajetId)
    {
        $stmtTrajet = $this->pdo->prepare(
            "SELECT id, prix, places_disponibles, chauffeur_id, statut, depart, arrivee 
             FROM covoiturages 
             WHERE id = ? 
             FOR UPDATE" // <--- Le verrouillage est ici
        );
        $stmtTrajet->execute([$trajetId]);
        return $stmtTrajet->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des passagers d'un trajet via la table de liaison.
     */
    public function getPassagers(int $trajetId)
    {
        $sqlPassagers = "SELECT u.id, u.pseudo 
                         FROM reservations r 
                         JOIN utilisateurs u ON r.utilisateur_id = u.id 
                         WHERE r.covoiturage_id = ?";
        $stmtPassagers = $this->pdo->prepare($sqlPassagers);
        $stmtPassagers->execute([$trajetId]);
        return $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Change le statut d'un trajet de manière CONDITIONNELLE.
     *
     * Exemple : Passer de 'planifié' à 'en_cours'.
     * La condition `AND statut = ?` est une sécurité métier (State Machine) :
     * On ne peut pas "Démarrer" un trajet qui est déjà "Annulé" ou "Terminé".
     *
     * @param int $trajetId
     * @param string $newStatus Le statut cible.
     * @param string $conditionStatus Le statut actuel requis pour autoriser le changement.
     * @return int Le nombre de lignes modifiées (0 si la condition n'est pas respectée).
     */
    public function updateStatusConditional(int $trajetId, string $newStatus, string $conditionStatus): int
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET statut = ? WHERE id = ? AND statut = ?");
        $stmt->execute([$newStatus, $trajetId, $conditionStatus]);
        return $stmt->rowCount(); // Si 0, c'est que le statut actuel n'était pas bon.
    }

    /**
     * Change le statut d'un trajet sans condition (ex: Annulation forcée).
     * Utilisé pour le "Soft Delete" (on ne supprime pas la ligne, on la marque 'annulée').
     */
    public function updateStatus(int $trajetId, string $newStatus): int
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatus, $trajetId]);
        return $stmt->rowCount();
    }

    /**
     * Décrémente le nombre de places disponibles de manière ATOMIQUE.
     *
     * Au lieu de faire :
     * 1. Lire le nombre de places (ex: 3) en PHP.
     * 2. Calculer 3 - 1 = 2.
     * 3. Envoyer UPDATE ... SET places = 2.
     *
     * On fait directement :
     * UPDATE ... SET places = places - 1.
     *
     * C'est plus sûr (thread-safe) et plus rapide car tout se passe dans le moteur SQL.
     */
    public function decrementPlaces(int $trajetId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
        return $stmt->execute([$trajetId]);
    }

    /**
     * Incrémente le nombre de places (ex: après une annulation de réservation).
     */
    public function incrementPlaces(int $trajetId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles + 1 WHERE id = ?");
        return $stmt->execute([$trajetId]);
    }

    /**
     * Récupère tous les trajets proposés par un chauffeur spécifique.
     * Utilisé pour l'onglet "Mes trajets proposés" dans le profil.
     */
    public function findByChauffeurId(int $chauffeurId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM covoiturages 
             WHERE chauffeur_id = ? 
             ORDER BY date_depart DESC" // Les plus récents en premier
        );
        $stmt->execute([$chauffeurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
