<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class IncidentModel
 *
 * Ce Modèle gère les litiges (incidents) signalés par les passagers.
 * Il fait partie du module "Modération" utilisé par les Employés.
 *
 * Ses responsabilités sont :
 * 1. Créer un incident lors d'un signalement.
 * 2. Mettre à jour son statut (traitement par un employé).
 * 3. Récupérer les incidents avec toutes les infos contextuelles (Qui ? Quand ? Quel trajet ?).
 */
class IncidentModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     */
    private $pdo;

    /**
     * Constructeur avec Injection de Dépendance.
     * On reçoit la connexion active pour effectuer nos requêtes.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Met à jour le statut d'un incident.
     * Utilisé par un employé pour marquer un incident comme "fermé" (traité).
     *
     * @param int $incidentId L'ID de l'incident à modifier.
     * @param string $statut Le nouveau statut (ex: 'fermé').
     * @return bool Succès de l'opération.
     */
    public function updateStatus(int $incidentId, string $statut): bool
    {
        try {
            // Requête préparée pour sécuriser la modification (UPDATE)
            $stmt = $this->pdo->prepare("UPDATE incidents SET statut = ? WHERE id = ?");

            // Exécution avec les paramètres liés
            return $stmt->execute([$statut, $incidentId]);
        } catch (PDOException $e) {
            // En cas d'erreur SQL, on loggue le problème pour le débogage
            error_log('Erreur IncidentModel::updateStatus : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée un nouvel incident dans la base de données.
     * Cette méthode est appelée par le AvisController::reportIncident.
     *
     * @param int $reservationId L'ID de la réservation qui pose problème.
     * @param string $commentaire La description du problème par le passager.
     * @return bool Succès de l'insertion.
     */
    public function create(int $reservationId, string $commentaire): bool
    {
        try {
            // On insère l'incident avec le statut 'ouvert' par défaut.
            // Cela garantit qu'il apparaîtra dans le tableau de bord des employés.
            $stmt = $this->pdo->prepare(
                "INSERT INTO incidents (reservation_id, commentaire, statut) 
                 VALUES (?, ?, 'ouvert')"
            );

            return $stmt->execute([$reservationId, $commentaire]);
        } catch (PDOException $e) {
            error_log('Erreur IncidentModel::create : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste des incidents filtrés par statut, avec les détails complets.
     *
     * C'est une méthode clé pour le Dashboard Employé.
     * Elle utilise des JOINTURES (JOIN) pour récupérer des informations dispersées
     * dans 4 tables différentes (incidents, reservations, covoiturages, utilisateurs).
     *
     * @param string $statut Le statut recherché ('ouvert' ou 'fermé').
     * @return array Tableau associatif contenant toutes les infos pour l'affichage.
     */
    public function getByStatus(string $statut)
    {
        try {
            // Construction de la requête complexe :
            // 1. On part de la table 'incidents' (i).
            // 2. On joint 'reservations' (r) pour savoir de quelle réservation on parle.
            // 3. On joint 'covoiturages' (c) pour avoir les infos du trajet (date, lieux).
            // 4. On joint 'utilisateurs' DEUX FOIS (alias u_conducteur et u_passager) :
            //    - Une fois pour avoir le nom du chauffeur (via c.chauffeur_id).
            //    - Une fois pour avoir le nom du passager qui se plaint (via r.utilisateur_id).

            $query = "
                SELECT 
                    i.id AS incident_id, 
                    i.reservation_id,
                    c.id AS covoiturage_id,
                    
                    -- On récupère les pseudos via les alias de jointure
                    u_conducteur.pseudo AS conducteur_pseudo,
                    u_passager.pseudo AS passager_pseudo,
                    
                    -- Formatage de la date directement en SQL (DD/MM/YYYY)
                    DATE_FORMAT(c.date_depart, '%d/%m/%Y') AS date_trajet,
                    
                    -- Concaténation pour un affichage propre du trajet (ex: 'Paris → Lyon')
                    CONCAT(c.depart, ' → ', c.arrivee) AS lieu,
                    
                    i.commentaire AS description
                FROM incidents i
                JOIN reservations r ON i.reservation_id = r.id
                JOIN covoiturages c ON r.covoiturage_id = c.id
                JOIN utilisateurs u_conducteur ON c.chauffeur_id = u_conducteur.id
                JOIN utilisateurs u_passager ON r.utilisateur_id = u_passager.id
                WHERE i.statut = ?
                ORDER BY i.date_creation DESC
            ";

            // Préparation et exécution sécurisée
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$statut]);

            // Retourne les résultats sous forme de tableau associatif utilisable par la Vue
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur IncidentModel::getByStatus : ' . $e->getMessage());
            return []; // Retourne un tableau vide pour ne pas faire planter l'affichage
        }
    }
}
