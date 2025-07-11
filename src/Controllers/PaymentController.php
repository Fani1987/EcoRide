<?php

namespace App\Controllers;

use PDO;
use PDOException;

class PaymentController
{
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
        // Dans une vraie application, ici on appellerait une API de paiement (Stripe, PayPal...).
        // On enverrait le montant (par ex., $creditsToAdd . '€'), on attendrait la confirmation.
        // Pour notre projet, nous simulons simplement que le paiement a réussi.
        $paymentSuccess = true;

        if ($paymentSuccess) {
            try {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET credit = credit + ? WHERE id = ?");
                $stmt->execute([$creditsToAdd, $userId]);

                $_SESSION['message'] = ['type' => 'success', 'text' => $creditsToAdd . ' crédits ont été ajoutés à votre compte !'];
                header('Location: /profile');
                exit;
            } catch (PDOException $e) {
                error_log("Erreur lors de l'ajout de crédits : " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de la mise à jour de votre solde.'];
                header('Location: /buy-credits');
                exit;
            }
        }
    }
}
