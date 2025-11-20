<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class VehiculeModel
 *
 * Ce Modèle gère l'entité `vehicules` dans la base de données.
 * Il est responsable de l'ajout, de la modification et de la récupération des véhicules.
 *
 * Rôle dans l'architecture :
 * - Permet aux chauffeurs d'enregistrer leurs voitures pour proposer des trajets.
 * - Garantit que chaque véhicule est bien lié à un utilisateur unique (Clé étrangère).
 */
class VehiculeModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     */
    private $pdo;

    /**
     * Constructeur avec Injection de Dépendance.
     * On récupère la connexion active pour éviter d'en recréer une nouvelle.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crée un nouveau véhicule pour un utilisateur.
     *
     * @param int $userId L'ID du propriétaire (Chauffeur).
     * @param string $marque
     * @param string $modele
     * @param string $couleur
     * @param string $plaque Immatriculation (Donnée sensible, mais nécessaire).
     * @param string $energie Type de carburant (utilisé pour déterminer si c'est écologique).
     * @param string $immat Date de première immatriculation.
     * @return bool Succès de l'opération.
     */
    public function create(int $userId, string $marque, string $modele, string $couleur, string $plaque, string $energie, string $immat): bool
    {
        // 1. Préparation de la requête
        // On utilise une requête préparée pour se protéger des injections SQL.
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehicules (utilisateur_id, marque, modele, couleur, plaque_immatriculation, energie, date_premiere_immat) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        // 2. Exécution
        // Les données sont envoyées séparément de la requête.
        return $stmt->execute([$userId, $marque, $modele, $couleur, $plaque, $energie, $immat]);
    }

    /**
     * Met à jour un véhicule existant.
     *
     * SÉCURITÉ CRITIQUE :
     * Cette méthode vérifie implicitement que l'utilisateur qui tente la modification
     * est bien le PROPRIÉTAIRE du véhicule.
     *
     * @param int $vehiculeId L'ID du véhicule à modifier.
     * @param int $userId L'ID de l'utilisateur connecté (venant de la session).
     * @param string ...$args Les autres paramètres.
     * @return bool Succès.
     */
    public function update(int $vehiculeId, int $userId, string $marque, string $modele, string $couleur, string $plaque, string $energie, string $immat): bool
    {
        // La clause "AND utilisateur_id = ?" est la clé de la sécurité ici.
        // Si un utilisateur malveillant essaie de changer l'ID du véhicule dans le formulaire
        // pour modifier la voiture de quelqu'un d'autre, la condition "utilisateur_id = ?" échouera
        // car le véhicule ne lui appartient pas. La requête ne modifiera aucune ligne (rowCount = 0).
        $stmt = $this->pdo->prepare(
            "UPDATE vehicules 
             SET marque = ?, modele = ?, couleur = ?, energie = ?, plaque_immatriculation = ?, date_premiere_immat = ? 
             WHERE id = ? AND utilisateur_id = ?"
        );

        return $stmt->execute([$marque, $modele, $couleur, $energie, $plaque, $immat, $vehiculeId, $userId]);
    }

    /**
     * Récupère tous les véhicules appartenant à un utilisateur spécifique.
     * Utilisé pour l'affichage dans le Profil ("Mes véhicules").
     *
     * @param int $userId
     * @return array Liste des véhicules.
     */
    public function findByUserId(int $userId)
    {
        // Requête simple de lecture avec filtre sur la clé étrangère.
        $stmt = $this->pdo->prepare("SELECT * FROM vehicules WHERE utilisateur_id = ?");
        $stmt->execute([$userId]);

        // Retourne un tableau de tableaux associatifs.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
