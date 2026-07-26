<?php
declare(strict_types=1);
/**
 * KLG-Caisse License Manager — Routeur Principal
 * ================================================
 * Point d'entrée unique de l'application.
 * Ce fichier :
 *   1. Intercepte les appels à l'API Legacy V1 (clients anciens)
 *   2. Route vers les vues du back-office éditeur
 *
 * URL de l'application : http://localhost/klg-caisse-licence-manager/public/
 *
 * API V1 (legacy) : http://localhost/klg-caisse-licence-manager/public/?action=...
 * Back-office     : http://localhost/klg-caisse-licence-manager/public/?page=...
 */

// -- Chargement de la configuration de base --
require_once dirname(__DIR__) . '/config/config.php';

// =================================================================
// ROUTE 1 : API LEGACY V1
// Interceptée en priorité pour les clients KLG-Caisse anciens.
// =================================================================
$api_actions_v1 = [
    'get-data-licence',
    'update-licence',
    'desactiver-licence',
    'get-latest-app-version',
    'check-update',
];

if (isset($_GET['action']) && in_array($_GET['action'], $api_actions_v1, true)) {
    require_once SRC_PATH . '/api/v1_legacy_api.php';
    klg_v1_dispatcher($_GET['action']);
    exit;
}

// =================================================================
// ROUTE 2 : BACK-OFFICE ÉDITEUR
// =================================================================
$page = isset($_GET['page']) ? preg_replace('/[^a-z_]/', '', strtolower((string)$_GET['page'])) : 'dashboard';

$vues_autorisees = [
    'dashboard'      => SRC_PATH . '/views/dashboard.php',
    'create_licence' => SRC_PATH . '/views/create_licence.php',
    'list_licences'  => SRC_PATH . '/views/list_licences.php',
    'revoke_licence' => SRC_PATH . '/views/revoke_licence.php',
    'audit_logs'     => SRC_PATH . '/views/audit_logs.php',
];

if (array_key_exists($page, $vues_autorisees) && is_file($vues_autorisees[$page])) {
    require_once $vues_autorisees[$page];
} else {
    // Page inconnue → redirection vers le tableau de bord
    header('Location: ?page=dashboard');
    exit;
}
