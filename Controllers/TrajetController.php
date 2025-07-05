<?php

class TrajetController
{

    // Méthode pour gérer l'ajout d'un trajet (vient de ajouterTrajet.php)
    public static function ajouterTrajet($pdo, $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            // Utiliser le système de message flash
            echo json_encode(['success' => 'Trajet ajouté avec succès !']); // Redirection vers la route de connexion
            exit;
        }

        $chauffeur_id = $_SESSION['user_id'];
        $depart = $postData['depart'] ?? '';
        $arrivee = $postData['arrivee'] ?? '';
        $date_depart = $postData['date_depart'] ?? '';
        $prix = $postData['prix'] ?? '';
        $vehicule_id = $postData['vehicule'] ?? '';
        $places = $postData['places'] ?? '';

        // Validation des champs
        if (empty($depart) || empty($arrivee) || empty($date_depart) || empty($prix) || empty($vehicule_id) || empty($places)) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Veuillez remplir tous les champs du trajet.']); // Rediriger vers la page de profil où se trouve le formulaire
            exit;
        }

        try {
            // Récupérer le type d'énergie du véhicule
            $stmt = $pdo->prepare("SELECT energie FROM vehicules WHERE id = ? AND utilisateur_id = ?"); // Vérifier que le véhicule appartient bien au chauffeur
            $stmt->execute([$vehicule_id, $chauffeur_id]);
            $vehicule = $stmt->fetch();

            if (!$vehicule) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => 'Véhicule non trouvé ou ne vous appartient pas.']);
                exit;
            }

            $energie = strtolower($vehicule['energie']);
            $est_ecologique = ($energie === 'electrique' || $energie === 'hybride') ? 1 : 0;

            // Insertion du trajet
            $stmt = $pdo->prepare("INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, prix, places_disponibles, est_ecologique)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$chauffeur_id, $vehicule_id, $depart, $arrivee, $date_depart, $prix, $places, $est_ecologique]);

            echo json_encode(['success' => 'Trajet ajouté avec succès !']);
            exit;
        } catch (PDOException $e) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Erreur lors de l\'ajout du trajet : ' . $e->getMessage()]);

            exit;
        }
    }

    // Méthode pour gérer la réservation d'un trajet (vient de reserverTrajet.php)
    public static function reserverTrajet($pdo, $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Vous devez être connecté pour réserver.']);
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $trajet_id = $postData['trajet_id'] ?? null;
        $confirmation = $postData['confirmation'] ?? null; // Récupérer la confirmation

        if (!$trajet_id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID du trajet manquant.']);
            exit;
        }

        try {
            // Vérifier si l'utilisateur a déjà réservé ce trajet
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE utilisateur_id = ? AND covoiturage_id = ?");
            $stmt->execute([$user_id, $trajet_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Vous avez déjà réservé ce trajet.']);
                exit;
            }

            // Récupérer les infos du trajet
            $stmt = $pdo->prepare("SELECT places_disponibles, prix, chauffeur_id FROM covoiturages WHERE id = ?");
            $stmt->execute([$trajet_id]);
            $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trajet) {
                echo json_encode(['error' => 'Trajet introuvable.']);
                exit;
            }

            if ($trajet['places_disponibles'] <= 0) {
                echo json_encode(['error' => 'Plus de places disponibles pour ce trajet.']);
                exit;
            }

            if ($trajet['chauffeur_id'] === $user_id) {
                echo json_encode(['error' => 'Vous ne pouvez pas réserver votre propre trajet.']);
                exit;
            }

            // Vérifier les crédits de l'utilisateur
            $stmt = $pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $credit = $stmt->fetchColumn();

            if ($credit < $trajet['prix']) {
                echo json_encode(['error' => 'Crédits insuffisants.']);
                exit;
            }

            // Double confirmation (gérer côté front-end si ce n'est pas déjà fait)
            // Cette partie devrait être envoyée AVANT la requête POST de réservation finale
            // Si c'est une requête AJAX, le frontend doit gérer la modale de confirmation.
            // Si la confirmation est requise avant la requête, le `confirmation_required`
            // ne devrait pas être dans ce script qui est censé traiter la confirmation finale.
            // Pour l'instant, je vais considérer que le 'oui' vient déjà d'une action de l'utilisateur.
            if ($confirmation !== 'oui') {
                echo json_encode(['confirmation_required' => true, 'message' => 'Confirmez la réservation pour utiliser vos crédits.']);
                exit;
            }


            // Début de transaction
            $pdo->beginTransaction();

            // Insérer la réservation
            $stmt = $pdo->prepare("INSERT INTO reservations (utilisateur_id, covoiturage_id, date_reservation) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $trajet_id]);

            // Mettre à jour les crédits de l'utilisateur
            $stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
            $stmt->execute([$trajet['prix'], $user_id]);

            // Mettre à jour les places disponibles
            $stmt = $pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
            $stmt->execute([$trajet_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Réservation effectuée avec succès !']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la réservation : ' . $e->getMessage()]);
        }
    }
}
