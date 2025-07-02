<?php
session_start();
require_once 'db_connection.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "<p class='text-danger'>Veuillez remplir tous les champs.</p>";
        exit;
    }

    // Vérification de l'utilisateur
    $stmt = $pdo->prepare("SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: profile.html");
        exit;
    } else {
        echo "<p class='text-danger'>Identifiants incorrects.</p>";
    }
}
