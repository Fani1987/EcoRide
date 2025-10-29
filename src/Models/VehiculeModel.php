<?php

namespace App\Models;

use PDO;
use PDOException;

class VehiculeModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouveau véhicule pour un utilisateur.
     */
    public function create(int $userId, string $marque, string $modele, string $couleur, string $plaque, string $energie, string $immat): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, plaque_immatriculation, energie, date_premiere_immat) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $marque, $modele, $couleur, $plaque, $energie, $immat]);
    }

    /**
     * Met à jour un véhicule existant.
     */
    public function update(int $vehiculeId, int $userId, string $marque, string $modele, string $couleur, string $plaque, string $energie, string $immat): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vehicules SET marque = ?, modele = ?, couleur = ?, energie = ?, plaque_immatriculation = ?, date_premiere_immat = ? 
             WHERE id = ? AND utilisateur_id = ?"
        );
        return $stmt->execute([$marque, $modele, $couleur, $energie, $plaque, $immat, $vehiculeId, $userId]);
    }

    /**
     * Récupère tous les véhicules d'un utilisateur.
     */
    public function findByUserId(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
