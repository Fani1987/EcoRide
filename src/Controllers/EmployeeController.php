<?php

namespace App\Controllers;

use PDO;
// On importe les modèles nécessaires pour gérer les avis et les incidents
// Cela respecte le principe de séparation des responsabilités (MVC)
use App\Models\AvisModel;
use App\Models\IncidentModel;

/**
 * Class EmployeeController
 *
 * Cette classe gère l'espace réservé aux employés (Modération).
 * Elle permet d'afficher le tableau de bord et de traiter les actions de validation/refus.
 */
class EmployeeController
{

    /**
     * Affiche le tableau de bord de l'employé.
     * Cette méthode récupère toutes les données nécessaires (avis en attente, incidents ouverts)
     * et les transmet à la vue pour affichage.
     *
     * @param PDO $pdo Instance de connexion à la base de données
     */
    public static function showDashboard(PDO $pdo)
    {
        // 1. Instanciation des modèles
        // Le contrôleur ne fait pas de SQL direct, il utilise les modèles comme intermédiaires.
        $avisModel = new AvisModel($pdo);
        $incidentModel = new IncidentModel($pdo);

        // 2. Récupération des données
        // On prépare un tableau associatif $data qui sera "déballé" (extract) dans la vue.
        $data = [
            // Liste des avis qui nécessitent une modération ('en_attente')
            'avisEnAttente' => $avisModel->getByStatus('en_attente'),

            // Liste des avis déjà validés (pour historique/consultation)
            'avisValides' => $avisModel->getByStatus('validé'),

            // Liste des incidents non résolus ('ouvert')
            'incidentsOuverts' => $incidentModel->getByStatus('ouvert')
        ];

        // 3. Appel de la vue
        // La fonction renderView se charge d'inclure le header, le fichier 'views/employees.php' et le footer.
        \renderView('employees', $data);
    }


    /**
     * Valide un avis passager.
     * Cette action est déclenchée par le bouton "Valider" du tableau de bord.
     *
     * @param PDO $pdo Instance de connexion
     * @param array $postData Données du formulaire (contient 'avis_id')
     */
    public static function validateAvis(PDO $pdo, array $postData)
    {
        // 1. Validation de l'entrée
        if (!isset($postData['avis_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'avis manquant.'];
            return; // On arrête si l'ID n'est pas fourni
        }

        // 2. Appel au Modèle pour la mise à jour
        $avisModel = new AvisModel($pdo);

        // On tente de passer le statut à 'validé'.
        // Si réussi, un Trigger SQL (update_chauffeur_note) se déclenchera automatiquement 
        // pour recalculer la note moyenne du chauffeur.
        if ($avisModel->updateStatus((int)$postData['avis_id'], 'validé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Avis validé.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors de la validation.'];
        }

        // Pas de redirection explicite ici car cette méthode est souvent appelée
        // juste avant le rechargement de la page par le routeur, ou via fetch.
    }

    /**
     * Refuse un avis passager.
     * L'avis ne sera pas supprimé mais marqué comme 'refusé' (Soft Delete / Archivage).
     */
    public static function refuseAvis(PDO $pdo, array $postData)
    {
        if (!isset($postData['avis_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'avis manquant.'];
            return;
        }

        $avisModel = new AvisModel($pdo);
        // Mise à jour du statut vers 'refusé'
        if ($avisModel->updateStatus((int)$postData['avis_id'], 'refusé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Avis refusé.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du refus.'];
        }
    }

    /**
     * Marque un incident comme "traité" (fermé).
     * Permet à l'employé d'indiquer qu'il a résolu le litige.
     */
    public static function markIncidentHandled(PDO $pdo, array $postData)
    {
        if (!isset($postData['incident_id'])) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID d\'incident manquant.'];
            return;
        }

        $incidentModel = new IncidentModel($pdo);
        // Mise à jour du statut vers 'fermé'
        if ($incidentModel->updateStatus((int)$postData['incident_id'], 'fermé')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Incident marqué comme traité.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur lors du traitement.'];
        }
    }
}
