<?php

namespace App\Controllers;

use PDO;
use PDOException;
use App\Models\UserModel; // <-- 1. On IMPORTE le UserModel

class PaymentController
{
    /**
     * Gère la "simulation" d'achat de crédits.
     */
    public static function processCreditPurchase(PDO $pdo, array $postData)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        $userId = $_SESSION['user_id'];
        $creditsToAdd = (int)($postData['credit_pack'] ?? 0);

        if ($creditsToAdd <= 0) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez sélectionner un pack de crédits valide.'];
            header('Location: /buy-credits');
            exit;
        }

        // --- SIMULATION DU PAIEMENT ---
        $paymentSuccess = true; // Simulation de paiement réussi

        if ($paymentSuccess) {
            try {
                // 2. Instancier le Modèle
                $userModel = new UserModel($pdo);

                // 3. Appeler la méthode du Modèle (plus de SQL ici !)
                $userModel->creditCredits($userId, $creditsToAdd);

                $_SESSION['message'] = ['type' => 'success', 'text' => $creditsToAdd . ' crédits ont été ajoutés à votre compte !'];
                header('Location: /profile');
                exit;
            } catch (PDOException $e) {
                error_log("Erreur lors de l'ajout de crédits : " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de la mise à jour de votre solde.'];
                header('Location: /buy-credits');
                exit;
            }
        } else {
            // Logique si le paiement échoue
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le paiement a échoué.'];
            header('Location: /buy-credits');
            exit;
        }
    }
}
