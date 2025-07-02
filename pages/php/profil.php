<?php
require_once 'db_connection.php';

if (!isset($_GET['id'])) {
    echo "Aucun covoiturage sélectionné.";
    exit;
}

$covoiturage_id = intval($_GET['id']);

// Récupération des infos du covoiturage
$stmt = $pdo->prepare("
    SELECT c.*, u.pseudo, u.description, v.marque, v.modele, v.energie, v.couleur, v.plaque_immatriculation, v.date_premiere_immat
    FROM covoiturages c
    JOIN utilisateurs u ON c.chauffeur_id = u.id
    JOIN vehicules v ON c.vehicule_id = v.id
    WHERE c.id = ?
");
$stmt->execute([$covoiturage_id]);
$covoiturage = $stmt->fetch();

if (!$covoiturage) {
    echo "Covoiturage introuvable.";
    exit;
}

// Récupération du type d'utilisateur
$stmt = $pdo->prepare("
    SELECT est_chauffeur, est_passager
    FROM profils_utilisateur
    WHERE utilisateur_id = ?
");
$stmt->execute([$covoiturage['chauffeur_id']]);
$profil = $stmt->fetch();

$type_utilisateur = 'Utilisateur';
if ($profil) {
    if ($profil['est_chauffeur'] && $profil['est_passager']) {
        $type_utilisateur = 'Chauffeur & Passager';
    } elseif ($profil['est_chauffeur']) {
        $type_utilisateur = 'Chauffeur';
    } elseif ($profil['est_passager']) {
        $type_utilisateur = 'Passager';
    }
}

// Calcul de la note moyenne du chauffeur si la table 'avis' existe
$note_moyenne = null;
if ($pdo->query("SHOW TABLES LIKE 'avis'")->rowCount() > 0) {
    $stmt = $pdo->prepare("SELECT AVG(note) as moyenne FROM notations WHERE utilisateur_id = ?");
    $stmt->execute([$covoiturage['utilisateur_id']]);
    $result = $stmt->fetch();
    if ($result && $result['moyenne'] !== null) {
        $note_moyenne = round($result['moyenne'], 1);
    }
}



// Récupération des préférences
$stmt = $pdo->prepare("
    SELECT preference FROM preferences_utilisateur
    WHERE utilisateur_id = ?
");
$stmt->execute([$covoiturage['chauffeur_id']]);
$preferences = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupération des avis (si table disponible)
$avis = [];
if ($pdo->query("SHOW TABLES LIKE 'avis'")->rowCount() > 0) {
    $stmt = $pdo->prepare("
        SELECT auteur, note, commentaire FROM avis
        WHERE chauffeur_id = ?
    ");
    $stmt->execute([$covoiturage['chauffeur_id']]);
    $avis = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Conducteur</title>
</head>

<body>
    <main class="container mb-5">
        <br />
        <div class="row">
            <div class="col-md-4 text-center text-black">
                <h3 class="bg-secondary text-center"><?= htmlspecialchars($covoiturage['pseudo']) ?></h3>

                <p class="text-muted"><?= $type_utilisateur ?></p>

                <p><i class="fas fa-star text-warning"></i>
                <p>
                    <i class="fas fa-star text-warning"></i>
                    <?= $note_moyenne !== null ? $note_moyenne . ' / 5' : 'Pas encore noté' ?>
                </p>

                <p class="fst-italic">
                    "<?= !empty($covoiturage['description']) ? htmlspecialchars($covoiturage['description']) : 'Aucune description disponible.' ?>"
                </p>

                <h5 class="mt-4">Préférences :</h5><br />
                <ul class="list-unstyled">
                    <?php foreach ($preferences as $pref): ?>
                        <li><i class="fas fa-check text-success"></i> <?= htmlspecialchars($pref) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-md-8">
                <div class="card border-dark mb-4">
                    <div class="card-header bg-dark text-white">Véhicule</div>
                    <div class="card-body bg-primary text-black">
                        <p><strong>Marque :</strong> <?= htmlspecialchars($covoiturage['marque']) ?></p>
                        <p><strong>Modèle :</strong> <?= htmlspecialchars($covoiturage['modele']) ?></p>
                        <p><strong>Énergie :</strong> <?= htmlspecialchars($covoiturage['energie']) ?></p>
                        <p><strong>Couleur :</strong> <?= htmlspecialchars($covoiturage['couleur']) ?></p>
                        <p><strong>Plaque :</strong> <?= htmlspecialchars($covoiturage['plaque_immatriculation']) ?></p>
                        <p><strong>Date de 1ère immatriculation :</strong> <?= htmlspecialchars($covoiturage['date_premiere_immat']) ?></p>
                        <p><strong>Nombre de places disponibles :</strong> <?= htmlspecialchars($covoiturage['places_disponibles']) ?></p>
                    </div>
                </div>

                <div class="card border-dark mb-4">
                    <div class="card-header bg-dark text-white">Historique des trajets</div>
                    <div class="card-body bg-primary">
                        <ul class="list-group">
                            <li class="list-group-item bg-primary border-black text-black">
                                <?= htmlspecialchars($covoiturage['depart']) ?> → <?= htmlspecialchars($covoiturage['arrivee']) ?> - <?= date('d M Y', strtotime($covoiturage['date_depart'])) ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card border-dark mb-4">
                    <div class="card-header bg-dark text-white">Avis des utilisateurs</div>
                    <div class="card-body bg-primary text-black">
                        <?php if (count($avis) > 0): ?>
                            <?php foreach ($avis as $a): ?>
                                <div class="mb-3">
                                    <p><strong><?= htmlspecialchars($a['auteur']) ?> :</strong> <?= str_repeat('⭐', $a['note']) ?></p>
                                    <p><?= htmlspecialchars($a['commentaire']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Aucun avis pour ce chauffeur.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>