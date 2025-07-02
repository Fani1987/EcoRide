<?php

class UserController
{
    /**
     * Affiche la page de profil de l'utilisateur connecté.
     *
     * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
     * @param int $userId L'ID de l'utilisateur dont on veut afficher le profil.
     */
    public static function showProfilePage($pdo, $userId)
    {
        // 1. Récupération des informations de l'utilisateur
        // Récupération de la note_moyenne directement depuis la table utilisateurs
        $stmt = $pdo->prepare("
            SELECT u.id, u.pseudo, u.email, u.description, pu.est_chauffeur, pu.est_passager, u.note_moyenne
            FROM utilisateurs u
            JOIN profils_utilisateur pu ON u.id = pu.utilisateur_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // L'utilisateur n'existe pas ou n'a pas de profil
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Profil utilisateur introuvable.'];
            header("Location: /login"); // Rediriger vers la page de connexion
            exit;
        }

        // Déterminer le type de l'utilisateur
        $type_utilisateur = '';
        if ($user['est_chauffeur'] && $user['est_passager']) {
            $type_utilisateur = 'Chauffeur et Passager';
        } elseif ($user['est_chauffeur']) {
            $type_utilisateur = 'Chauffeur';
        } elseif ($user['est_passager']) {
            $type_utilisateur = 'Passager';
        }

        // 2. Récupération des véhicules de l'utilisateur (s'il est chauffeur)
        $vehicules = [];
        if ($user['est_chauffeur']) {
            $stmt = $pdo->prepare("SELECT id, marque, modele, energie, couleur, plaque_immatriculation FROM vehicules WHERE utilisateur_id = ?");
            $stmt->execute([$userId]);
            $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Récupération de l'historique des trajets proposés par l'utilisateur (s'il est chauffeur)
        $trajets_proposes = [];
        if ($user['est_chauffeur']) {
            $stmt = $pdo->prepare("SELECT id, depart, arrivee, date_depart FROM covoiturages WHERE chauffeur_id = ? ORDER BY date_depart DESC");
            $stmt->execute([$userId]);
            $trajets_proposes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Récupération de l'historique des trajets réservés par l'utilisateur (s'il est passager)
        $trajets_reserves = [];
        if ($user['est_passager']) {
            $stmt = $pdo->prepare("
                SELECT c.id, c.depart, c.arrivee, c.date_depart, u.pseudo as chauffeur_pseudo
                FROM reservations r
                JOIN covoiturages c ON r.covoiturage_id = c.id
                JOIN utilisateurs u ON c.chauffeur_id = u.id
                WHERE r.utilisateur_id = ?
                ORDER BY c.date_depart DESC
            ");
            $stmt->execute([$userId]);
            $trajets_reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Récupération des avis *reçus* par cet utilisateur (s'il est chauffeur)
        // La table 'avis' est l'ancienne table 'notations'.
        // Nous allons récupérer les notes et commentaires laissés sur les covoiturages de cet utilisateur (qui est le chauffeur).
        $avis_recus = []; // Renommé de $avis à $avis_recus pour plus de clarté
        if ($user['est_chauffeur']) { // N'afficher les avis que si l'utilisateur est un chauffeur
            $stmt = $pdo->prepare("
                SELECT
                    a.note,
                    a.commentaire,
                    u.pseudo AS passager_pseudo, -- C'est l'utilisateur qui a donné l'avis
                    c.depart,
                    c.arrivee,
                    c.date_depart -- Informations sur le covoiturage concerné
                FROM
                    avis a
                JOIN
                    covoiturages c ON a.covoiturage_id = c.id
                JOIN
                    utilisateurs u ON a.utilisateur_id = u.id -- Jointure pour obtenir le pseudo du passager
                WHERE
                    c.chauffeur_id = ? -- Filtre sur l'ID du chauffeur du covoiturage
                ORDER BY
                    a.id DESC -- Assurez-vous d'avoir une colonne de date dans 'avis' si vous voulez trier par date
            ");
            $stmt->execute([$userId]);
            $avis_recus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }


        // Préparer les données à passer à la vue
        $data = [
            'user' => $user, // Contient maintenant 'note_moyenne'
            'type_utilisateur' => $type_utilisateur,
            'vehicules' => $vehicules,
            'trajets_proposes' => $trajets_proposes,
            'trajets_reserves' => $trajets_reserves,
            'avis_recus' => $avis_recus // Utiliser le nouveau nom de variable
        ];

        // Rendre la vue 'profile.html' avec les données
        renderView('profile', $data);
    }

    // Vous pourriez ajouter d'autres méthodes ici pour la gestion du profil,
    // comme l'édition des informations, l'ajout de véhicule, etc.
}
