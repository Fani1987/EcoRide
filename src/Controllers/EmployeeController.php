<?php

namespace App\Controllers;

use PDO;
// On importe les nouveaux modèles
use App\Models\AvisModel;
use App\Models\IncidentModel;

class EmployeeController
{

    /**
     * Affiche le tableau de bord de l'employé avec les listes à traiter.
     * C'est cette méthode qui fournit les données à la vue employees.php
     */
    public static function showDashboard(PDO $pdo)
    {
        // 1. Instancier les modèles
        $avisModel = new AvisModel($pdo);
        $incidentModel = new IncidentModel($pdo);

        // 2. Récupérer toutes les données
        $data = [
            'avisEnAttente' => $avisModel->getByStatus('en_attente'),
            'avisValides' => $avisModel->getByStatus('validé'),
            'incidentsOuverts' => $incidentModel->getByStatus('ouvert')
        ];

        // 3. Appeler la vue en lui passant les données
        \renderView('employees', $data);
    }


    /**
     * Valide un avis.
     * Le contrôleur gère la requête et appelle le modèle.
     */
    public static function validateAvis(PDO $pdo, array $postData)
    {
        if (!isset($postData['avis_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'avis manquant.'];
            return;
        }

        $avisModel = new AvisModel($pdo);
        if ($avisModel->updateStatus((int)$postData['avis_id'], 'validé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Avis validé.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la validation.'];
        }
        // La redirection est gérée dans le routeur (index.php)
    }

    /**
     * Refuse un avis.
     */
    public static function refuseAvis(PDO $pdo, array $postData)
    {
        if (!isset($postData['avis_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'avis manquant.'];
            return;
        }

        $avisModel = new AvisModel($pdo);
        if ($avisModel->updateStatus((int)$postData['avis_id'], 'refusé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Avis refusé.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du refus.'];
        }
    }

    /**
     * Marque un incident comme "traité" (fermé).
     */
    public static function markIncidentHandled(PDO $pdo, array $postData)
    {
        if (!isset($postData['incident_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'incident manquant.'];
            return;
        }

        $incidentModel = new IncidentModel($pdo);
        if ($incidentModel->updateStatus((int)$postData['incident_id'], 'fermé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Incident marqué comme traité.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du traitement.'];
        }
    }
}
