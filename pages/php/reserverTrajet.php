<?php
session_start();
require_once 'db_connection.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Vous devez être connecté pour réserver.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$trajet_id = $_POST['trajet_id'] ?? null;

// Vérification des données
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

    if (!$trajet || $trajet['places_disponibles'] <= 0) {
        echo json_encode(['error' => 'Aucune place disponible.']);
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

    // Double confirmation (à gérer côté front-end)
    if (!isset($_POST['confirmation']) || $_POST['confirmation'] !== 'oui') {
        echo json_encode(['confirmation_required' => true, 'message' => 'Confirmez la réservation.']);
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

    echo json_encode(['success' => 'Réservation confirmée.']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la réservation : ' . $e->getMessage()]);
}
