<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class AvisModel
 *
 * Ce Modèle est responsable de toutes les interactions avec la table `avis` de la base de données.
 * Il fait partie de la couche "M" (Modèle) de l'architecture MVC.
 *
 * Ses responsabilités principales sont :
 * 1. Créer des avis (avec un statut par défaut).
 * 2. Lire les avis (filtrés par statut ou par chauffeur).
 * 3. Mettre à jour le statut des avis (pour la modération).
 */
class AvisModel
{
    /**
     * @var PDO Instance de connexion à la base de données.
     * On utilise l'injection de dépendance pour découpler la configuration de la BDD du Modèle.
     */
    private $pdo;

    /**
     * Constructeur de la classe.
     *
     * @param PDO $pdo L'objet PDO déjà configuré (venant de Database::getInstance()).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Met à jour le statut d'un avis.
     * Cette méthode est utilisée par les Employés pour la modération.
     *
     * @param int $avisId L'ID unique de l'avis à modérer.
     * @param string $statut Le nouveau statut ('validé' ou 'refusé').
     * @return bool Retourne true si la mise à jour a réussi, false sinon.
     */
    public function updateStatus(int $avisId, string $statut): bool
    {
        try {
            // 1. Préparation de la requête SQL
            // On utilise une requête préparée pour éviter les Injections SQL.
            // Le '?' sert de marqueur pour les données qui seront injectées plus tard.
            $stmt = $this->pdo->prepare("UPDATE avis SET statut = ? WHERE id = ?");

            // 2. Exécution
            // On passe les variables dans un tableau. PDO s'occupe de l'échappement.
            return $stmt->execute([$statut, $avisId]);
        } catch (PDOException $e) {
            // Gestion des erreurs : On ne laisse pas l'application planter.
            // On enregistre l'erreur dans les logs serveur pour le développeur.
            error_log('Erreur AvisModel::updateStatus : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre un nouvel avis dans la base de données.
     *
     * Règle Métier importante :
     * [cite_start]Tout nouvel avis est inséré avec le statut 'en_attente' par défaut[cite: 47].
     * Il ne sera visible sur le profil public qu'après validation par un employé.
     *
     * @param int $userId L'ID du passager qui laisse l'avis (auteur).
     * @param int $covoiturageId L'ID du trajet concerné.
     * @param int $note La note (généralement sur 5).
     * @param string $commentaire Le texte de l'avis.
     * @return bool Succès ou échec de l'insertion.
     */
    public function create(int $userId, int $covoiturageId, int $note, string $commentaire): bool
    {
        try {
            // On prépare l'insertion. Notez que 'statut' est forcé à 'en_attente' en dur dans la requête.
            // Cela garantit qu'aucun utilisateur ne peut contourner la modération.
            $stmt = $this->pdo->prepare(
                "INSERT INTO avis (utilisateur_id, covoiturage_id, note, commentaire, statut) 
                 VALUES (?, ?, ?, ?, 'en_attente')"
            );

            // Exécution avec les données fournies par le contrôleur.
            return $stmt->execute([$userId, $covoiturageId, $note, $commentaire]);
        } catch (PDOException $e) {
            // En cas d'erreur (ex: clé étrangère invalide, base inaccessible), on loggue et on retourne false.
            error_log('Erreur AvisModel::create : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère uniquement les avis VALIDÉS pour un chauffeur donné.
     * Cette méthode sert à l'affichage public sur la page de profil (Profile).
     *
     * On utilise une JOINTURE (JOIN) complexe pour récupérer :
     * 1. Les détails de l'avis (note, commentaire).
     * 2. Le pseudo de l'auteur (via la table `utilisateurs`).
     * 3. On filtre par chauffeur via la table `covoiturages`.
     *
     * @param int $chauffeurId L'ID du chauffeur dont on veut voir la réputation.
     * @return array Un tableau associatif contenant les avis.
     */
    public function getChauffeurAvisValides(int $chauffeurId)
    {
        try {
            // La requête SQL :
            // - SELECT : On choisit les champs utiles (note, commentaire, pseudo de l'auteur).
            // - JOIN covoiturages : Pour lier l'avis au trajet et donc au chauffeur du trajet.
            // - JOIN utilisateurs : Pour récupérer le nom de celui qui a écrit l'avis.
            // - WHERE : On filtre sur l'ID du chauffeur ET (très important) sur le statut 'validé'.
            // - ORDER BY : Les plus récents en premier.
            $stmtAvis = $this->pdo->prepare("
                SELECT a.note, a.commentaire, u_auteur.pseudo AS auteur 
                FROM avis a 
                JOIN covoiturages c ON a.covoiturage_id = c.id
                JOIN utilisateurs u_auteur ON a.utilisateur_id = u_auteur.id 
                WHERE c.chauffeur_id = ? AND a.statut = 'validé'
                ORDER BY a.id DESC
            ");

            $stmtAvis->execute([$chauffeurId]);

            // fetchAll récupère toutes les lignes trouvées.
            return $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur AvisModel::getChauffeurAvisValides : ' . $e->getMessage());
            return []; // Retourne un tableau vide pour ne pas casser l'affichage de la vue.
        }
    }

    /**
     * Récupère les avis filtrés par leur statut.
     * Cette méthode est utilisée principalement par le tableau de bord des employés (Back-office).
     * Elle permet d'afficher la liste des avis "en_attente" pour les modérer.
     *
     * @param string $statut Le statut recherché (ex: 'en_attente', 'validé').
     * @return array Tableau des avis correspondants.
     */
    public function getByStatus(string $statut)
    {
        try {
            // On récupère l'ID, le nom de l'auteur, la note et le commentaire.
            // C'est tout ce dont l'employé a besoin pour juger.
            $stmt = $this->pdo->prepare(
                "SELECT a.id, u.pseudo AS nom, a.note, a.commentaire 
                 FROM avis a 
                 JOIN utilisateurs u ON a.utilisateur_id = u.id 
                 WHERE a.statut = ?"
            );

            $stmt->execute([$statut]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur AvisModel::getByStatus : ' . $e->getMessage());
            return [];
        }
    }
}
