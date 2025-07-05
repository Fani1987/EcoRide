<?php

namespace App\Core; // Ou App\Database;

use PDO;
use PDOException;

class Database
{
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
            $dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Afficher les erreurs PDO en tant qu'exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Récupérer les résultats sous forme de tableau associatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactiver l'émulation des requêtes préparées pour une meilleure sécurité/performance
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
