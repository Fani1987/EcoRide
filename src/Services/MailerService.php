<?php

namespace App\Services;

// Importation des classes de la librairie PHPMailer via Composer
// PHPMailer est utilisé car la fonction mail() native de PHP est limitée et moins sécurisée.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class MailerService
 *
 * Ce service a pour Responsabilité Unique (SRP) de gérer l'envoi d'emails.
 * Il centralise la configuration SMTP pour éviter de dupliquer ce code dans chaque contrôleur.
 */
class MailerService
{
    /**
     * Envoie un email transactionnel.
     *
     * Cette méthode est statique pour être appelée facilement (ex: MailerService::send(...)).
     * Elle capture les erreurs silencieusement pour ne pas faire planter l'application si le mail échoue.
     *
     * @param string $toEmail L'adresse email du destinataire
     * @param string $toName  Le nom ou pseudo du destinataire (pour l'affichage)
     * @param string $subject Le sujet de l'email
     * @param string $body    Le contenu du message (accepte le HTML)
     * @return bool           Retourne true si envoyé, false sinon.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        // Création d'une nouvelle instance. 'true' active les exceptions en cas d'erreur.
        $mail = new PHPMailer(true);

        try {
            // =================================================================
            // 1. CONFIGURATION DU SERVEUR (SMTP)
            // =================================================================
            // On récupère les identifiants depuis le fichier .env (Sécurité : rien n'est écrit en dur)

            $mail->isSMTP();                                            // Utilisation du protocole SMTP
            $mail->Host       = $_ENV['MAIL_HOST'];                     // Adresse du serveur (ex: smtp.mailtrap.io)
            $mail->SMTPAuth   = true;                                   // Active l'authentification
            $mail->Username   = $_ENV['MAIL_USERNAME'];                 // Login SMTP
            $mail->Password   = $_ENV['MAIL_PASSWORD'];                 // Mot de passe SMTP
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Chiffrement TLS (Transport Layer Security)
            $mail->Port       = $_ENV['MAIL_PORT'];                     // Port (souvent 587 ou 2525)
            $mail->CharSet    = 'UTF-8';                                // Encodage pour gérer les accents

            // =================================================================
            // 2. EXPÉDITEUR ET DESTINATAIRE
            // =================================================================

            // L'expéditeur par défaut (Le site EcoRide)
            $mail->setFrom($_ENV['MAIL_USERNAME'], 'EcoRide Notification');

            // Ajout du destinataire passé en paramètre
            $mail->addAddress($toEmail, $toName);

            // (Optionnel) On pourrait ajouter ici un Reply-To si besoin :
            // $mail->addReplyTo('support@ecoride.fr', 'Support');

            // =================================================================
            // 3. CONTENU DU MESSAGE
            // =================================================================

            $mail->isHTML(true);                                  // Active le mode HTML
            $mail->Subject = $subject;                            // Définit le sujet

            // Corps du message en HTML (peut contenir des balises <b>, <br>, etc.)
            $mail->Body    = $body;

            // Corps alternatif (AltBody) en texte brut.
            // Essentiel pour :
            // 1. Les clients mails qui bloquent le HTML.
            // 2. L'accessibilité (lecteurs d'écran).
            // 3. Réduire le risque d'être classé comme SPAM.
            // On utilise strip_tags() pour nettoyer le HTML et ne garder que le texte.
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $body));

            // =================================================================
            // 4. ENVOI
            // =================================================================

            $mail->send();

            // Log de succès (visible dans les logs serveur, utile pour le développeur)
            error_log("EMAIL SERVICE: Succès - Envoi à $toEmail - Sujet: $subject");

            return true;
        } catch (Exception $e) {
            // =================================================================
            // 5. GESTION DES ERREURS
            // =================================================================
            // Si l'envoi échoue, PHPMailer lève une exception.

            // On enregistre l'erreur technique précise ($mail->ErrorInfo) dans les logs.
            error_log("EMAIL SERVICE: Échec d'envoi à $toEmail. Erreur: " . $mail->ErrorInfo);

            // On retourne false pour que le Contrôleur puisse gérer la suite
            // (ex: afficher un message "L'action a réussi mais l'email n'est pas parti")
            return false;
        }
    }
}
