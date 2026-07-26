<?php
declare(strict_types=1);

/**
 * KLG-Caisse License Manager — Génération de Licences V2
 * =======================================================
 * Compatibilité minimum : PHP 7.4.33
 *
 * Ce fichier construit le payload JWT canonique conformément au
 * schéma unifié décidé entre le serveur éditeur et le client KLG-Caisse.
 * Il génère également la référence unique "cle_licence" pour chaque
 * nouveau jeton V2.
 */

require_once dirname(__DIR__) . '/security/crypto_functions.php';


/**
 * Génère une référence unique pour une licence V2 au format "V2-AAAA-XXXX-YYYY".
 * La référence est différente de l'ancienne clé V1 (20 caractères) et permet
 * de distinguer visuellement les deux générations de licences.
 *
 * @return string  ex: "V2-2026-A3K7-P9QX"
 */
function klg_generer_ref_licence_v2(): string
{
    $annee   = date('Y');
    $segment1 = mb_strtoupper(klg_generer_segment_aleatoire(4));
    $segment2 = mb_strtoupper(klg_generer_segment_aleatoire(4));

    return 'V2-' . $annee . '-' . $segment1 . '-' . $segment2;
}


/**
 * Génère un segment aléatoire alphanumérique sans caractères ambigus
 * (pas de 0/O, 1/I/L).
 *
 * @param int $longueur Longueur du segment.
 * @return string
 */
function klg_generer_segment_aleatoire(int $longueur = 4): string
{
    $chars = '23456789abcdefghjklmnprstuvxyz';
    $result = '';
    $max    = strlen($chars) - 1;

    for ($i = 0; $i < $longueur; $i++) {
        $result .= $chars[random_int(0, $max)];
    }

    return $result;
}


/**
 * Construit le payload JWT canonique V2, en respectant strictement la
 * structure attendue par le validateur KLG-Caisse V2.
 *
 * @param array $data Tableau associatif avec les champs suivants :
 *   - 'nom_prenom'                    (string) Nom et prénom du titulaire
 *   - 'telephone'                     (string) Téléphone
 *   - 'email'                         (string) E-mail
 *   - 'hwid'                          (string) Hardware ID fourni par le client
 *   - 'type_licence'                  (string) 'ESSAI' | 'ABONNEMENT' | 'PERPETUELLE'
 *   - 'cle_licence'                   (string) Référence V2 générée
 *   - 'duree_periode_utilisation'     (int)    Durée numérique
 *   - 'unite_temps_periode_utilisation' (string) 'years'|'months'|'days'|...
 *   - 'date_expiration'               (string) 'YYYY-MM-DD HH:MM:SS' ou '' si PERPETUELLE
 *   - 'features'                      (array)  Feature flags (optionnel)
 * @return array Le payload complet prêt à être encodé en JWT.
 */
function klg_construire_payload_v2(array $data): array
{
    $maintenant     = time();
    $type_licence   = $data['type_licence'] ?? 'ESSAI';
    $date_expiration = $data['date_expiration'] ?? '';

    // Calcul du timestamp d'expiration Unix
    if ($type_licence === 'PERPETUELLE' || empty($date_expiration)) {
        $exp = 0; // 0 = pas d'expiration (PERPETUELLE)
    } else {
        $exp = (int) strtotime($date_expiration);
    }

    // Date de première activation : maintenant (le JWT est généré = il est activé)
    $date_first_activation = date('Y-m-d H:i:s', $maintenant);

    // Construire le payload canonique — les noms de clés doivent être identiques
    // à ceux lus par klg-caisse_v2/core/security/v2/license_v2_functions.php
    $payload = [
        'ver'                               => 2,
        'cle_licence'                       => $data['cle_licence'],
        'type_licence'                      => $type_licence,
        'hwid'                              => $data['hwid'],
        'exp'                               => $exp,
        'iat'                               => $maintenant,
        'nom_prenom_titulaire_licence'      => $data['nom_prenom'] ?? '',
        'telephone_titulaire_licence'       => $data['telephone'] ?? '',
        'email_titulaire_licence'           => $data['email'] ?? '',
        'duree_periode_utilisation'         => (int) ($data['duree_periode_utilisation'] ?? 0),
        'unite_temps_periode_utilisation'   => $data['unite_temps_periode_utilisation'] ?? 'months',
        'date_first_activation_licence'     => $date_first_activation,
        'date_expiration_licence'           => $date_expiration,
    ];

    // Ajout des feature flags si présents et non vides
    if (!empty($data['features']) && is_array($data['features'])) {
        $payload['features'] = $data['features'];
    }

    return $payload;
}


/**
 * Pipeline complet : construit le payload, le signe et retourne le JWT.
 * C'est la fonction à appeler depuis le formulaire de création de licence.
 *
 * @param array  $data            Données du formulaire (voir klg_construire_payload_v2).
 * @param string $private_key_pem Contenu PEM de la clé privée RSA.
 * @return string|null            Le JWT signé, ou null en cas d'erreur de signature.
 */
function klg_emettre_licence_v2(array $data, string $private_key_pem): ?string
{
    $payload = klg_construire_payload_v2($data);
    return klg_jwt_encode_rs256($payload, $private_key_pem);
}
