<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — API Legacy V1 (Rétrocompatibilité)
 * ================================================================
 * Compatibilité minimum : PHP 7.4.33
 *
 * Ce fichier isole INTÉGRALEMENT le code de l'ancien back-office
 * pour les clients KLG-Caisse utilisant encore les licences V1
 * (clés de 20 caractères au format XXXX-XXXX-XXXX-XXXX-XXXX).
 *
 * ⚠️  RÈGLE ABSOLUE : Ne jamais modifier la logique de cette API
 *     sans vérifier la compatibilité avec les clients V1 existants.
 *     Ces endpoints doivent rester fonctionnels jusqu'au 01/01/2027.
 *
 * Points d'entrée maintenus :
 *   - GET  ?action=get-data-licence&cle_licence=XXXX
 *   - GET  ?action=update-licence&data_licence=JSON
 *   - GET  ?action=desactiver-licence&cle_licence=XXXX
 *   - GET  ?action=get-latest-app-version
 */

require_once dirname(__DIR__, 2) . '/config/database.php';


/**
 * Dispatch de l'action V1 demandée.
 * Appelée depuis le routeur principal public/index.php.
 *
 * @param string $action L'action demandée via $_GET['action'].
 * @return void
 */
function klg_v1_dispatcher(string $action): void
{
    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {

        // --------------------------------------------------------
        // GET ?action=get-data-licence&cle_licence=XXXX
        // Retourne les informations d'une licence V1.
        // --------------------------------------------------------
        case 'get-data-licence':
            klg_v1_get_data_licence();
            break;

        // --------------------------------------------------------
        // GET ?action=update-licence&data_licence=JSON
        // Met à jour les données d'activation d'une licence V1.
        // --------------------------------------------------------
        case 'update-licence':
            klg_v1_update_licence();
            break;

        // --------------------------------------------------------
        // GET ?action=desactiver-licence&cle_licence=XXXX
        // Marque une licence V1 comme inutilisée (désactivation).
        // --------------------------------------------------------
        case 'desactiver-licence':
            klg_v1_desactiver_licence();
            break;

        // --------------------------------------------------------
        // GET ?action=get-latest-app-version
        // Retourne la dernière version connue du logiciel.
        // (Endpoint legacy conservé — à terme remplacer par le Pilier 5)
        // --------------------------------------------------------
        case 'get-latest-app-version':
            klg_v1_get_latest_version();
            break;

        default:
            http_response_code(404);
            exit(json_encode(['error' => true, 'message' => 'Action V1 inconnue.']));
    }
}


/**
 * Retourne les données d'une licence V1 à partir de sa clé.
 */
function klg_v1_get_data_licence(): void
{
    if (empty($_GET['cle_licence'])) {
        exit(json_encode(['licence_exists' => false]));
    }

    $db          = klg_get_db();
    $cle_licence = klg_db_escape($_GET['cle_licence']);

    $sql    = 'SELECT * FROM cles_licence WHERE cle_licence = "' . $cle_licence . '" LIMIT 1';
    $resql  = mysqli_query($db, $sql);
    $nb     = mysqli_num_rows($resql);

    if ($nb > 0) {
        $data = mysqli_fetch_object($resql);

        $response = [
            'licence_exists'                  => true,
            'nom_prenom_titulaire_licence'     => $data->nom_prenom_titulaire_licence,
            'telephone_titulaire_licence'      => $data->telephone_titulaire_licence,
            'email_titulaire_licence'          => $data->email_titulaire_licence,
            'type_licence'                     => $data->type_licence,
            'duree_periode_utilisation'        => $data->duree_periode_utilisation,
            'unite_temps_periode_utilisation'  => $data->unite_temps_periode_utilisation,
            'cle_licence'                      => $data->cle_licence,
            'licence_is_currently_used'        => $data->licence_is_currently_used,
            'licence_is_locked'                => $data->licence_is_locked,
            'nom_serveur'                      => $data->nom_serveur,
            'date_first_activation_licence'    => $data->date_first_activation_licence,
            'date_last_activation_licence'     => $data->date_last_activation_licence,
            'date_expiration_licence'          => $data->date_expiration_licence,
        ];
    } else {
        $response = ['licence_exists' => false];
    }

    exit(json_encode($response));
}


/**
 * Met à jour les données d'activation d'une licence V1.
 */
function klg_v1_update_licence(): void
{
    if (empty($_GET['data_licence'])) {
        exit(json_encode(['data_licence_updated_via_api' => false]));
    }

    $db           = klg_get_db();
    $current_dt   = klg_now();
    $data_licence = json_decode($_GET['data_licence']);

    if ($data_licence === null) {
        exit(json_encode(['data_licence_updated_via_api' => false]));
    }

    $sql = 'UPDATE cles_licence SET
                nom_serveur                  = "' . mysqli_real_escape_string($db, (string) $data_licence->nom_serveur) . '",
                licence_is_currently_used    = "' . mysqli_real_escape_string($db, (string) $data_licence->licence_is_currently_used) . '",
                licence_is_locked            = "' . mysqli_real_escape_string($db, (string) $data_licence->licence_is_locked) . '",
                date_first_activation_licence= "' . mysqli_real_escape_string($db, (string) $data_licence->date_first_activation_licence) . '",
                date_last_activation_licence = "' . mysqli_real_escape_string($db, (string) $data_licence->date_last_activation_licence) . '",
                date_expiration_licence      = "' . mysqli_real_escape_string($db, (string) $data_licence->date_expiration_licence) . '",
                date_modif                   = "' . $current_dt . '"
            WHERE cle_licence = "' . mysqli_real_escape_string($db, (string) $data_licence->cle_licence) . '"';

    $ok       = mysqli_query($db, $sql);
    $response = ['data_licence_updated_via_api' => (bool) $ok];

    exit(json_encode($response));
}


/**
 * Marque une licence V1 comme non utilisée (désactivation distante).
 */
function klg_v1_desactiver_licence(): void
{
    if (empty($_GET['cle_licence'])) {
        exit(json_encode(['licence_desactive_via_api' => false]));
    }

    $db          = klg_get_db();
    $cle_licence = klg_db_escape($_GET['cle_licence']);
    $current_dt  = klg_now();

    $sql = 'UPDATE cles_licence SET
                licence_is_currently_used = 0,
                date_modif                = "' . $current_dt . '"
            WHERE cle_licence = "' . $cle_licence . '"';

    $ok       = mysqli_query($db, $sql);
    $response = ['licence_desactive_via_api' => (bool) $ok];

    exit(json_encode($response));
}


/**
 * Retourne la liste des versions connues du logiciel KLG-Caisse.
 * (Endpoint legacy conservé — Pilier 5 prendra le relais à terme.)
 */
function klg_v1_get_latest_version(): void
{
    $db     = klg_get_db();
    $result = mysqli_query($db, 'SELECT version FROM klg_c_lm_releases WHERE est_active = 1 ORDER BY date_upload DESC');

    $versions = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $versions[] = $row['version'];
        }
    }

    // Fallback sur la liste codée en dur si aucune release en base
    if (empty($versions)) {
        $versions = ['1.0.0', '1.0.1', '1.0.2', '1.0.3', '1.0.4', '1.0.5', '1.0.6'];
    }

    exit(json_encode([
        'all_versions'   => $versions,
        'latest_version' => end($versions),
    ]));
}
