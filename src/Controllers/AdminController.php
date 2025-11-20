<?php

namespace App\Controllers;

use PDO;
use PDOException;
// Import du modèle qui contient les requêtes SQL spécifiques à l'administration
use App\Models\AdminModel;

/**
 * Class AdminController
 * * Cette classe gère la logique métier liée à l'espace administrateur.
 * Elle agit comme une API pour le tableau de bord (Dashboard), fournissant les données
 * nécessaires aux graphiques via des réponses JSON.
 */
class AdminController
{
    /**
     * Récupère les statistiques globales de l'application.
     * * Cette méthode est appelée via une requête AJAX (fetch) depuis le JavaScript du tableau de bord.
     * Elle n'affiche pas de vue (HTML) mais renvoie des données brutes (JSON).
     * * @param PDO $pdo L'instance de connexion à la base de données (Injection de dépendance)
     * @return void    La méthode fait un 'echo' direct du JSON.
     */
    public static function getStats(PDO $pdo): void
    {
        // 1. Définition du type de contenu
        // On indique au navigateur (et au script JS qui a fait l'appel) que la réponse
        // sera au format JSON, et non du HTML. C'est indispensable pour une API.
        header('Content-Type: application/json');

        try {
            // 2. Instanciation du Modèle (MVC)
            // Le contrôleur ne fait pas de SQL lui-même. Il délègue cette tâche au modèle AdminModel.
            $adminModel = new AdminModel($pdo);

            // 3. Récupération des données
            // On appelle les différentes méthodes du modèle pour obtenir les chiffres clés.

            // a. Données pour le graphique en barres (Covoiturages par jour)
            $covoituragesParJour = $adminModel->getCovoituragesParJour();

            // b. Données pour le graphique en ligne (Crédits générés par jour)
            $creditsParJour = $adminModel->getCreditsParJour();

            // c. Donnée pour le compteur total (KPI)
            $totalCredits = $adminModel->getTotalCreditsGagnes();

            // 4. Envoi de la réponse
            // On regroupe toutes les données dans un tableau associatif et on le convertit en JSON.
            // C'est ce JSON que le JavaScript (admin.php) va recevoir et utiliser pour dessiner les graphiques.
            echo json_encode([
                'covoiturages_par_jour' => $covoituragesParJour,
                'credits_par_jour' => $creditsParJour,
                'total_credits' => $totalCredits
            ]);
        } catch (PDOException $e) {
            // 5. Gestion des erreurs

            // En cas de problème SQL, on renvoie un code HTTP 500 (Erreur Serveur).
            // Cela permet au JavaScript de savoir que la requête a échoué (le bloc .catch() du JS).
            http_response_code(500);

            // On enregistre l'erreur technique dans les logs du serveur pour le développeur.
            // (On ne l'affiche pas à l'utilisateur pour des raisons de sécurité).
            error_log("Erreur dans AdminController::getStats : " . $e->getMessage());

            // On renvoie un message d'erreur propre au format JSON.
            echo json_encode(['error' => 'Erreur lors de la récupération des statistiques.']);
        }
    }
}
