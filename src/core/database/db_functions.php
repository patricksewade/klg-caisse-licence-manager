<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Accès aux Données (Tables V2)
 * ===========================================================
 * Compatibilité minimum : PHP 7.4.33
 *
 * Fournit les fonctions d'accès aux tables klg_c_lm_* pour
 * la création, la mise à jour et la consultation des licences V2.
 *
 * Toutes les requêtes en écriture utilisent des requêtes préparées MySQLi.
 */

require_once dirname(__DIR__, 3) . '/config/database.php';


// ================================================================
// CLIENTS
// ================================================================

/**
 * Insère un nouveau client en base ou retourne l'ID s'il existe déjà (par email).
 *
 * @param string $nom_prenom
 * @param string $telephone
 * @param string $email
 * @param string $entreprise
 * @return int|null L'ID du client, ou null en cas d'erreur.
 */
function klg_db_upsert_client(string $nom_prenom, string $telephone, string $email, string $entreprise = ''): ?int
{
    $db = klg_get_db();

    // Vérifier si le client existe déjà par email
    if (!empty($email)) {
        $stmt = mysqli_prepare($db, 'SELECT id FROM klg_c_lm_clients WHERE email = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row    = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($row) {
                return (int) $row['id'];
            }
        }
    }

    // Créer le client
    $stmt = mysqli_prepare($db, 'INSERT INTO klg_c_lm_clients (nom_prenom, entreprise, telephone, email) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ssss', $nom_prenom, $entreprise, $telephone, $email);
    $ok = mysqli_stmt_execute($stmt);
    $id = $ok ? (int) mysqli_insert_id($db) : null;
    mysqli_stmt_close($stmt);

    return $id;
}


/**
 * Retourne tous les clients avec le nombre de licences associées.
 *
 * @return array
 */
function klg_db_get_all_clients(): array
{
    $db  = klg_get_db();
    $sql = 'SELECT c.*, COUNT(l.id) AS nb_licences
            FROM klg_c_lm_clients c
            LEFT JOIN klg_c_lm_licences l ON l.client_id = c.id
            GROUP BY c.id
            ORDER BY c.date_create DESC';

    $result = mysqli_query($db, $sql);
    if (!$result) {
        return [];
    }

    $clients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clients[] = $row;
    }

    return $clients;
}


// ================================================================
// LICENCES V2
// ================================================================

/**
 * Insère une nouvelle licence V2 en base de données.
 *
 * @param int    $client_id
 * @param string $cle_licence
 * @param string $type_licence
 * @param string $hwid
 * @param string $jwt_token
 * @param int    $duree
 * @param string $unite
 * @param string $date_expiration  'YYYY-MM-DD HH:MM:SS' ou '' si PERPETUELLE
 * @param array  $features
 * @return int|null L'ID de la licence insérée, ou null en cas d'erreur.
 */
function klg_db_inserer_licence_v2(
    int    $client_id,
    string $cle_licence,
    string $type_licence,
    string $hwid,
    string $jwt_token,
    int    $duree,
    string $unite,
    string $date_expiration = '',
    array  $features = []
): ?int {
    $db             = klg_get_db();
    $date_now       = klg_now();
    $date_exp_sql   = empty($date_expiration) ? null : $date_expiration;
    $features_json  = empty($features) ? null : json_encode($features, JSON_UNESCAPED_UNICODE);

    $sql = 'INSERT INTO klg_c_lm_licences
            (client_id, cle_licence, version, type_licence, hwid, jwt_token,
             duree_periode_utilisation, unite_temps_periode_utilisation,
             date_first_activation, date_expiration, statut, features, date_emission)
            VALUES (?, ?, 2, ?, ?, ?, ?, ?, ?, ?, \'ACTIVE\', ?, ?)';

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt, 'isssssissss',
        $client_id, $cle_licence, $type_licence, $hwid, $jwt_token,
        $duree, $unite, $date_now, $date_exp_sql, $features_json, $date_now
    );

    $ok = mysqli_stmt_execute($stmt);
    $id = $ok ? (int) mysqli_insert_id($db) : null;
    mysqli_stmt_close($stmt);

    return $id;
}


