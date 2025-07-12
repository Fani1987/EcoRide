<?php

namespace App\Core; // Ou App\Database;

use PDO;
use PDOException;
use MongoDB\Client;

class Database
{
    private static $mongoClient = null;

    public static function getMongoClient(): Client
    {
        if (self::$mongoClient === null) {
            // Charger les variables d'environnement si elles ne sont pas déjà chargées (par exemple, dans le point d'entrée de votre application)
            if (!class_exists(Dotenv::class)) {
                // Vous pourriez avoir une autre façon de charger .env
                // Par exemple, si vous utilisez un framework, il pourrait gérer cela.
                // Sinon, vous devrez le charger explicitement :
                // $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); // Ajustez le chemin si nécessaire
                // $dotenv->load();
            }

            $host = $_ENV['MONGO_DB_HOST'] ?? 'localhost';
            $port = $_ENV['MONGO_DB_PORT'] ?? '27017';
            $dbName = $_ENV['MONGO_DB_NAME'] ?? 'testdb'; // Par défaut à 'testdb' si non défini

            $uri = "mongodb://$host:$port";

            // Ajouter l'authentification si le nom d'utilisateur et le mot de passe sont fournis
            $username = $_ENV['MONGO_DB_USERNAME'] ?? null;
            $password = $_ENV['MONGO_DB_PASSWORD'] ?? null;

            $options = [];
            if ($username && $password) {
                $options['username'] = $username;
                $options['password'] = $password;
            }

            try {
                self::$mongoClient = new Client($uri, $options);
            } catch (\MongoDB\Driver\Exception\Exception $e) {
                // Journaliser l'erreur ou la gérer de manière appropriée
                die("Échec de la connexion à MongoDB : " . $e->getMessage());
            }
        }
        return self::$mongoClient;
    }

    private static ?PDO $instance = null; // Utilisez le type hint pour PDO et ? pour null

    private function __construct()
    {
        // Constructeur privé pour empêcher l'instanciation directe
    }

    private function __clone()
    {
        // Empêcher le clonage de l'instance
    }

    /**
     * Récupère l'instance unique de la connexion PDO (pattern Singleton).
     *
     * @return PDO L'objet PDO connecté à la base de données.
     * @throws PDOException Si la connexion échoue.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Charger les variables d'environnement (si ce n'est pas déjà fait ailleurs)
            // Assurez-vous que Dotenv est bien chargé et que les variables sont accessibles
            // require_once __DIR__ . '/../../vendor/autoload.php'; // Ou assurez-vous que Composer l'a déjà fait
            // $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            // $dotenv->load();

            $dbHost = $_ENV['DB_HOST'];
            $dbName = $_ENV['DB_NAME'];
            $dbUser = $_ENV['DB_USER'];
            $dbPass = $_ENV['DB_PASS'];
            $dbPort = $_ENV['DB_PORT'];
            $dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=$dbCharset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Afficher les erreurs PDO en tant qu'exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Récupérer les résultats sous forme de tableau associatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactiver l'émulation des requêtes préparées pour une meilleure sécurité/performance
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Désactiver la vérification du certificat SSL pour éviter les erreurs de connexion SSL
            ];

            try {
                self::$instance = new PDO($dsn, $dbUser, $dbPass, $options);
            } catch (PDOException $e) {
                // Enregistrement de l'erreur dans les logs au lieu de l'afficher
                error_log("Erreur de connexion PDO : " . $e->getMessage());
                // Rediriger vers une page d'erreur générique ou afficher un message convivial
                die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
            }
        }
        return self::$instance;
    }
}
