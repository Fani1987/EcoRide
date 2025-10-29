<?php

namespace App\Controllers;

use PDO;
use PDOException;
use App\Models\AdminModel; // <-- 1. On IMPORTE le nouveau Modèle

class AdminController
{
    /**
     * Récupère les statistiques pour le tableau de bord de l'administrateur.
     * Le contrôleur ne contient plus de SQL.
     */
    public static function getStats(PDO $pdo): void
    {
        header('Content-Type: application/json');

        try {
            // 2. On instancie le Modèle
            $adminModel = new AdminModel($pdo);

            // 3. On appelle les méthodes du Modèle
            $covoituragesParJour = $adminModel->getCovoituragesParJour();
            $creditsParJour = $adminModel->getCreditsParJour();
            $totalCredits = $adminModel->getTotalCreditsGagnes();

            // 4. Le contrôleur se charge de formater la réponse JSON
            echo json_encode([
                'covoiturages_par_jour' => $covoituragesParJour,
                'credits_par_jour' => $creditsParJour,
                'total_credits' => $totalCredits
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Erreur dans AdminController::getStats : " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la récupération des statistiques.']);
        }
    }
}
