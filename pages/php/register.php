<?php

// Connexion à la base de données
$host = 'localhost';
$db   = 'ecoride';
$user = 'EstefaniaCapitao';
$pass = 'Mael06012014!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = $_POST['pseudo'];
    $email = $_POST['email'];
    $mdp = $_POST['password'];
    $type = $_POST['type'];
    $prefs = isset($_POST['prefs']) ? $_POST['prefs'] : [];

    $isChauffeur = $type === 'Chauffeur' || $type === 'Passager/Chauffeur';
    $isPassager = $type === 'Passager' || $type === 'Passager/Chauffeur';

    $hash = password_hash($mdp, PASSWORD_DEFAULT);

    // Vérification des champs requis

    if (empty($pseudo) || empty($email) || empty($mdp) || empty($type)) {
        echo "<p class='text-danger'>Tous les champs sont requis.</p>";
        return;
    }


    // Vérification de la complexité du mot de passe
    if (!preg_match("/^(?=.*[A-Za-z])(?=.*\\d)(?=.*[^A-Za-z\\d]).{12,}$/", $mdp)) {
        echo "<p class='text-danger'>Le mot de passe doit contenir au moins 12 caractères, incluant une lettre, un chiffre et un symbole.</p>";
        return;
    }

    // Vérification de l'unicité du pseudo et de l'email
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE pseudo = ? OR email = ?");
    $stmt->execute([$pseudo, $email]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo "<p class='text-danger'>Le pseudo ou l'email est déjà utilisé.</p>";
        return;
    }

    // Insertion dans la table utilisateurs
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role) VALUES (?, ?, ?, 'utilisateur')");
    $stmt->execute([$pseudo, $email, $hash]);
    $user_id = $pdo->lastInsertId();

    // Insertion dans la table profils_utilisateur
    $stmt = $pdo->prepare("INSERT INTO profils_utilisateur (utilisateur_id, est_chauffeur, est_passager) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $isChauffeur, $isPassager]);

    // Enregistrer le véhicule si c’est un chauffeur
    if ($isChauffeur) {
        $marque = $_POST['car'];
        $modele = $_POST['model'];
        $couleur = $_POST['color'];
        $plaque = $_POST['plate'];
        $immat = $_POST['immatriculation'];
        $places = $_POST['places'];

        if ($marque && $modele && $couleur && $plaque && $immat  && $places) {
            $stmt = $pdo->prepare("INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, plaque_immatriculation, date_premiere_immat, places) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $marque, $modele, $couleur, $plaque, $immat, $places]);
        }
    }

    // Enregistrer les préférences si c’est un chauffeur
    if ($isChauffeur && !empty($prefs)) {
        $prefsString = implode(',', $prefs);
        $stmt = $pdo->prepare("INSERT INTO preferences_utilisateur  (utilisateur_id, preferences) VALUES (?, ?)");
        $stmt->execute([$user_id, $prefsString]);
    }

    echo "<p class='text-success'>Inscription réussie !</p>";
}
