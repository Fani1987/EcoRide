<?php

namespace App\Models;

use PDO;
use PDOException;

class AdminModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère le nombre de covoiturages par jour.
     */
    public function getCovoituragesParJour()
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(date_depart) AS jour, COUNT(*) AS nombre_covoiturages
            FROM covoiturages
            GROUP BY DATE(date_depart)
            ORDER BY jour ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les crédits gagnés par jour.
     */
    public function getCreditsParJour()
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(date_depart) AS jour, (COUNT(*) * 2) AS credits_gagnes
            FROM covoiturages
            GROUP BY DATE(date_depart)
            ORDER BY jour ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le total des crédits gagnés.
     */
    public function getTotalCreditsGagnes()
    {
        $stmt = $this->pdo->prepare("
            SELECT (COUNT(*) * 2) AS total_credits
            FROM covoiturages
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_credits'] ?? 0;
    }
}
