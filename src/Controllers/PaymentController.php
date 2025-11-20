<?php

namespace App\Controllers;

use PDO;
use PDOException;
// On importe le modèle UserModel pour gérer la mise à jour du solde de crédits.
// Cela respecte la séparation MVC : le contrôleur ne fait pas de SQL.
use App\Models\UserModel;

/**
 * Class PaymentController
 * * Responsabilité : Gérer le processus d'achat de crédits.
 * * Note pour le jury : Dans le cadre de ce MVP (Minimum Viable Product), le paiement est simulé.
 * Une intégration réelle (Stripe/PayPal) se ferait ici à la place de la variable $paymentSuccess.
 */
class PaymentController
{
    /**
     * Traite le formulaire d'achat de crédits.
     *
     * @param PDO $pdo La connexion à la base de données.
     * @param array $postData Les données soumises par le formulaire ($_POST).
     */
    public static function processCreditPurchase(PDO $pdo, array $postData)
    {
        // 1. SÉCURITÉ : Vérification de l'authentification
        // Seul un membre connecté peut acheter des crédits.
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        $userId = $_SESSION['user_id'];

        // 2. RÉCUPÉRATION & VALIDATION
        // On s'assure que la valeur est un entier positif.
        $creditsToAdd = (int)($postData['credit_pack'] ?? 0);

        if ($creditsToAdd <= 0) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Veuillez sélectionner un pack de crédits valide.'];
            header('Location: /buy-credits');
            exit;
        }

        // 3. SIMULATION DU PAIEMENT
        // Ici, on appellerait l'API de Stripe ou PayPal.
        // Pour l'exercice, on considère que le paiement est toujours accepté.
        $paymentSuccess = true;

        if ($paymentSuccess) {
            try {
                // 4. APPEL AU MODÈLE (Mise à jour des données)
                // Le paiement est validé, on crédite le compte utilisateur via le modèle.
                $userModel = new UserModel($pdo);

                // La méthode creditCredits s'occupe de la requête UPDATE sécurisée.
                $userModel->creditCredits($userId, $creditsToAdd);

                // 5. FEEDBACK UTILISATEUR
                $_SESSION['message'] = ['type' => 'success', 'text' => $creditsToAdd . ' crédits ont été ajoutés à votre compte !'];
                header('Location: /profile'); // Redirection vers le profil pour voir le nouveau solde
                exit;
            } catch (PDOException $e) {
                // 6. GESTION DES ERREURS BDD
                // Si la mise à jour échoue (ex: base de données inaccessible)
                error_log("Erreur lors de l'ajout de crédits : " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Une erreur est survenue lors de la mise à jour de votre solde.'];
                header('Location: /buy-credits');
                exit;
            }
        } else {
            // Logique si le paiement échoue (Simulation)
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Le paiement a échoué.'];
            header('Location: /buy-credits');
            exit;
        }
    }
}
