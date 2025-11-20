<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class NotificationModel
 *
 * Ce Modèle gère les interactions avec la table `notifications`.
 * Il est responsable de la récupération des alertes non lues et de leur mise à jour.
 *
 * Rôle dans l'application :
 * Améliorer l'expérience utilisateur (UX) en informant les passagers/chauffeurs
 * des changements importants (validation de trajet, annulation, etc.).
 */
class NotificationModel
{
    /**
     * @var PDO Instance de connexion à la base de données (injectée).
     */
    private $pdo;

    /**
     * Constructeur.
     * On utilise l'Injection de Dépendance pour récupérer la connexion active.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère les notifications NON LUES d'un utilisateur spécifique.
     * Cette méthode est appelée à chaque chargement de la page Profil.
     *
     * @param int $userId L'ID de l'utilisateur connecté.
     * @return array Tableau associatif des notifications.
     */
    public function getUnread(int $userId)
    {
        // 1. Requête SQL
        // On filtre sur 'est_lu = 0' pour ne pas encombrer l'affichage avec l'historique.
        // On trie par date décroissante (DESC) pour voir les dernières nouvelles en haut.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM notifications 
             WHERE utilisateur_id = ? AND est_lu = 0 
             ORDER BY date_creation DESC"
        );

        // 2. Exécution sécurisée
        $stmt->execute([$userId]);

        // 3. Retour des données
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marque une liste de notifications comme "lues" (mise à jour de masse).
     *
     * C'est une méthode technique intéressante car elle doit gérer un nombre variable d'IDs
     * dans une clause SQL `IN (...)`, tout en restant sécurisée (préparée).
     *
     * @param array $notificationIds Tableau contenant les IDs des notifications (ex: [4, 12, 15]).
     * @return bool Succès ou échec.
     */
    public function markAsRead(array $notificationIds): bool
    {
        // Optimisation : Si le tableau est vide, inutile d'appeler la BDD.
        if (empty($notificationIds)) {
            return true;
        }

        // 1. Construction dynamique des marqueurs
        // Pour une clause 'IN (?, ?, ?)', il faut autant de '?' que d'IDs.
        // array_fill(0, 3, '?') => ['?', '?', '?']
        // implode(',', ...) => "?,?,?"
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));

        try {
            // 2. Préparation de la requête dynamique
            // On injecte la chaîne de points d'interrogation dans la requête.
            // "UPDATE ... WHERE id IN (?,?,?)"
            $stmt = $this->pdo->prepare("UPDATE notifications SET est_lu = 1 WHERE id IN ($placeholders)");

            // 3. Exécution
            // On passe le tableau d'IDs directement à execute().
            // PDO va mapper chaque ID à chaque '?' dans l'ordre.
            return $stmt->execute($notificationIds);
        } catch (PDOException $e) {
            // Gestion des erreurs (ex: format d'ID invalide)
            error_log("Erreur NotificationModel::markAsRead : " . $e->getMessage());
            return false;
        }
    }
}
