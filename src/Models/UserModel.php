<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class UserModel
 *
 * Ce Modèle gère l'entité `utilisateurs`, qui est centrale dans l'application.
 * Elle contient les informations de connexion, les données personnelles et le SOLDE de crédits.
 *
 * Rôle :
 * - Authentification (trouver par email).
 * - Gestion financière (débiter/créditer).
 * - Gestion des profils et des rôles.
 * - Administration (création d'employés, suspension).
 */
class UserModel
{
    /**
     * @var PDO Instance de connexion à la base de données (injectée).
     */
    private $pdo;

    /**
     * Constructeur.
     * On utilise l'Injection de Dépendance pour récupérer la connexion active.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Trouve un utilisateur par son email pour l'authentification.
     *
     * @param string $email
     * @return mixed Le tableau de l'utilisateur (avec le hash du mot de passe) ou false.
     */
    public function findUserByEmail(string $email)
    {
        // On récupère les champs nécessaires à la connexion (mot de passe) et à la sécurité (actif, role).
        // LIMIT 1 est une optimisation : on s'arrête dès qu'on a trouvé.
        $stmt = $this->pdo->prepare("SELECT id, mot_de_passe, role, actif FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Récupère le solde de crédits d'un utilisateur en VERROUILLANT la ligne.
     *
     * CONCEPT AVANCÉ : Pessimistic Locking (Verrouillage Pessimiste).
     * L'instruction `FOR UPDATE` dit à la base de données :
     * "Je lis ce solde pour le modifier. Personne d'autre ne peut y toucher tant que je n'ai pas fini."
     *
     * Cela empêche les "Race Conditions" (ex: dépenser les mêmes 10 crédits pour 2 trajets simultanés).
     *
     * @param int $userId
     * @return float|false Le montant du crédit.
     */
    public function getCreditsForUpdate(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT credit FROM utilisateurs WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Débite des crédits à un utilisateur.
     *
     * @param int $userId
     * @param float $amount Montant à retirer.
     * @return bool
     */
    public function debitCredits(int $userId, float $amount): bool
    {
        // On fait le calcul directement en SQL (credit = credit - ?) pour l'atomicité.
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET credit = credit - ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Crédite des crédits à un utilisateur (ex: après un trajet ou un achat).
     *
     * @param int $userId
     * @param float $amount Montant à ajouter.
     * @return bool
     */
    public function creditCredits(int $userId, float $amount): bool
    {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Vérifie si un email existe déjà (pour l'inscription).
     *
     * @param string $email
     * @return bool Vrai si l'email est pris.
     */
    public function checkEmailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        // fetchColumn() retourne directement le résultat du COUNT (int).
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouvel utilisateur (Inscription).
     *
     * @param string $pseudo
     * @param string $email
     * @param string $hash Mot de passe haché (Bcrypt).
     * @param float $credits Crédits de bienvenue.
     * @param string $description Bio de l'utilisateur.
     * @return int|false L'ID du nouvel utilisateur (pour créer son profil ensuite) ou false.
     */
    public function createUser(string $pseudo, string $email, string $hash, float $credits, string $description)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (pseudo, email, mot_de_passe, credit, description, role, actif) 
             VALUES (?, ?, ?, ?, ?, 'utilisateur', 1)"
        );

        if ($stmt->execute([$pseudo, $email, $hash, $credits, $description])) {
            // On retourne l'ID auto-incrémenté généré par MySQL
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    /**
     * Crée un compte employé (Back-office).
     * Le rôle est forcé à 'employe'.
     */
    public function createEmployee(string $nom, string $email, string $hash): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, actif) 
             VALUES (?, ?, ?, 'employe', 1)"
        );
        return $stmt->execute([$nom, $email, $hash]);
    }

    /**
     * Change le statut d'activation d'un compte (Suspension / Réactivation).
     *
     * @param string $email L'email de la cible.
     * @param string $role Le rôle (sécurité pour ne pas suspendre un admin par erreur).
     * @param int $status 0 pour suspendre, 1 pour activer.
     * @return int Le nombre de lignes modifiées (permet de savoir si le compte existait).
     */
    public function updateUserActiveStatus(string $email, string $role, int $status): int
    {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE email = ? AND role = ?");
        $stmt->execute([$status, $email, $role]);
        return $stmt->rowCount();
    }

    /**
     * Récupère les infos complètes du profil (utilisateur + rôles).
     *
     * Utilise une JOINTURE GAUCHE (LEFT JOIN) avec la table `profils_utilisateur`.
     * Cela permet de récupérer les indicateurs `est_chauffeur` et `est_passager`
     * en même temps que les infos de base.
     *
     * @param int $userId
     * @return mixed
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
     * Version simple sans jointure.
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
            "UPDATE utilisateurs SET pseudo = ?, email = ?, description = ? WHERE id = ?"
        );
        return $stmt->execute([$pseudo, $email, $description, $userId]);
    }
}
