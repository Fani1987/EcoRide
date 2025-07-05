<?php

namespace App\Controllers;

use PDO;
use PDOException;

class TrajetController
{

    /**
     * Permet à un utilisateur (chauffeur) d'ajouter un nouveau trajet.
     * Déduit 2 crédits du compte du chauffeur lors de l'ajout.
     * Utilise une transaction pour assurer l'atomicité de l'insertion du trajet et de la déduction des crédits.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param array $postData Les données soumises via la requête POST.
     */
    public static function ajouterTrajet($pdo, $postData)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour ajouter un trajet.']);
            exit();
        }

        $userId = $_SESSION['user_id'];

        // Validation des données requises
        $requiredFields = ['depart', 'arrivee', 'date_depart', 'heure_depart', 'prix', 'places_disponibles', 'vehicule_id', 'duree'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
                exit();
            }
        }

        // Assurez-vous que est_ecologique est un booléen ou 0/1
        $estEcologique = isset($postData['est_ecologique']) ? 1 : 0;

        try {
            $pdo->beginTransaction();

            // Vérifier les crédits du chauffeur
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans profils_utilisateur
            $stmtCredits = $pdo->prepare("SELECT credits FROM profils_utilisateur WHERE utilisateur_id = ? FOR UPDATE"); // FOR UPDATE pour verrouiller la ligne
            $stmtCredits->execute([$userId]);
            $currentCredits = $stmtCredits->fetchColumn();

            $costToAddTrajet = 2; // Coût fixe pour ajouter un trajet

            if ($currentCredits === false || $currentCredits < $costToAddTrajet) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Crédits insuffisants pour ajouter un trajet.']);
                exit();
            }

            // Déduire les crédits
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans profils_utilisateur
            $stmtUpdateCredits = $pdo->prepare("UPDATE profils_utilisateur SET credits = credits - ? WHERE utilisateur_id = ?");
            if (!$stmtUpdateCredits->execute([$costToAddTrajet, $userId])) {
                throw new PDOException("Failed to update user credits.");
            }

            // Insérer le nouveau trajet
            $stmtTrajet = $pdo->prepare("INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, heure_depart, prix, places_disponibles, est_ecologique, duree) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmtTrajet->execute([
                $userId,
                $postData['vehicule_id'],
                $postData['depart'],
                $postData['arrivee'],
                $postData['date_depart'],
                $postData['heure_depart'],
                $postData['prix'],
                $postData['places_disponibles'],
                $estEcologique,
                $postData['duree']
            ])) {
                throw new PDOException("Failed to insert new trajet.");
            }

            $pdo->commit(); // Valider la transaction

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Trajet ajouté avec succès et ' . $costToAddTrajet . ' crédits déduits !']);
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack(); // Annuler toutes les opérations si une erreur survient
            error_log("Erreur lors de l'ajout de trajet (TrajetController::ajouterTrajet) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'ajout du trajet. Veuillez réessayer.']);
            exit();
        }
    }

    /**
     * Affiche la page détaillée d'un covoiturage spécifique.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $trajetId L'ID du trajet à afficher.
     */
    public static function showTrajetDetail($pdo, $trajetId) {
        $sql = "SELECT c.*, u.pseudo AS chauffeur_pseudo, u.id AS chauffeur_id,
                       v.marque AS vehicule_marque, v.modele AS vehicule_modele,
                       v.couleur AS vehicule_couleur,
                       v.plaque_immatriculation AS vehicule_immatriculation
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE c.id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trajetId]);
            $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trajet) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Trajet non trouvé.'];
                header('Location: /covoiturage'); // Rediriger si le trajet n'existe pas
                exit();
            }

            // Récupérer la liste des passagers qui ont réservé ce trajet
            // CORRECTION: r.user_id -> r.utilisateur_id dans la table reservations
            // CORRECTION: r.trajet_id -> r.covoiturage_id (confirmé par ecoride (2).sql)
            $sqlPassagers = "SELECT u.id, u.pseudo, u.email FROM reservations r JOIN utilisateurs u ON r.utilisateur_id = u.id WHERE r.covoiturage_id = ?";
            $stmtPassagers = $pdo->prepare($sqlPassagers);
            $stmtPassagers->execute([$trajetId]);
            $passagers = $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);

            // Préparer les données pour la vue
            $data = [
                'trajet' => $trajet,
                'passagers' => $passagers,
                'isDriver' => (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $trajet['chauffeur_id'])
            ];

            // Rendre la vue détaillée
            \renderView('covoiturage-detail', $data);

        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des détails du trajet : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erreur lors de la récupération des détails du trajet.'];
            header('Location: /covoiturage');
            exit();
        }
    }

    /**
     * Permet à un utilisateur de réserver (participer) à un trajet.
     * Déduit le prix du trajet du compte du passager.
     * Décrémente le nombre de places disponibles pour le trajet.
     * Utilise une transaction pour assurer l'atomicité des opérations.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $trajet_id L'ID du trajet à réserver.
     * @param int $user_id L'ID de l'utilisateur qui réserve.
     */
    public static function participerTrajet($pdo, $trajet_id, $user_id)
    {
        try {
            $pdo->beginTransaction();

            // 1. Vérifier si l'utilisateur est déjà inscrit à ce trajet
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans reservations
            // CORRECTION: 'trajet_id' -> 'covoiturage_id' dans reservations
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE utilisateur_id = ? AND covoiturage_id = ?");
            $stmtCheck->execute([$user_id, $trajet_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Vous êtes déjà inscrit à ce trajet.']);
                exit();
            }

            // 2. Récupérer les infos du trajet et verrouiller la ligne pour la transaction
            $stmtTrajet = $pdo->prepare("SELECT prix, places_disponibles, chauffeur_id FROM covoiturages WHERE id = ? FOR UPDATE");
            $stmtTrajet->execute([$trajet_id]);
            $trajet = $stmtTrajet->fetch(PDO::FETCH_ASSOC);

            if (!$trajet) {
                $pdo->rollBack();
                throw new PDOException("Trajet non trouvé.");
            }

            if ($trajet['places_disponibles'] <= 0) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Plus de places disponibles pour ce trajet.']);
                exit();
            }

            // Empêcher le chauffeur de réserver son propre trajet
            if ($trajet['chauffeur_id'] == $user_id) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas réserver votre propre trajet.']);
                exit();
            }

            // 3. Vérifier les crédits de l'utilisateur qui réserve
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans profils_utilisateur
            $stmtCredits = $pdo->prepare("SELECT credits FROM profils_utilisateur WHERE utilisateur_id = ? FOR UPDATE");
            $stmtCredits->execute([$user_id]);
            $currentCredits = $stmtCredits->fetchColumn();

            if ($currentCredits === false || $currentCredits < $trajet['prix']) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Crédits insuffisants pour réserver ce trajet.']);
                exit();
            }

            // 4. Déduire les crédits du passager
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans profils_utilisateur
            $stmtUpdateCredits = $pdo->prepare("UPDATE profils_utilisateur SET credits = credits - ? WHERE utilisateur_id = ?");
            if (!$stmtUpdateCredits->execute([$trajet['prix'], $user_id])) {
                throw new PDOException("Failed to update user credits for reservation.");
            }

            // 5. Incrémenter les crédits du chauffeur (si vous avez cette logique)
            // Si le prix est payé au chauffeur en crédits, ajoutez cette logique ici
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans profils_utilisateur
            $stmtAddCreditsChauffeur = $pdo->prepare("UPDATE profils_utilisateur SET credits = credits + ? WHERE utilisateur_id = ?");
            if (!$stmtAddCreditsChauffeur->execute([$trajet['prix'], $trajet['chauffeur_id']])) {
                throw new PDOException("Failed to add credits to driver.");
            }

            // 6. Décrémenter les places disponibles
            $stmtUpdatePlaces = $pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
            if (!$stmtUpdatePlaces->execute([$trajet_id])) {
                throw new PDOException("Failed to update available places.");
            }

            // 7. Insérer la réservation
            // CORRECTION: 'user_id' -> 'utilisateur_id' dans reservations
            // CORRECTION: 'trajet_id' -> 'covoiturage_id' dans reservations
            $stmtReservation = $pdo->prepare("INSERT INTO reservations (utilisateur_id, covoiturage_id, date_reservation, statut) VALUES (?, ?, NOW(), ?)");
            // 'confirmée' ou un autre statut initial, selon votre logique de double confirmation
            if (!$stmtReservation->execute([$user_id, $trajet_id, 'confirmée'])) {
                throw new PDOException("Failed to insert reservation.");
            }

            // Si tout s'est bien passé, valider la transaction
            $pdo->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Réservation effectuée avec succès ! ' . $trajet['prix'] . ' crédits déduits.']);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack(); // Annuler toutes les opérations si une erreur survient
            error_log("Erreur lors de la réservation de trajet (TrajetController::participerTrajet) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la réservation. Veuillez réessayer.']);
            exit();
        }
    }
}