/**
 * Retourne toutes les licences avec les informations client associées.
 *
 * @param string|null $filtre_statut  'ACTIVE'|'EXPIREE'|'REVOQUEE'|'MIGREE'|null (tous)
 * @return array
 */
function klg_db_get_all_licences(?string $filtre_statut = null): array
{
    $db  = klg_get_db();
    
    // Requête pour les licences V2
    $sql_v2 = "SELECT l.id, l.client_id, l.cle_licence, l.version, l.type_licence, l.statut, l.date_expiration, l.date_emission, l.features,
                      c.nom_prenom, c.entreprise, c.telephone, c.email
               FROM klg_c_lm_licences l
               INNER JOIN klg_c_lm_clients c ON c.id = l.client_id";
               
    // Requête pour les licences V1
    $sql_v1 = "SELECT 0 as id, 0 as client_id, cle_licence, 1 as version, type_licence, 
                      CASE 
                          WHEN licence_is_locked = '1' THEN 'REVOQUEE'
                          WHEN type_licence = 'PERPETUELLE' THEN 'ACTIVE'
                          WHEN date_expiration_licence IS NOT NULL AND date_expiration_licence != '0000-00-00 00:00:00' AND date_expiration_licence < NOW() THEN 'EXPIREE'
                          ELSE 'ACTIVE'
                      END as statut, 
                      CASE 
                          WHEN type_licence = 'PERPETUELLE' OR date_expiration_licence = '0000-00-00 00:00:00' THEN NULL 
                          ELSE date_expiration_licence 
                      END as date_expiration, 
                      date_create as date_emission, '{}' as features,
                      nom_prenom_titulaire_licence as nom_prenom, '' as entreprise, telephone_titulaire_licence as telephone, email_titulaire_licence as email
               FROM cles_licence";

    // Clause WHERE commune si filtre
    $where = "";
    if ($filtre_statut !== null) {
        $filtre_statut_esc = mysqli_real_escape_string($db, $filtre_statut);
        $where = " WHERE statut = '" . $filtre_statut_esc . "'";
    }

    $sql = "SELECT * FROM (
                $sql_v2
                UNION ALL
                $sql_v1
            ) AS all_licences
            $where
            ORDER BY date_emission DESC";

    $result = mysqli_query($db, $sql);
    if (!$result) {
        return [];
    }

    $licences = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['features'])) {
            $row['features'] = json_decode($row['features'], true) ?? [];
        } else {
            $row['features'] = [];
        }
        $licences[] = $row;
    }

    return $licences;
}


/**
 * Retourne une licence V2 par son ID.
 *
 * @param int $id
 * @return array|null
 */
