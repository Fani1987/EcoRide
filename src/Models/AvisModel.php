<?php

namespace App\Models;

use PDO;
use PDOException;

class AvisModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Met à jour le statut d'un avis (ex: 'validé', 'refusé')
     */
    public function updateStatus(int $avisId, string $statut): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE avis SET statut = ? WHERE id = ?");
            return $stmt->execute([$statut, $avisId]);
        } catch (PDOException $e) {
            error_log('Erreur AvisModel::updateStatus : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée un nouvel avis.
     * [cite_start]Le statut est 'en_attente' par défaut
     */
    public function create(int $userId, int $covoiturageId, int $note, string $commentaire): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO avis (utilisateur_id, covoiturage_id, note, commentaire, statut) 
                 VALUES (?, ?, ?, ?, 'en_attente')"
            );
            return $stmt->execute([$userId, $covoiturageId, $note, $commentaire]);
        } catch (PDOException $e) {
            error_log('Erreur AvisModel::create : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les avis (validés) sur un chauffeur.
     * Cette méthode est nécessaire pour la page de profil.
     */
    public function getChauffeurAvisValides(int $chauffeurId)
    {
        try {
            $stmtAvis = $this->pdo->prepare("SELECT a.note, a.commentaire, u_auteur.pseudo AS auteur 
                                            FROM avis a 
                                            JOIN covoiturages c ON a.covoiturage_id = c.id
                                            JOIN utilisateurs u_auteur ON a.utilisateur_id = u_auteur.id 
                                            WHERE c.chauffeur_id = ? AND a.statut = 'validé'
                                            ORDER BY a.id DESC");
            $stmtAvis->execute([$chauffeurId]);
            return $stmtAvis->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur AvisModel::getChauffeurAvisValides : ' . $e->getMessage());
            return []; // Retourne un tableau vide en cas d'erreur
        }
    }

    /**
     * Récupère les avis par statut ('en_attente', 'validé', 'refusé').
     */
    public function getByStatus(string $statut)
    {
        try {
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
