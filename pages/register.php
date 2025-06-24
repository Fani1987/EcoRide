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

  $isChauffeur = $type === 'Chauffeur' || $type === 'Passager/Chauffeur';
  $isPassager = $type === 'Passager' || $type === 'Passager/Chauffeur';

  $hash = password_hash($mdp, PASSWORD_DEFAULT);

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
    $couleur = $_POST['coulor'];
    $plaque = $_POST['plate'];
    $immat = $_POST['immatriculation'];
    $places = $_POST['places'];

    if ($marque && $modele && $couleur && $plaque && $immat  && $places) {
      $stmt = $pdo->prepare("INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, plaque_immatriculation, date_premiere_immat) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$user_id, $marque, $modele, $couleur, $plaque, $immat]);
    }
  }

  echo "<p>Inscription réussie !</p>";
}
?>
<br />
<div class="container">
  <h1 class="bg-secondary text-center text-black m-6">Nouveau compte</h1>
</div>

<div class="bg-primary text-black register">
  <form action="" method="POST">
    <div>
      <label for="pseudo">Pseudo:</label>
      <input class="form" id="pseudo" name="pseudo" type="text" />
    </div>

    <br />

    <div>
      <label for="mail">E-mail :</label>
      <input class="form" id="email" name="email" type="email" />
    </div>

    <br />

    <div>
      <p class="text-center">
        Pour protéger vos comptes, créez des mots de passe sécurisés. Utilisez
        au moins 12 caractères, mélangez lettres, chiffres et symboles, et
        évitez les informations personnelles.
      </p>
    </div>

    <div>
      <label for="pass">Mot de passe :</label>
      <input class="form" id="pass" type="password" />
    </div>

    <div>
      <label for="pass">Confirmer mot de passe :</label>
      <input class="form" id="pass" type="password" />
    </div>

    <div>
      <label for="">Type d'utilisateur :</label>
      <input class="form" type="text" list="usertype" id="type" name="type" />
      <datalist id="usertype">
        <option value="Chauffeur"></option>
        <option value="Passager"></option>
        <option value="Passager/Chauffeur"></option>
      </datalist>
    </div>

    <br />

    <div class="text-center">
      <p>
        Vous souhaitez devenir Eco chauffeur! Merci de nous fournir quelques
        informations supplémentaires :
      </p>
    </div>

    <div>
      <label for="car">Marque du véhicule :</label>
      <input class="form" id="car" name="car" type="text" />
    </div>

    <br />

    <div>
      <label for="model">Modèle du véhicule :</label>
      <input class="form" id="model" name="model" type="text" />
    </div>

    <br />

    <div>
      <label for="color">Couleur du véhicule :</label>
      <input class="form" id="color" name="color" type="text" />
    </div>

    <br />

    <div>
      <label for="plate">Numéro d'immatriculation :</label>
      <input class="form" id="plate" name="plate" type="text" />
    </div>

    <br />

    <div>
      <label for="immatriculationDate">Date de première immatriculation :</label>
      <input class="form" id="immatriculation" name="immatriculation" type="text" />
    </div>

    <br />

    <div>
      <label for="places">Nombre de places disponibles :</label>
      <input class="form" id="places" name="places" type="text" />
    </div>

    <br />

    <div>
      <label for="prefs">Préférences d'utilisation :</label>
    </div>

    <div>
      <label><input type="checkbox" name="prefs" value="smoke" />Fumeurs
        acceptés</label>
    </div>

    <div>
      <label><input type="checkbox" name="prefs" value="animals" />Animaux
        acceptés</label>
    </div>

    <div>
      <label><input type="checkbox" name="prefs" value="men" />Hommes seuls
        refusés</label>
    </div>

    <div>
      <label><input type="checkbox" name="prefs" value="music" /> Musique</label>
    </div>

    <br />

    <div class="text-center">
      <button
        class="btn btn-dark"
        type="button"
        onclick="enregistrerPreferences()">
        Enregistrer
      </button>
    </div>
  </form>
</div>