function klg_db_get_licence_by_id(int $id): ?array
{
    $db   = klg_get_db();
    $stmt = mysqli_prepare($db, 'SELECT l.*, c.nom_prenom, c.entreprise, c.telephone, c.email
                                  FROM klg_c_lm_licences l
                                  INNER JOIN klg_c_lm_clients c ON c.id = l.client_id
                                  WHERE l.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $row['features'] = !empty($row['features']) ? (json_decode($row['features'], true) ?? []) : [];

    return $row;
}


/**
 * Retourne une licence V2 par sa clé de référence.
 *
 * @param string $cle_licence
 * @return array|null
 */
function klg_db_get_licence_by_cle(string $cle_licence): ?array
{
    $db   = klg_get_db();
    $stmt = mysqli_prepare($db, 'SELECT l.*, c.nom_prenom, c.entreprise, c.telephone, c.email
                                  FROM klg_c_lm_licences l
                                  INNER JOIN klg_c_lm_clients c ON c.id = l.client_id
                                  WHERE l.cle_licence = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $cle_licence);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $row['features'] = !empty($row['features']) ? (json_decode($row['features'], true) ?? []) : [];

    return $row;
}


/**
 * Marque une licence comme révoquée.
 *
 * @param int $licence_id
 * @return bool
 */
function klg_db_revoquer_licence(int $licence_id): bool
{
    $db   = klg_get_db();
    $stmt = mysqli_prepare($db, "UPDATE klg_c_lm_licences SET statut = 'REVOQUEE' WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $licence_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}


/**
 * Enregistre une preuve de révocation hors-ligne en base de données.
 *
 * @param int    $licence_id
 * @param string $code_hmac
 * @param string $ancien_hwid
 * @param string $motif
 * @return bool
 */
function klg_db_enregistrer_revocation(int $licence_id, string $code_hmac, string $ancien_hwid, string $motif = ''): bool
{
    $db   = klg_get_db();
    $stmt = mysqli_prepare($db, 'INSERT INTO klg_c_lm_revocations (licence_id, code_hmac, ancien_hwid, motif) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'isss', $licence_id, $code_hmac, $ancien_hwid, $motif);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}


/**
 * Retourne les licences expirant dans les $jours prochains jours.
 *
 * @param int $jours
 * @return array
 */
function klg_db_get_licences_expirant_bientot(int $jours = 30): array
{
    $db    = klg_get_db();
    $limit = date('Y-m-d H:i:s', strtotime('+' . $jours . ' days'));
    $now   = klg_now();

    $sql_v2 = "SELECT l.cle_licence, l.type_licence, l.date_expiration, l.client_id, l.version,
                      c.nom_prenom
               FROM klg_c_lm_licences l
               INNER JOIN klg_c_lm_clients c ON c.id = l.client_id
               WHERE l.statut = 'ACTIVE'
                 AND l.date_expiration IS NOT NULL
                 AND l.date_expiration BETWEEN ? AND ?";

    $sql_v1 = "SELECT cle_licence, type_licence, date_expiration_licence as date_expiration, 0 as client_id, 1 as version,
                      nom_prenom_titulaire_licence as nom_prenom
               FROM cles_licence
               WHERE (licence_is_locked != '1' OR licence_is_locked IS NULL)
                 AND type_licence != 'PERPETUELLE'
                 AND date_expiration_licence IS NOT NULL
                 AND date_expiration_licence != '0000-00-00 00:00:00'
                 AND date_expiration_licence BETWEEN ? AND ?";

    $sql = "SELECT * FROM (
                $sql_v2
                UNION ALL
                $sql_v1
            ) AS expirations
            ORDER BY date_expiration ASC";

    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ssss', $now, $limit, $now, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $licences = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $licences[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $licences;
}


/**
 * Retourne des statistiques globales pour le tableau de bord.
 *
 * @return array
 */
function klg_db_get_statistiques(): array
{
    $db = klg_get_db();

    $stats = [
        'total_licences'    => 0,
        'actives'           => 0,
        'expirees'          => 0,
        'revoquees'         => 0,
        'en_attente'        => 0,
        'total_clients'     => 0,
    ];

    // Compter les licences V2 par statut
    $sql_v2 = "SELECT statut, COUNT(*) as nb FROM klg_c_lm_licences GROUP BY statut";
    
    // Compter les licences V1 par statut calculé
    $sql_v1 = "SELECT 
                  CASE 
                      WHEN licence_is_locked = '1' THEN 'REVOQUEE'
                      WHEN type_licence = 'PERPETUELLE' THEN 'ACTIVE'
                      WHEN date_expiration_licence IS NOT NULL AND date_expiration_licence != '0000-00-00 00:00:00' AND date_expiration_licence < NOW() THEN 'EXPIREE'
                      ELSE 'ACTIVE'
                  END as statut, 
                  COUNT(*) as nb 
               FROM cles_licence 
               GROUP BY statut";

    $sql = "SELECT statut, SUM(nb) as nb FROM (
                $sql_v2
                UNION ALL
                $sql_v1
            ) as all_stats GROUP BY statut";

    $result = mysqli_query($db, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stats['total_licences'] += (int) $row['nb'];
            $key = strtolower($row['statut']);
            if (isset($stats[$key])) {
                $stats[$key] = (int) $row['nb'];
            }
        }
    }

    // Compter les clients (seulement V2)
    $result2 = mysqli_query($db, 'SELECT COUNT(*) as nb FROM klg_c_lm_clients');
    if ($result2) {
        $row2 = mysqli_fetch_assoc($result2);
        $stats['total_clients'] = (int) ($row2['nb'] ?? 0);
    }

    return $stats;
}
