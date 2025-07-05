<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est du JSON

require_once '../db_connection.php'; // Chemin vers votre fichier de connexion à la BDD

// Vérifier si l'ID du trajet est fourni dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID de trajet manquant ou invalide.']);
    http_response_code(400); // Bad Request
    exit;
}

$trajetId = $_GET['id'];

try {
    // 1. Récupérer les détails du covoiturage
    $stmtTrajet = $pdo->prepare("
        SELECT
            c.id,
            c.depart,
            c.arrivee,
            c.date_depart,
            c.date_arrivee,
            c.prix,
            c.places_disponibles,
            c.est_ecologique,
            c.duree,
            c.chauffeur_id,
            c.vehicule_id
        FROM
            covoiturages c
        WHERE
            c.id = :id
    ");
    $stmtTrajet->execute([':id' => $trajetId]);
    $trajet = $stmtTrajet->fetch(PDO::FETCH_ASSOC);

    if (!$trajet) {
        echo json_encode(['error' => 'Trajet introuvable.']);
        http_response_code(404); // Not Found
        exit;
    }

    // 2. Récupérer les informations du conducteur
    $conducteur = null;
    if ($trajet['chauffeur_id']) {
        $stmtConducteur = $pdo->prepare("
            SELECT
                u.id,
                u.pseudo,
                u.description,
                u.note_moyenne
            FROM
                utilisateurs u
            WHERE
                u.id = :chauffeur_id
        ");
        $stmtConducteur->execute([':chauffeur_id' => $trajet['chauffeur_id']]);
        $conducteur = $stmtConducteur->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Récupérer les informations du véhicule
    $vehicule = null;
    if ($trajet['vehicule_id']) {
        $stmtVehicule = $pdo->prepare("
            SELECT
                v.marque,
                v.modele,
                v.couleur,
                v.energie
            FROM
                vehicules v
            WHERE
                v.id = :vehicule_id
        ");
        $stmtVehicule->execute([':vehicule_id' => $trajet['vehicule_id']]);
        $vehicule = $stmtVehicule->fetch(PDO::FETCH_ASSOC);
    }

    // 4. Récupérer les avis pour ce conducteur
    // Note: Pour l'instant, les préférences viennent de la table MySQL 'preferences_utilisateur'.
    // Quand MongoDB sera intégré, cette partie sera modifiée.
    $stmtAvis = $pdo->prepare("
        SELECT
            u.pseudo AS auteur,
            a.note,
            a.commentaire
        FROM
            avis a
        JOIN
            utilisateurs u ON a.utilisateur_id = u.id
        WHERE
            a.covoiturage_id IN (SELECT id FROM covoiturages WHERE chauffeur_id = :chauffeur_id)
        ORDER BY
            a.id DESC
    ");
    $stmtAvis->execute([':chauffeur_id' => $trajet['chauffeur_id']]);
    $avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

    // 5. Récupérer les préférences du conducteur (depuis MySQL pour le moment)
    $stmtPreferences = $pdo->prepare("
        SELECT
            pu.preference
        FROM
            preferences_utilisateur pu
        WHERE
            pu.utilisateur_id = :utilisateur_id
    ");
    $stmtPreferences->execute([':utilisateur_id' => $trajet['chauffeur_id']]);
    $preferences = $stmtPreferences->fetchAll(PDO::FETCH_COLUMN); // Récupère seulement la colonne 'preference'


    // Combiner toutes les données
    $responseData = [
        'trajet' => $trajet,
        'conducteur' => $conducteur,
        'vehicule' => $vehicule,
        'preferences' => $preferences, // Ceci sera remplacé par MongoDB plus tard
        'avis' => $avis
    ];

    echo json_encode($responseData);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
    http_response_code(500); // Internal Server Error
} catch (Exception $e) {
    echo json_encode(['error' => 'Une erreur inattendue est survenue : ' . $e->getMessage()]);
    http_response_code(500); // Internal Server Error
}
