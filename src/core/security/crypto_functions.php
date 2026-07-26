<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Fonctions Cryptographiques (Côté Éditeur)
 * =======================================================================
 * Compatibilité minimum : PHP 7.4.33
 *
 * Ce fichier est le pendant exact de `crypto_functions.php` du logiciel client
 * KLG-Caisse, mais du côté éditeur : il SIGNE les tokens JWT avec la clé
 * privée RSA au lieu de les vérifier avec la clé publique.
 *
 * IMPORTANT :
 * - La clé privée RSA ne doit JAMAIS être incluse dans le code client.
 * - Seul ce serveur éditeur doit y avoir accès.
 */


/**
 * Encode une chaîne en Base64 URL-safe (sans =, + remplacé par -, / par _).
 *
 * @param string $data
 * @return string
 */
function klg_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


/**
 * Décode une chaîne Base64 URL-safe.
 *
 * @param string $data
 * @return string|false
 */
function klg_base64url_decode(string $data)
{
    return base64_decode(strtr($data, '-_', '+/'));
}


/**
 * Génère un jeton JWT signé en RSA-SHA256 (algorithme RS256).
 * C'est la fonction principale du moteur de licence V2 côté éditeur.
 *
 * @param array  $payload         Le tableau associatif des données du jeton.
 * @param string $private_key_pem Le contenu PEM de la clé privée RSA.
 * @return string|null            Le jeton JWT complet (header.payload.signature) ou null si erreur.
 */
function klg_jwt_encode_rs256(array $payload, string $private_key_pem): ?string
{
    // 1. Construire l'en-tête JWT (RS256 = RSA + SHA-256)
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];

    $header_b64  = klg_base64url_encode((string) json_encode($header));
    $payload_b64 = klg_base64url_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $data_to_sign = $header_b64 . '.' . $payload_b64;

    // 2. Charger la clé privée RSA
    $private_key_resource = openssl_get_privatekey($private_key_pem);
    if ($private_key_resource === false) {
        // La clé privée est invalide ou corrompue
        return null;
    }

    // 3. Signer avec RSA-SHA256
    $signature = '';
    $sign_result = openssl_sign($data_to_sign, $signature, $private_key_resource, OPENSSL_ALGO_SHA256);

    // PHP 8+ libère automatiquement, PHP 7.4 requiert openssl_free_key
    if (PHP_MAJOR_VERSION < 8) {
        /** @noinspection PhpParamsInspection */
        openssl_free_key($private_key_resource);
    }

    if (!$sign_result) {
        return null;
    }

    // 4. Assembler le jeton final
    $signature_b64 = klg_base64url_encode($signature);

    return $header_b64 . '.' . $payload_b64 . '.' . $signature_b64;
}


/**
 * Charge le contenu de la clé privée RSA depuis le chemin défini en config.
 *
 * @return string|null Le contenu PEM, ou null si le fichier est introuvable.
 */
function klg_load_private_key(): ?string
{
    if (!defined('RSA_PRIVATE_KEY_PATH') || !is_file(RSA_PRIVATE_KEY_PATH)) {
        return null;
    }

    $pem = file_get_contents(RSA_PRIVATE_KEY_PATH);
    return ($pem !== false) ? $pem : null;
}


/**
 * Vérifie un code de révocation HMAC-SHA256 fourni par un client KLG-Caisse.
 * Le code de révocation contient les données hachées : "hwid|cle_licence|timestamp".
 *
 * @param string $code_revocation Le code brut soumis par le client.
 * @param string $cle_licence     La clé licence que l'on cherche à révoquer.
 * @param string $hwid            Le HWID attendu pour cette licence.
 * @return array                  ['valide' => bool, 'timestamp' => int|null, 'hwid' => string|null]
 */
function klg_verifier_code_revocation(string $code_revocation, string $cle_licence, string $hwid): array
{
    // Format attendu du code de révocation (base64url) :
    // base64url( "hwid|cle_licence|timestamp" + "|" + hmac_sha256(hwid|cle_licence|timestamp, HMAC_SHARED_SECRET) )
    $decoded = klg_base64url_decode(trim($code_revocation));
    if ($decoded === false || empty($decoded)) {
        return ['valide' => false, 'timestamp' => null, 'hwid' => null];
    }

    // Séparer les parties
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return ['valide' => false, 'timestamp' => null, 'hwid' => null];
    }

    [$code_hwid, $code_cle, $code_timestamp, $code_hmac] = $parts;

    // Recalculer le HMAC pour validation
    $data_a_hasher   = $code_hwid . '|' . $code_cle . '|' . $code_timestamp;
    $hmac_attendu    = hash_hmac('sha256', $data_a_hasher, HMAC_SHARED_SECRET);

    // Vérifier que les données correspondent à ce que l'on attend
    $hmac_valide    = hash_equals($hmac_attendu, $code_hmac);
    $hwid_valide    = hash_equals($hwid, $code_hwid);
    $cle_valide     = hash_equals($cle_licence, $code_cle);

    return [
        'valide'     => ($hmac_valide && $hwid_valide && $cle_valide),
        'timestamp'  => (int) $code_timestamp,
        'hwid'       => $code_hwid,
    ];
}
