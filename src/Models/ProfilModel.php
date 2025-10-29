<?php

namespace App\Models;

use PDO;
use PDOException;

class ProfilModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée le profil (rôles) d'un utilisateur lors de l'inscription.
     */
    public function createProfil(int $userId, bool $isChauffeur, bool $isPassager): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO profils_utilisateur (utilisateur_id, est_chauffeur, est_passager) 
             VALUES (?, ?, ?)"
        );
        return $stmt->execute([$userId, $isChauffeur ? 1 : 0, $isPassager ? 1 : 0]);
    }
}
