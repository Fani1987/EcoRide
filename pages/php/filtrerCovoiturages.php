<?php
require_once 'db_connection.php';

// Récupération des filtres
$type = $_GET['typeTrajet'] ?? '';
$prixMax = $_GET['prixMax'] ?? '';
$dureeMax = $_GET['dureeMax'] ?? '';
$noteMin = $_GET['noteMin'] ?? '';

// Construction de la requête SQL
$sql = "SELECT c.*, u.pseudo, u.note FROM covoiturages c
        JOIN utilisateurs u ON c.chauffeur_id = u.id
        WHERE 1=1";
$params = [];

if ($type === 'ecologique') {
    $sql .= " AND c.est_ecologique = 1";
}
if ($type === 'standard') {
    $sql .= " AND c.est_ecologique = 0";
}
if ($prixMax !== '') {
    $sql .= " AND c.prix <= ?";
    $params[] = $prixMax;
}
if ($dureeMax !== '') {
    $sql .= " AND TIMESTAMPDIFF(MINUTE, c.date_depart, c.date_arrivee) <= ?";
    $params[] = $dureeMax;
}
if ($noteMin !== '') {
    $sql .= " AND u.note >= ?";
    $params[] = $noteMin;
}

// Exécution
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Affichage
if (count($results) === 0) {
    echo "<p>Aucun covoiturage trouvé.</p>";
} else {
    foreach ($results as $row) {
        echo "<div class='card mb-3 p-3 bg-light'>";
        echo "<h5>" . htmlspecialchars($row['pseudo']) . "</h5>";
        echo "<p>Note : " . htmlspecialchars($row['note']) . " ★</p>";
        echo "<p>Départ : " . htmlspecialchars($row['depart']) . "</p>";
        echo "<p>Arrivée : " . htmlspecialchars($row['arrivee']) . "</p>";
        echo "<p>Date : " . htmlspecialchars($row['date_depart']) . "</p>";
        echo "<p>Prix : " . htmlspecialchars($row['prix']) . " crédits</p>";
        echo "<p>Places disponibles : " . htmlspecialchars($row['places_disponibles']) . "</p>";
        echo "<p>Type : " . ($row['est_ecologique'] ? "Écologique" : "Standard") . "</p>";
        echo "<a href='/pages/php/profilConducteur.php?id=" . $row['chauffeur_id'] . "' class='btn btn-primary'>Détails</a>";
        echo "</div>";
    }
}
