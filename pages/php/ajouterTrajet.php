<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Utilisateur non connecté.");
}

require_once 'db_connection.php'; // Connexion centralisée à la base

$chauffeur_id = $_SESSION['user_id'];
$depart = $_POST['depart'];
$arrivee = $_POST['arrivee'];
$date_depart = $_POST['date_depart'];
$prix = $_POST['prix'];
$vehicule_id = $_POST['vehicule'];
$places = $_POST['places'];

try {
    // Récupérer le type d'énergie du véhicule
    $stmt = $pdo->prepare("SELECT energie FROM vehicules WHERE id = ?");
    $stmt->execute([$vehicule_id]);
    $vehicule = $stmt->fetch();

    if (!$vehicule) {
        die("Véhicule non trouvé.");
    }

    $energie = strtolower($vehicule['energie']);
    $est_ecologique = ($energie === 'electrique' || $energie === 'hybride') ? 1 : 0;

    // Insertion du trajet
    $stmt = $pdo->prepare("INSERT INTO covoiturages (chauffeur_id, vehicule_id, depart, arrivee, date_depart, prix, places_disponibles, est_ecologique)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$chauffeur_id, $vehicule_id, $depart, $arrivee, $date_depart, $prix, $places, $est_ecologique]);

    echo "<p class='text-success'>Trajet ajouté avec succès !</p>";
} catch (PDOException $e) {
    echo "<p class='text-danger'>Erreur : " . $e->getMessage() . "</p>";
}

// Fonction de réservation (à appeler lors de la réservation d'une place)
function reserverPlace($pdo, $covoiturage_id)
{
    $stmt = $pdo->prepare("SELECT places_disponibles FROM covoiturages WHERE id = ?");
    $stmt->execute([$covoiturage_id]);
    $row = $stmt->fetch();

    if ($row && $row['places_disponibles'] > 0) {
        $stmt = $pdo->prepare("UPDATE covoiturages SET places_disponibles = places_disponibles - 1 WHERE id = ?");
        $stmt->execute([$covoiturage_id]);
        return true;
    } else {
        return false;
    }
}
