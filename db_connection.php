<?php

require_once __DIR__ . '/vendor/autoload.php'; // Charge l'autoloader de Composer

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$charset = 'utf8mb4'; // Reste inchangé

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // En développement, on peut afficher l'erreur pour déboguer
    // En production, il est préférable de logguer l'erreur et afficher un message générique
    die("Erreur de connexion : " . $e->getMessage());
}
