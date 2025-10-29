<?php

namespace App\Models; // Namespace pour les Modèles

use PDO;
use PDOException; // Ajout de l'import pour la gestion d'erreur

class UserModel
{
    private $pdo; // Le modèle aura besoin de la connexion BDD

    // On passe la connexion BDD au modèle lors de sa création
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Trouve un utilisateur par son email.
     */
    public function findUserByEmail(string $email)
    {
        $stmt = $this->pdo->prepare("SELECT id, mot_de_passe, role, actif FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Récupère le crédit d'un utilisateur (en verrouillant la ligne)
     */
    public function getCreditsForUpdate(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Débite des crédits à un utilisateur
     */
    public function debitCredits(int $userId, float $amount): bool
    {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Crédite un utilisateur
     */
    public function creditCredits(int $userId, float $amount): bool
    {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Crée une notification pour un utilisateur
     */
    public function createNotification(int $userId, string $message): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications (utilisateur_id, message) VALUES (?, ?)");
            return $stmt->execute([$userId, $message]);
        } catch (PDOException $e) {
            error_log("Erreur création notification : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un email existe déjà dans la base.
     */
    public function checkEmailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouvel utilisateur (passager/chauffeur).
     */
    public function createUser(string $pseudo, string $email, string $hash, float $credits, string $description): ?int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, credit, description)
             VALUES (?, ?, ?, 'utilisateur', ?, ?)"
        );
        if ($stmt->execute([$pseudo, $email, $hash, $credits, $description])) {
            return (int)$this->pdo->lastInsertId();
        }
        return null;
    }

    /**
     * Crée un compte employé (rôle 'employe').
     */
    public function createEmployee(string $pseudo, string $email, string $hash): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, actif, credit)
             VALUES (?, ?, ?, 'employe', 1, 0)"
        );
        return $stmt->execute([$pseudo, $email, $hash]);
    }

    /**
     * Met à jour le statut 'actif' d'un utilisateur (pour suspendre/réactiver).
     */
    public function updateUserActiveStatus(string $email, string $role, int $status): int
    {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE email = ? AND role = ?");
        $stmt->execute([$status, $email, $role]);
        return $stmt->rowCount();
    }

    /**
     * Récupère les infos complètes du profil (utilisateur + rôles).
     */
    public function findProfilById(int $userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, pu.est_chauffeur, pu.est_passager
            FROM utilisateurs u
            LEFT JOIN profils_utilisateur pu ON u.id = pu.utilisateur_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les infos de base d'un utilisateur (pour la page d'édition).
     */
    public function findById(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour les informations de base du profil d'un utilisateur.
     */
    public function updateProfile(int $userId, string $pseudo, string $email, string $description): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE utilisateurs SET pseudo = ?, email = ?, description = ?
             WHERE id = ?"
        );
        return $stmt->execute([$pseudo, $email, $description, $userId]);
    }
} // <-- Fin de la classe UserModel