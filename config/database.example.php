<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Connexion à la Base de Données
 * ============================================================
 * Fournit une connexion MySQLi unique et des fonctions d'assistance.
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Retourne la connexion MySQLi active. La connexion est initialisée
 * une seule fois et réutilisée (pattern de connexion globale).
 *
 * @return \mysqli
 */
function klg_get_db(): \mysqli
{
    static $connexion = null;

    if ($connexion === null) {
        $connexion = mysqli_connect('localhost', 'utilisateur_bdd', 'mot_de_passe', 'nom_base_de_donnees');

        if (!$connexion) {
            http_response_code(500);
            die(json_encode([
                'error' => true,
                'message' => 'Impossible de se connecter à la base de données.'
            ]));
        }

        mysqli_set_charset($connexion, 'utf8mb4');
    }

    return $connexion;
}

/**
 * Échappe une donnée entrante pour une insertion sécurisée en BDD.
 * (Couche de sécurité complémentaire — préférer les requêtes préparées
 * pour les nouvelles fonctions V2.)
 *
 * @param string $string
 * @return string
 */
function klg_db_escape(string $string): string
{
    return mysqli_real_escape_string(klg_get_db(), trim($string));
}

/**
 * Retourne la date et l'heure courante au format `YYYY-MM-DD HH:MM:SS`.
 *
 * @return string
 */
function klg_now(): string
{
    return date('Y-m-d H:i:s');
}
