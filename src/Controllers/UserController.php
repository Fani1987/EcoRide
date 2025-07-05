<?php

namespace App\Controllers;

use PDO;
use PDOException;

class UserController
{

    /**
     * Affiche la page de profil de l'utilisateur avec toutes ses informations (détails, véhicules, trajets, avis).
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $userId L'ID de l'utilisateur dont le profil doit être affiché.
     */
    public static function showProfilePage($pdo, $userId)
    {
        // Vérifier si l'utilisateur est connecté et correspond à l'ID demandé
        // Ou si l'utilisateur est un admin et peut voir n'importe quel profil (logique à ajouter si nécessaire)
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != $userId && $_SESSION['role'] !== 'admin')) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Accès non autorisé au profil.'];
            header('Location: /login');
            exit();
        }

        // Récupération des informations de l'utilisateur et de son profil
        // CORRECTION: pu.user_id -> pu.utilisateur_id dans profils_utilisateur
        $stmtUser = $pdo->prepare("SELECT u.*, pu.credits, pu.est_chauffeur, pu.est_passager, pu.biographie FROM utilisateurs u JOIN profils_utilisateur pu ON u.id = pu.utilisateur_id WHERE u.id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Utilisateur non trouvé.'];
            header('Location: /'); // Redirection vers l'accueil si l'utilisateur n'existe pas
            exit();
        }

        // Récupération des véhicules de l'utilisateur
        // 'utilisateur_id' est déjà correct ici, suite à une correction précédente.
        $stmtVehicules = $pdo->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ?");
        $stmtVehicules->execute([$userId]);
        $vehicules = $stmtVehicules->fetchAll(PDO::FETCH_ASSOC);

        // Récupération des trajets proposés par l'utilisateur (en tant que chauffeur)
        // 'chauffeur_id' est déjà correct ici, suite à une correction précédente.
        $stmtTrajetsProposes = $pdo->prepare("SELECT * FROM covoiturages WHERE chauffeur_id = ? ORDER BY date_depart DESC");
        $stmtTrajetsProposes->execute([$userId]);
        $trajetsProposes = $stmtTrajetsProposes->fetchAll(PDO::FETCH_ASSOC);

        // Récupération des trajets réservés par l'utilisateur (en tant que passager)
        // CORRECTION: r.user_id -> r.utilisateur_id dans reservations
        // CORRECTION: c.user_id -> c.chauffeur_id dans covoiturages (pour la jointure)
        $stmtTrajetsReserves = $pdo->prepare("
            SELECT c.*, u.pseudo AS chauffeur_pseudo
            FROM reservations r
            JOIN covoiturages c ON r.covoiturage_id = c.id
            JOIN utilisateurs u ON c.chauffeur_id = u.id
            WHERE r.utilisateur_id = ? -- CORRECTION: r.user_id -> r.utilisateur_id
            ORDER BY c.date_depart DESC
        ");
        $stmtTrajetsReserves->execute([$userId]);
        $trajetsReserves = $stmtTrajetsReserves->fetchAll(PDO::FETCH_ASSOC);

        // Préparer les données pour la vue
        $data = [
            'user' => $user,
            'vehicules' => $vehicules,
            'trajetsProposes' => $trajetsProposes,
            'trajetsReserves' => $trajetsReserves
            // Ajoutez ici les avis ou autres données si nécessaires
        ];

        // Rendre la vue de profil
        \renderView('profile', $data);
    }

    /**
     * Gère la mise à jour des informations de profil de l'utilisateur.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $userId L'ID de l'utilisateur à mettre à jour.
     * @param array $postData Les données POST du formulaire de mise à jour.
     */
    public static function updateProfile($pdo, $userId, $postData)
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            exit();
        }

        $pseudo = trim($postData['pseudo'] ?? '');
        $email = trim($postData['email'] ?? '');
        $bio = trim($postData['bio'] ?? '');

        if (empty($pseudo) || empty($email)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Pseudo et email sont requis.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Mettre à jour la table 'utilisateurs'
            $stmtUser = $pdo->prepare("UPDATE utilisateurs SET pseudo = ?, email = ?, bio = ? WHERE id = ?");
            if (!$stmtUser->execute([$pseudo, $email, $bio, $userId])) {
                throw new PDOException("Failed to update user details.");
            }

            // Mettre à jour la table 'profils_utilisateur' si la biographie est stockée là aussi
            // CORRECTION: user_id -> utilisateur_id dans profils_utilisateur
            $stmtProfil = $pdo->prepare("UPDATE profils_utilisateur SET biographie = ? WHERE utilisateur_id = ?");
            if (!$stmtProfil->execute([$bio, $userId])) { // Assurez-vous que 'biographie' est le bon nom de colonne
                throw new PDOException("Failed to update user profile bio.");
            }

            $pdo->commit();

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil mis à jour avec succès.'];
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur lors de la mise à jour du profil (UserController::updateProfile) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la mise à jour du profil.']);
            exit();
        }
    }

    /**
     * Gère l'ajout ou la mise à jour d'un véhicule pour l'utilisateur.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $userId L'ID de l'utilisateur propriétaire du véhicule.
     * @param array $postData Les données POST du formulaire de véhicule.
     */
    public static function updateVehicle($pdo, $userId, $postData)
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            exit();
        }

        $vehiculeId = $postData['id'] ?? null; // Peut être null si c'est un nouveau véhicule
        $marque = trim($postData['marque'] ?? '');
        $modele = trim($postData['modele'] ?? '');
        $couleur = trim($postData['couleur'] ?? '');
        $energie = trim($postData['energie'] ?? ''); // 'essence','diesel','electrique','hybride'
        $plaque_immatriculation = trim($postData['plaque_immatriculation'] ?? '');
        $date_premiere_immat = trim($postData['date_premiere_immat'] ?? ''); // Format YYYY-MM-DD

        // Validation des champs requis pour un véhicule
        if (empty($marque) || empty($modele) || empty($plaque_immatriculation)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Marque, modèle et plaque d\'immatriculation sont requis.']);
            exit();
        }

        try {
            if ($vehiculeId) {
                // Mise à jour d'un véhicule existant
                // 'utilisateur_id' est déjà correct ici.
                $stmt = $pdo->prepare("UPDATE vehicules SET marque = ?, modele = ?, couleur = ?, energie = ?, plaque_immatriculation = ?, date_premiere_immat = ? WHERE id = ? AND utilisateur_id = ?");
                if (!$stmt->execute([$marque, $modele, $couleur, $energie, $plaque_immatriculation, $date_premiere_immat, $vehiculeId, $userId])) {
                    throw new PDOException("Failed to update vehicle.");
                }
                $message = 'Véhicule mis à jour avec succès.';
            } else {
                // Ajout d'un nouveau véhicule
                // 'utilisateur_id' est déjà correct ici.
                $stmt = $pdo->prepare("INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, energie, plaque_immatriculation, date_premiere_immat) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt->execute([$userId, $marque, $modele, $couleur, $energie, $plaque_immatriculation, $date_premiere_immat])) {
                    throw new PDOException("Failed to insert vehicle.");
                }
                $message = 'Véhicule ajouté avec succès.';
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();

        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour/ajout du véhicule (UserController::updateVehicle) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'opération sur le véhicule.']);
            exit();
        }
    }
}