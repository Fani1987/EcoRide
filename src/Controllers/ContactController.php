<?php

namespace App\Controllers;

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class ContactController
 * * Responsabilité : Gérer la page de contact et le traitement du formulaire.
 * Architecture : Hybride (Envoi d'Email Transactionnel + Sauvegarde NoSQL).
 */
class ContactController
{
    /**
     * Point d'entrée principal pour le traitement du formulaire.
     * Orchestre les différentes actions via des méthodes privées.
     */
    public static function handleContactForm(array $postData)
    {
        // 1. Nettoyage des entrées (Sécurité XSS)
        $pseudo = trim($postData['pseudo'] ?? '');
        $email = trim($postData['email'] ?? '');
        $sujet = trim($postData['sujet'] ?? '');
        $message = trim($postData['message'] ?? '');

        // 2. Validation des données
        // On utilise FILTER_VALIDATE_EMAIL pour être sûr que le format est correct
        if (empty($pseudo) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($sujet) || empty($message)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tous les champs sont requis et l\'email doit être valide.'];
            header('Location: /contact');
            exit;
        }

        // 3. Exécution des tâches (Séparation des responsabilités)
        // On délègue l'envoi du mail et la sauvegarde BDD à des méthodes dédiées.
        // Cela rend le code principal plus lisible.
        $mailSent = self::sendContactEmail($pseudo, $email, $sujet, $message);
        $mongoSaved = self::saveContactMessageToMongo($pseudo, $email, $sujet, $message);

        // 4. Gestion des retours utilisateur
        if ($mailSent && $mongoSaved) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Votre message a bien été envoyé par email et enregistré en base.'];
        } elseif ($mailSent) {
            // Cas rare : Email parti mais base de données inaccessible
            $_SESSION['message'] = ['type' => 'warning', 'text' => 'Message envoyé, mais une erreur de sauvegarde est survenue.'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Erreur technique : le message n\'a pas pu être envoyé.'];
        }

        // 5. Redirection
        header('Location: /contact');
        exit;
    }

    /**
     * Méthode Privée : Gère uniquement la configuration et l'envoi via PHPMailer.
     * Utilise les variables d'environnement pour ne pas exposer les mots de passe.
     */
    private static function sendContactEmail(string $pseudo, string $email, string $sujet, string $message): bool
    {
        $mail = new PHPMailer(true);
        try {
            // Configuration SMTP (issue du fichier .env non versionné)
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['MAIL_PORT'];
            $mail->CharSet    = 'UTF-8'; // Important pour les accents

            // En-têtes de l'email
            $mail->setFrom($_ENV['MAIL_USERNAME'], 'EcoRide Contact');
            $mail->addAddress('contact@ecoride.fr'); // Destinataire final (Admin du site)
            $mail->addReplyTo($email, $pseudo); // Pour répondre directement à l'utilisateur

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = 'Contact : ' . htmlspecialchars($sujet);
            $mail->Body    = "De: <b>" . htmlspecialchars($pseudo) . "</b> ($email)<br><br>" . nl2br(htmlspecialchars($message));

            // En production, on décommenterait la ligne suivante :
            // $mail->send();

            // Pour le développement/démo, on simule le succès dans les logs
            error_log("SIMULATION EMAIL: De $email, Sujet: $sujet");
            return true;
        } catch (Exception $e) {
            error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Méthode Privée : Gère uniquement l'insertion dans MongoDB (NoSQL).
     * Stocke le message sous forme de document JSON.
     */
    private static function saveContactMessageToMongo(string $pseudo, string $email, string $sujet, string $message): bool
    {
        try {
            // 1. Connexion au serveur MongoDB (via la classe Database)
            $client = Database::getMongoClient();

            // 2. Sélection de la base et de la collection
            $database = $client->selectDatabase($_ENV['MONGO_DB_NAME']);
            $collection = $database->selectCollection('demandes_contact');

            // 3. Création du document (Structure flexible)
            $document = [
                'nom' => $pseudo,
                'email' => $email,
                'sujet' => $sujet,
                'message' => $message,
                'date_envoi' => new \MongoDB\BSON\UTCDateTime(), // Date au format Mongo
                'statut' => 'non_lu' // Champ utile pour un futur back-office
            ];

            // 4. Insertion
            $result = $collection->insertOne($document);

            // Retourne vrai si 1 document a été inséré
            return $result->getInsertedCount() == 1;
        } catch (\Exception $e) {
            error_log("Erreur MongoDB Contact : " . $e->getMessage());
            return false;
        }
    }
}
