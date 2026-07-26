<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Configuration Globale
 * ===================================================
 * Définit les constantes et chemins utilisés dans toute l'application.
 */

// --- Chemins Racines ---
define('ROOT_PATH',     dirname(__DIR__));
define('SRC_PATH',      ROOT_PATH . '/src');
define('KEYS_PATH',     ROOT_PATH . '/keys');
define('PUBLIC_PATH',   ROOT_PATH . '/public');

// --- Clé Privée RSA (Autorité de Signature des Licences V2) ---
// La clé privée ne doit JAMAIS être accessible depuis le web.
// Elle est stockée hors du dossier public/, dans keys/.
define('RSA_PRIVATE_KEY_PATH', KEYS_PATH . '/klg_private_key.pem');

// --- Clé Secrète Partagée HMAC-SHA256 (Révocations Hors-ligne) ---
// Cette clé doit être identique à celle utilisée dans KLG-Caisse V2
// pour générer les codes de révocation.
// À stocker idéalement dans une variable d'environnement en production.
define('HMAC_SHARED_SECRET', 'KLG-CAISSE-REVOCATION-SECRET-2026');

// --- Paramètres de l'Application ---
define('APP_NAME',      'KLG-Caisse License Manager');
define('APP_VERSION',   '2.0.0');
define('APP_TIMEZONE',  'Africa/Porto-Novo');

// --- Préfixe des Tables V2 ---
define('DB_PREFIX_V2', 'klg_c_lm_');

// --- Fuseau horaire par défaut ---
date_default_timezone_set(APP_TIMEZONE);
