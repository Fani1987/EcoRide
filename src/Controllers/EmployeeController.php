<?php

namespace App\Controllers;

use PDO;

class EmployeeController
{
    public static function validateAvis(PDO $pdo, array $postData)
    {
        // La méthode se contente de faire la mise à jour, sans rien afficher
        if (!isset($postData['avis_id'])) {
            return; // On arrête si l'ID est manquant
        }
        try {
            $stmt = $pdo->prepare("UPDATE avis SET statut = 'validé' WHERE id = ?");
            $stmt->execute([$postData['avis_id']]);
        } catch (\PDOException $e) {
            error_log('Erreur validation avis: ' . $e->getMessage());
        }
    }

    public static function refuseAvis(PDO $pdo, array $postData)
    {
        if (!isset($postData['avis_id'])) {
            return;
        }
        try {
            $stmt = $pdo->prepare("UPDATE avis SET statut = 'refusé' WHERE id = ?");
            $stmt->execute([$postData['avis_id']]);
        } catch (\PDOException $e) {
            error_log('Erreur refus avis: ' . $e->getMessage());
        }
    }

    public static function markIncidentHandled(PDO $pdo, array $postData)
    {
        if (!isset($postData['incident_id'])) {
            return;
        }
        try {
            $stmt = $pdo->prepare("UPDATE incidents SET statut = 'fermé' WHERE id = ?");
            $stmt->execute([$postData['incident_id']]);
        } catch (\PDOException $e) {
            error_log('Erreur traitement incident: ' . $e->getMessage());
        }
    }
}
