<?php

namespace App\Controllers;

use PDO;
use Exception;
use App\Models\VehiculeModel;

/**
 * Class VehicleController
 * * Responsabilité : Gérer uniquement les actions liées aux véhicules.
 * Cela permet d'alléger le UserController (Principe de Séparation des Responsabilités).
 */
class VehicleController
{
    /**
     * Gère l'ajout ou la mise à jour d'un véhicule.
     * Cette méthode agit comme une API : elle reçoit des données et renvoie du JSON.
     * * @param PDO $pdo Connexion à la base de données
     * @param int $userId ID du propriétaire du véhicule (utilisateur connecté)
     * @param array $postData Données du formulaire ($_POST)
     */
    public static function updateVehicle(PDO $pdo, int $userId, array $postData)
    {
        // 1. Configuration de la réponse
        // On indique au navigateur qu'on va renvoyer du JSON (pour le JavaScript fetch)
        header('Content-Type: application/json');

        // 2. Récupération et Nettoyage des données
        // L'opérateur '??' (Null Coalescing) évite les erreurs si le champ est absent
        $vehiculeId = $postData['id'] ?? null;
        $marque = trim($postData['marque'] ?? '');
        $modele = trim($postData['modele'] ?? '');
        $plaque = trim($postData['plaque_immatriculation'] ?? '');
        $couleur = trim($postData['couleur'] ?? '');
        $energie = trim($postData['energie'] ?? '');
        $date_immat = trim($postData['date_premiere_immat'] ?? '');

        // 3. Validation basique
        if (empty($marque) || empty($modele) || empty($plaque)) {
            echo json_encode(['success' => false, 'message' => 'Marque, modèle et plaque sont requis.']);
            exit(); // On arrête tout si les données sont invalides
        }

        try {
            // 4. Instanciation du Modèle
            $vehiculeModel = new VehiculeModel($pdo);
            $success = false;
            $message = '';

            // 5. Logique métier : Création ou Mise à jour ?
            if ($vehiculeId) {
                // --- UPDATE ---
                // On met à jour un véhicule existant.
                // IMPORTANT : Le modèle vérifiera que $userId est bien le propriétaire (Sécurité)
                $success = $vehiculeModel->update((int)$vehiculeId, $userId, $marque, $modele, $couleur, $plaque, $energie, $date_immat);
                $message = 'Véhicule mis à jour avec succès.';
            } else {
                // --- CREATE ---
                // On crée un nouveau véhicule lié à l'utilisateur
                $success = $vehiculeModel->create($userId, $marque, $modele, $couleur, $plaque, $energie, $date_immat);
                $message = 'Véhicule ajouté avec succès.';
            }

            if (!$success) {
                throw new Exception('L\'opération a échoué en base de données.');
            }

            // 6. Réponse succès
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            // 7. Gestion des erreurs
            // On loggue l'erreur technique pour le développeur
            error_log("Erreur VehicleController : " . $e->getMessage());
            // On renvoie un message générique à l'utilisateur
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'enregistrement.']);
        }
        exit();
    }
}
