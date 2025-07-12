<?php

namespace App\Controllers;

use PDO;
use PDOException;

class AdminController
{
    public static function getStats(PDO $pdo): void
    {
        header('Content-Type: application/json');

        try {
            // Requête 1 : Nombre de covoiturages par jour (cette requête était déjà bonne).
            $stmt1 = $pdo->prepare("
                SELECT DATE(date_depart) AS jour, COUNT(*) AS nombre_covoiturages
                FROM covoiturages
                GROUP BY DATE(date_depart)
                ORDER BY jour ASC
            ");
            $stmt1->execute();
            $covoituragesParJour = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            // Requête 2 : Crédits gagnés par jour (2 crédits par trajet).
            $stmt2 = $pdo->prepare("
                SELECT DATE(date_depart) AS jour, (COUNT(*) * 2) AS credits_gagnes
                FROM covoiturages
                GROUP BY DATE(date_depart)
                ORDER BY jour ASC
            ");
            $stmt2->execute();
            $creditsParJour = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Requête 3 : Total des crédits gagnés par la plateforme.
            $stmt3 = $pdo->prepare("
                SELECT (COUNT(*) * 2) AS total_credits
                FROM covoiturages
            ");
            $stmt3->execute();
            $totalCredits = $stmt3->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'covoiturages_par_jour' => $covoituragesParJour,
                'credits_par_jour' => $creditsParJour,
                'total_credits' => $totalCredits['total_credits'] ?? 0
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des statistiques.']);
        }
    }
}
