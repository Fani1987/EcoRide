<?php

namespace App\Models;

use PDO;
use PDOException;

class NotificationModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère les notifications non lues d'un utilisateur.
     */
    public function getUnread(int $userId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM notifications 
             WHERE utilisateur_id = ? AND est_lu = 0 
             ORDER BY date_creation DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marque une liste de notifications comme lues.
     */
    public function markAsRead(array $notificationIds): bool
    {
        if (empty($notificationIds)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));

        try {
            $stmt = $this->pdo->prepare("UPDATE notifications SET est_lu = 1 WHERE id IN ($placeholders)");
            return $stmt->execute($notificationIds);
        } catch (PDOException $e) {
            error_log("Erreur NotificationModel::markAsRead : " . $e->getMessage());
            return false;
        }
    }
}
