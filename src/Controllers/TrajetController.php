<?php

namespace App\Controllers;

use PDO;
use PDOException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TrajetController
{

    public static function ajouterTrajet($pdo, $postData)
    { // Permet à un chauffeur d'ajouter un nouveau trajet.
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour ajouter un trajet.']);
            exit();
        }
        // On récupère l'ID de l'utilisateur connecté
        $userId = $_SESSION['user_id'];
        // On vérifie que tous les champs requis sont présents
        $requiredFields = ['depart', 'arrivee', 'date_depart', 'date_arrivee', 'prix', 'places_disponibles', 'vehicule_id'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
                exit();
            }
        }

        $estEcologique = isset($postData['est_ecologique']) ? 1 : 0;
        // On vérifie que l'utilisateur a suffisament de crédits pour ajouter un trajet
        try {
            $pdo->beginTransaction();

            $stmtCredits = $pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ? FOR UPDATE");
            $stmtCredits->execute([$userId]);
            $currentCredits = $stmtCredits->fetchColumn();

            $costToAddTrajet = 2;

            if ($currentCredits === false || $currentCredits < $costToAddTrajet) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Crédits insuffisants pour ajouter un trajet.']);
                exit();
            }
            // On déduit les crédits de l'utilisateur
            $stmtUpdateCredits = $pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
            if (!$stmtUpdateCredits->execute([$costToAddTrajet, $userId])) {
                throw new PDOException("Failed to update user credits.");
            }
            // On insère le nouveau trajet dans la base de données
            $stmtTrajet = $pdo->prepare("INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, prix, places_disponibles, est_ecologique) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmtTrajet->execute([
                $userId,
                $postData['vehicule_id'],
                $postData['depart'],
                $postData['arrivee'],
                $postData['date_depart'],
                $postData['prix'],
                $postData['places_disponibles'],
                $estEcologique
            ])) {
                throw new PDOException("Failed to insert new trajet.");
            }

            $pdo->commit();
            // On envoie un message de succès
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Trajet ajouté avec succès et ' . $costToAddTrajet . ' crédits déduits !'];
            header('Location: /profile');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur lors de l'ajout de trajet (TrajetController::ajouterTrajet) : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'ajout du trajet. Veuillez réessayer.']);
            exit();
        }
    }
    public static function showTrajetDetail($pdo, $trajetId)
    { // Affiche les détails d'un trajet spécifique, y compris les passagers et les avis sur le chauffeur.
        $sql = "SELECT c.*, u.pseudo AS chauffeur_pseudo, u.id AS chauffeur_id,
                       v.marque AS vehicule_marque, v.modele AS vehicule_modele,
                       v.couleur AS vehicule_couleur,
                       v.plaque_immatriculation AS vehicule_immatriculation
                FROM covoiturages c
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                JOIN vehicules v ON c.vehicule_id = v.id
                WHERE c.id = ?";

        try { // Préparer et exécuter la requête
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$trajetId]);
            $trajet = $stmt->fetch(PDO::FETCH_ASSOC);
            // Vérifier si le trajet existe
            if (!$trajet) { // Si le trajet n'existe pas, on redirige avec un message d'erreur
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Trajet non trouvé.'];
                header('Location: /covoiturage');
                exit();
            }

            // 1. Récupérer les passagers
            $sqlPassagers = "SELECT u.id, u.pseudo FROM reservations r JOIN utilisateurs u ON r.utilisateur_id = u.id WHERE r.covoiturage_id = ?";
            $stmtPassagers = $pdo->prepare($sqlPassagers);
            $stmtPassagers->execute([$trajetId]);
            $passagers = $stmtPassagers->fetchAll(PDO::FETCH_ASSOC);

            // 2. Récupérer les avis sur le chauffeur
            $stmtAvis = $pdo->prepare("SELECT a.note, a.commentaire, u_auteur.pseudo AS auteur FROM avis a JOIN utilisateurs u_auteur ON a.utilisateur_id = u_auteur.id WHERE a.covoiturage_id IN (SELECT id FROM covoiturages WHERE chauffeur_id = ?)");
            $stmtAvis->execute([$trajet['chauffeur_id']]);
            $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

            // 3. Récupérer les préférences du chauffeur depuis MongoDB
            $userController = new UserController();
            $preferences = $userController->getUserPreferences($trajet['chauffeur_id']);

            $data = [
                'trajet' => $trajet,
                'passagers' => $passagers,
                'avis' => $avis,               // <-- On envoie les avis à la vue
                'preferences' => $preferences, // <-- On envoie les préférences à la vue
                'isDriver' => (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $trajet['chauffeur_id'])
            ];
            // On utilise une méthode de rendu de vue pour afficher les détails du trajet
            \renderView('covoiturage-detail', $data);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des détails du trajet : " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erreur lors de la récupération des détails du trajet.'];
            header('Location: /covoiturage');
            exit();
        }
    }

    public static function participerTrajet($pdo, $trajet_id, $user_id)
    { // Permet à un utilisateur de participer à un trajet en réservant une place.
        try {
            $pdo->beginTransaction();

            // 1. Vérifier si l'utilisateur est déjà inscrit à ce trajet
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
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Trajet non trouvé.']);
                exit();
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
            $stmtCredits = $pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ? FOR UPDATE");
            $stmtCredits->execute([$user_id]);
            $currentCredits = $stmtCredits->fetchColumn();

            if ($currentCredits === false || $currentCredits < $trajet['prix']) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Crédits insuffisants pour réserver ce trajet.']);
                exit();
            }

            // 4. Déduire les crédits du passager
            $stmtUpdateCredits = $pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
            if (!$stmtUpdateCredits->execute([$trajet['prix'], $user_id])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'La mise à jour des crédits du passager a échoué.']);
                exit();
            }

            // 5. Incrémenter les crédits du chauffeur
            // Commenté car la logique de crédit du chauffeur est gérée différemment
            //$stmtAddCreditsChauffeur = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
            //if (!$stmtAddCreditsChauffeur->execute([$trajet['prix'], $trajet['chauffeur_id']])) {
            //header('Content-Type: application/json');
            //echo json_encode(['success' => false, 'message' => 'La mise à jour des crédits du chauffeur a échoué.']);
            //exit();
            //}

            // 6. Décrémenter les places disponibles
            $stmtUpdatePlaces = $pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
            if (!$stmtUpdatePlaces->execute([$trajet_id])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'La mise à jour des places disponibles a échoué.']);
                exit();
            }

            // 7. Insérer la réservation
            $stmtReservation = $pdo->prepare("INSERT INTO reservations (utilisateur_id, covoiturage_id, date_reservation, statut) VALUES (?, ?, NOW(), ?)");
            if (!$stmtReservation->execute([$user_id, $trajet_id, 'en_attente'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'L\'insertion de la réservation a échoué.']);
                exit();
            }

            // Si tout s'est bien passé, valider la transaction et renvoyer JSON
            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Réservation effectuée avec succès !']);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur lors de la réservation : " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la réservation. Veuillez réessayer.']);
            exit;
        }
    }

    public static function confirmerReservation(PDO $pdo, int $reservation_id, string $statut)
    {
        // Permet à un chauffeur de confirmer ou refuser une réservation.

        // On vérifie que le statut demandé est valide
        $statuts_valides = ['confirmée', 'refusée'];
        if (!in_array($statut, $statuts_valides)) {
            echo json_encode(['success' => false, 'message' => 'Statut invalide.']);
            exit;
        }
        // On vérifie que le chauffeur est bien connecté
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }

        $chauffeur_id = $_SESSION['user_id'];

        try {
            // On récupère les informations nécessaires (ID du passager, détails du trajet)
            // pour vérifier les droits ET pour créer la notification
            $sql = "SELECT r.utilisateur_id, c.chauffeur_id, c.depart, c.arrivee
                    FROM reservations r
                    JOIN covoiturages c ON r.covoiturage_id = c.id
                    WHERE r.id = ? AND c.chauffeur_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reservation_id, $chauffeur_id]);
            $reservation_data = $stmt->fetch();


            // Si la réservation n'existe pas ou n'appartient pas au chauffeur, on arrête
            if (!$reservation_data) {
                return;
            }

            // On met à jour le statut de la réservation
            $update = $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
            $update->execute([$statut, $reservation_id]);

            // CRÉATION DE LA NOTIFICATION ---
            // Si le chauffeur a confirmé (et non refusé), on crée une notification pour le passager
            if ($statut === 'confirmée') {
                $message = "Bonne nouvelle ! Votre réservation pour le trajet de " .
                    htmlspecialchars($reservation_data['depart']) . " à " .
                    htmlspecialchars($reservation_data['arrivee']) . " a été confirmée.";

                // On appelle la méthode que nous avions créée dans UserController
                UserController::createNotification($pdo, $reservation_data['utilisateur_id'], $message);
            }
            // Si le statut est 'refusée', on pourrait aussi créer une notification de refus ici.

        } catch (PDOException $e) {
            error_log("Erreur lors de la confirmation de réservation : " . $e->getMessage());
        }
    }

    public static function startTrajet(PDO $pdo, int $trajet_id)
    { // Permet à un chauffeur de démarrer son covoiturage.
        header('Content-Type: application/json');

        // Sécurité : Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $userId = $_SESSION['user_id'];

        try {
            // Sécurité : Vérifier si l'utilisateur est bien le chauffeur de ce trajet
            $stmt = $pdo->prepare("SELECT chauffeur_id FROM covoiturages WHERE id = ?");
            $stmt->execute([$trajet_id]);
            $chauffeur_id_db = $stmt->fetchColumn();

            if ($chauffeur_id_db != $userId) {
                echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                exit;
            }

            // Mettre à jour le statut du covoiturage
            $updateStmt = $pdo->prepare("UPDATE covoiturages SET statut = 'en_cours' WHERE id = ? AND statut = 'planifié'");
            $updateStmt->execute([$trajet_id]);
            // Si la mise à jour a réussi, on renvoie un message de succès
            // Sinon, on renvoie un message d'erreur
            if ($updateStmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Trajet démarré avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Le trajet n\'a pas pu être démarré (il est peut-être déjà en cours ou terminé).']);
            }
        } catch (PDOException $e) {
            error_log("Erreur dans startTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
        }
    }

    public static function endTrajet(PDO $pdo, int $trajet_id)
    { // Permet à un chauffeur de terminer son covoiturage et notifie les passagers.
        header('Content-Type: application/json');

        // Sécurité : Vérifier la connexion et le rôle de l'utilisateur
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $userId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // Sécurité : Vérifier si l'utilisateur est bien le chauffeur du trajet
            $stmt = $pdo->prepare("SELECT chauffeur_id FROM covoiturages WHERE id = ?");
            $stmt->execute([$trajet_id]);
            $chauffeur_id_db = $stmt->fetchColumn();

            if ($chauffeur_id_db != $userId) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
                exit;
            }

            // Mettre à jour le statut du covoiturage à 'terminé'
            $updateStmt = $pdo->prepare("UPDATE covoiturages SET statut = 'terminé' WHERE id = ? AND statut = 'en_cours'");
            $updateStmt->execute([$trajet_id]);

            if ($updateStmt->rowCount() == 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Le trajet n\'a pas pu être terminé (il n\'était pas en cours).']);
                exit;
            }

            // Récupérer les emails et pseudos des passagers pour les notifier
            $stmtPassagers = $pdo->prepare("
                SELECT u.email, u.pseudo FROM reservations r
                JOIN utilisateurs u ON r.utilisateur_id = u.id
                WHERE r.covoiturage_id = ?
            ");
            $stmtPassagers->execute([$trajet_id]);
            $passagers = $stmtPassagers->fetchAll();

            // Envoi des e-mails
            foreach ($passagers as $passager) {

                // Envoyer un email de notification
                // On simule l'envoi en écrivant dans les logs au lieu d'envoyer un vrai email
                error_log("SIMULATION: E-mail de fin de trajet envoyé à " . $passager['email']);

                /*bloc d'envoi d'email (absence d'un serveur SMTP fonctionnel en environnement de développement local)
                $mail = new PHPMailer(true);
                try {
                    // Configuration du serveur SMTP (à mettre dans votre fichier .env)
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['MAIL_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['MAIL_USERNAME'];
                    $mail->Password   = $_ENV['MAIL_PASSWORD'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $_ENV['MAIL_PORT'];

                    // Destinataires et expéditeur
                    $mail->setFrom('no-reply@ecoride.fr', 'EcoRide');
                    $mail->addAddress($passager['email'], $passager['pseudo']);

                    // Contenu de l'e-mail
                    $mail->isHTML(true);
                    $mail->Subject = 'Votre trajet EcoRide est terminé !';
                    $mail->Body    = 'Bonjour ' . htmlspecialchars($passager['pseudo']) . ',<br><br>Votre trajet est maintenant terminé. Merci de vous rendre sur votre profil pour valider que tout s\'est bien passé et laisser un avis à votre chauffeur.<br><br><a href="http://' . $_SERVER['HTTP_HOST'] . '/profile">Accéder à mon profil</a><br><br>L\'équipe EcoRide';
                    $mail->AltBody = 'Bonjour ' . htmlspecialchars($passager['pseudo']) . ', Votre trajet est maintenant terminé. Merci de vous rendre sur votre profil pour valider que tout s\'est bien passé et laisser un avis à votre chauffeur. Lien : http://' . $_SERVER['HTTP_HOST'] . '/profile';

                    $mail->send();
                } catch (Exception $e) {
                    // Ne pas bloquer le processus si un email échoue, mais l'enregistrer
                    error_log("PHPMailer n'a pas pu envoyer l'email à " . $passager['email'] . ". Erreur: {$mail->ErrorInfo}");
                }
                    */
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trajet terminé. Les passagers ont été notifiés.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur dans endTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
        }
    }

    public static function cancelReservation(PDO $pdo, int $reservation_id)
    { // Permet à un passager d'annuler sa propre réservation et de récupérer ses crédits.
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
            exit;
        }
        $passagerId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // Vérifier que la réservation appartient à l'utilisateur et que le trajet est annulable
            $stmt = $pdo->prepare("
                SELECT r.covoiturage_id, c.prix, c.statut AS trajet_statut 
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                WHERE r.id = ? AND r.utilisateur_id = ? AND r.statut = 'confirmée'
            ");
            $stmt->execute([$reservation_id, $passagerId]);
            $reservation = $stmt->fetch();

            if (!$reservation || $reservation['trajet_statut'] !== 'planifié') {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Annulation impossible. Le trajet a peut-être déjà commencé.']);
                exit;
            }

            // 1. Mettre à jour le statut de la réservation à 'annulée'
            $stmtUpdateRes = $pdo->prepare("UPDATE reservations SET statut = 'annulée' WHERE id = ?");
            $stmtUpdateRes->execute([$reservation_id]);

            // 2. Rembourser les crédits au passager
            $stmtRefund = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
            $stmtRefund->execute([$reservation['prix'], $passagerId]);

            // 3. Rajouter une place disponible dans le covoiturage
            $stmtAddPlace = $pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles + 1 WHERE id = ?");
            $stmtAddPlace->execute([$reservation['covoiturage_id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Votre réservation a été annulée et vos crédits vous ont été remboursés.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur dans cancelReservation : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'annulation.']);
        }
    }

    public static function cancelTrajet(PDO $pdo, int $trajet_id)
    { // Permet à un chauffeur d'annuler un de ses trajets et de rembourser les passagers.
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) { /* ... */
        }
        $chauffeurId = $_SESSION['user_id'];

        try {
            $pdo->beginTransaction();

            // Vérifier que le trajet appartient au chauffeur et est annulable
            $stmt = $pdo->prepare("SELECT prix, statut, depart, arrivee FROM covoiturages WHERE id = ? AND chauffeur_id = ?");
            $stmt->execute([$trajet_id, $chauffeurId]);
            $trajet = $stmt->fetch();

            if (!$trajet || $trajet['statut'] !== 'planifié') {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Annulation impossible.']);
                exit;
            }

            // 1. Mettre à jour le statut du covoiturage à 'annulé'
            $stmtUpdateTrajet = $pdo->prepare("UPDATE covoiturages SET statut = 'annulé' WHERE id = ?");
            $stmtUpdateTrajet->execute([$trajet_id]);

            // 2. Récupérer tous les passagers ayant une réservation confirmée pour ce trajet
            $stmtPassagers = $pdo->prepare("
                SELECT r.id, r.utilisateur_id, u.email, u.pseudo 
                FROM reservations r
                JOIN utilisateurs u ON r.utilisateur_id = u.id
                WHERE r.covoiturage_id = ? AND r.statut = 'confirmée'
            ");
            $stmtPassagers->execute([$trajet_id]);
            $passagers = $stmtPassagers->fetchAll();

            // 3. Pour chaque passager, annuler sa réservation et le rembourser
            foreach ($passagers as $passager) {
                // Rembourser le passager
                $stmtRefund = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
                $stmtRefund->execute([$trajet['prix'], $passager['utilisateur_id']]);

                // Mettre à jour sa réservation
                $stmtUpdateRes = $pdo->prepare("UPDATE reservations SET statut = 'annulée' WHERE id = ?");
                $stmtUpdateRes->execute([$passager['id']]);

                // On crée la notification dans la base de données
                $message = "Le trajet de " . htmlspecialchars($trajet['depart']) . " à " . htmlspecialchars($trajet['arrivee']) . " a été annulé par le chauffeur.";
                UserController::createNotification($pdo, $passager['utilisateur_id'], $message);

                // Envoyer un email de notification
                // On simule l'envoi en écrivant dans les logs au lieu d'envoyer un vrai email
                error_log("SIMULATION: E-mail d'annulation envoyé à " . $passager['email']);

                /*bloc d'envoi d'email (absence d'un serveur SMTP fonctionnel en environnement de développement local)
                $mail = new PHPMailer(true);
                try {

                    // Configuration du serveur SMTP
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['MAIL_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['MAIL_USERNAME'];
                    $mail->Password   = $_ENV['MAIL_PASSWORD'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $_ENV['MAIL_PORT'];

                    // Destinataires et expéditeur
                    $mail->setFrom('no-reply@ecoride.fr', 'EcoRide');
                    $mail->addAddress($passager['email'], $passager['pseudo']);

                    // Contenu de l'e-mail
                    $mail->Subject = 'Annulation de votre trajet EcoRide';
                    $mail->Body    = 'Bonjour ' . htmlspecialchars($passager['pseudo']) . ',<br><br>Nous sommes au regret de vous informer que votre trajet a été annulé par le chauffeur. Vos crédits vous ont été intégralement remboursés.';
                    $mail->send();
                } catch (Exception $e) {
                    error_log("PHPMailer n'a pas pu envoyer l'email d'annulation à " . $passager['email'] . ". Erreur: {$mail->ErrorInfo}");
                }
                    */
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Le trajet a été annulé. Les passagers ont été notifiés et remboursés.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur dans cancelTrajet : " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'annulation du trajet.']);
        }
    }
}
