<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Journal d'Audit
 * =============================================
 * Compatibilité minimum : PHP 7.4.33
 *
 * Enregistre chaque action sensible de l'éditeur dans la table
 * klg_c_lm_audit_logs. Le journal est immuable (pas de UPDATE/DELETE).
 */

require_once dirname(__DIR__, 3) . '/config/database.php';


/**
 * Actions prédéfinies pour le journal d'audit.
 * Utiliser ces constantes garantit l'uniformité des libellés.
 */
define('AUDIT_GENERATION_JWT',      'GENERATION_JWT');
define('AUDIT_REVOCATION_LICENCE',  'REVOCATION_LICENCE');
define('AUDIT_MODIFICATION_HWID',   'MODIFICATION_HWID');
define('AUDIT_CREATION_CLIENT',     'CREATION_CLIENT');
define('AUDIT_CONSULTATION',        'CONSULTATION');


/**
 * Enregistre une action dans le journal d'audit immuable.
 *
 * @param string      $action   Type d'action (utiliser les constantes AUDIT_*).
 * @param string|null $cible    Identifiant de la ressource concernée (ex: "V2-2026-ABCD").
 * @param array       $details  Données additionnelles sous forme de tableau (sérialisé en JSON).
 * @return bool True si l'enregistrement a réussi.
 */
function klg_log_action(string $action, ?string $cible = null, array $details = []): bool
{
    $db          = klg_get_db();
    $ip          = klg_get_ip_visiteur();
    $details_json = empty($details) ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = mysqli_prepare($db, 'INSERT INTO klg_c_lm_audit_logs (action, cible, details, ip_address) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ssss', $action, $cible, $details_json, $ip);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}


/**
 * Retourne les N dernières entrées du journal d'audit.
 *
 * @param int $limite Nombre maximum de lignes à retourner.
 * @return array
 */
function klg_audit_get_recent(int $limite = 50): array
{
    $db   = klg_get_db();
    $stmt = mysqli_prepare($db, 'SELECT * FROM klg_c_lm_audit_logs ORDER BY date_action DESC LIMIT ?');
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'i', $limite);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['details'] = !empty($row['details']) ? (json_decode($row['details'], true) ?? []) : [];
        $logs[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $logs;
}


/**
 * Retourne l'adresse IP du visiteur actuel (prend en compte les proxies courants).
 *
 * @return string
 */
function klg_get_ip_visiteur(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
