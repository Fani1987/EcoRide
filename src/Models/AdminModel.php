<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Class AdminModel
 * * Ce modèle est dédié à l'espace d'administration.
 * Contrairement aux autres modèles qui gèrent des entités (User, Trajet),
 * celui-ci est spécialisé dans l'agrégation de données pour les statistiques.
 * * Rôle : Fournir des données chiffrées pour les graphiques (Chart.js).
 */
class AdminModel
{
    // Instance de connexion à la base de données (injectée)
    private $pdo;

    /**
     * Constructeur
     * * @param PDO $pdo L'instance de connexion générée par Database::getInstance()
     * On utilise l'Injection de Dépendance pour que le modèle ne soit pas
     * responsable de la création de la connexion, mais seulement de son utilisation.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère le nombre de covoiturages organisés par jour.
     * Sert à alimenter le graphique en barres du dashboard.
     * * @return array Tableau associatif avec 'jour' et 'nombre_covoiturages'
     */
    public function getCovoituragesParJour()
    {
        // 1. Requête SQL d'agrégation
        // - DATE(date_depart) : On extrait seulement la date (AAAA-MM-JJ) en ignorant l'heure.
        // - COUNT(*) : On compte le nombre de trajets pour chaque groupe.
        // - GROUP BY : On regroupe les résultats par jour unique.
        // - ORDER BY : On trie chronologiquement pour que le graphique soit lisible de gauche à droite.
        $sql = "
            SELECT DATE(date_depart) AS jour, COUNT(*) AS nombre_covoiturages
            FROM covoiturages
            GROUP BY DATE(date_depart)
            ORDER BY jour ASC
        ";

        // 2. Préparation et Exécution
        // Même s'il n'y a pas de paramètres variables ici, on utilise prepare()
        // par bonne pratique de sécurité et de performance (mise en cache du plan d'exécution).
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        // 3. Récupération
        // On retourne un tableau associatif prêt à être converti en JSON.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule les crédits générés par jour.
     * * Règle métier : 1 Covoiturage = 2 Crédits générés par la plateforme.
     * (C'est une simplification pour la démo, basée sur les frais de service).
     * * @return array Tableau associatif avec 'jour' et 'credits_gagnes'
     */
    public function getCreditsParJour()
    {
        // On effectue le calcul directement en SQL (COUNT(*) * 2).
        // C'est beaucoup plus performant que de récupérer toutes les lignes
        // en PHP et de faire une boucle pour multiplier.
        $sql = "
            SELECT DATE(date_depart) AS jour, (COUNT(*) * 2) AS credits_gagnes
            FROM covoiturages
            GROUP BY DATE(date_depart)
            ORDER BY jour ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le total absolu des crédits gagnés depuis le début.
     * Sert à afficher le gros chiffre (KPI) sur le tableau de bord.
     * * @return int Le nombre total de crédits.
     */
    public function getTotalCreditsGagnes()
    {
        // On additionne tous les trajets et on multiplie par 2 (frais fixes).
        // Si votre modèle économique changeait (frais variables), on ferait un SUM(prix).
        $sql = "SELECT COUNT(*) * 2 as total FROM covoiturages";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        // fetchColumn() est optimisé pour récupérer une seule valeur d'une seule ligne.
        return (int) $stmt->fetchColumn();
    }
}
