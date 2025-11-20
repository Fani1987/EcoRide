<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class ProfilModel
 *
 * Ce modèle gère la table `profils_utilisateur`.
 *
 * CONTEXTE MÉTIER :
 * Dans EcoRide, un utilisateur peut avoir plusieurs "casquettes" simultanément.
 * Il peut être à la fois "Chauffeur" ET "Passager".
 * Plutôt que de gérer cela dans la table principale `utilisateurs` (qui gère l'auth),
 * ou de faire deux tables séparées, on utilise cette table de profil liée par clé étrangère.
 */
class ProfilModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     */
    private $pdo;

    /**
     * Constructeur avec Injection de Dépendance.
     *
     * @param PDO $pdo On injecte la connexion active (Singleton) pour économiser les ressources.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée le profil associé à un nouvel utilisateur lors de son inscription.
     *
     * Cette méthode est appelée par AuthController::register() immédiatement après
     * la création du compte dans la table `utilisateurs`.
     * C'est la deuxième étape de la transaction d'inscription.
     *
     * @param int $userId L'ID de l'utilisateur venant d'être créé (Foreign Key).
     * @param bool $isChauffeur Vrai si l'utilisateur a coché "Chauffeur".
     * @param bool $isPassager Vrai si l'utilisateur a coché "Passager".
     * @return bool Retourne true si l'insertion a réussi.
     */
    public function createProfil(int $userId, bool $isChauffeur, bool $isPassager): bool
    {
        // 1. Préparation de la requête SQL
        // On utilise INSERT INTO pour créer la ligne de profil liée à l'utilisateur.
        $stmt = $this->pdo->prepare(
            "INSERT INTO profils_utilisateur (utilisateur_id, est_chauffeur, est_passager) 
             VALUES (?, ?, ?)"
        );

        // 2. Exécution et Conversion de Types (Casting)
        // MySQL ne possède pas de type "BOOL" natif, il utilise TINYINT(1).
        // On convertit donc les booléens PHP (true/false) en entiers (1/0) grâce à l'opérateur ternaire.
        return $stmt->execute([
            $userId,
            $isChauffeur ? 1 : 0, // Si true -> 1, sinon -> 0
            $isPassager ? 1 : 0
        ]);
    }
}
