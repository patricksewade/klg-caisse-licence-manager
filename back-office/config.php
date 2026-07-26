<?php

declare(strict_types=1);
/* Connexion à la base de données
    =================================*/
$connectBdd = mysqli_connect('localhost', 'root', 'root', 'klg_caisse_licence_manager_db') or die('Impossible de se connecter à la base de données');

/* Modification du jeu de résultats en utf8
===========================================*/
mysqli_set_charset($connectBdd, "utf8");


/*************************************************************************************************/

function secureDataIn($string)
{
    global $connectBdd;

    $string = trim($string);
    $string = mysqli_real_escape_string($connectBdd, $string);

    return $string;
}

function secureDataOut($string)
{
    $string = trim($string);
    $string = stripcslashes($string);
    $string = htmlentities($string);

    return $string;
}

function aff_date_heure($dateEtHeure)
{
    /*$dateEtHeure = date('d/m/Y \à H:i:s', strtotime($dateEtHeure));*/ // Date heure et seconde
    //$dateEtHeure = date('\l\e d/m/Y \à H:i', strtotime($dateEtHeure));
    if ($dateEtHeure != '0000-00-00 00:00:00' and !empty($dateEtHeure)) {
        $dateEtHeure = date('d/m/Y \à H:i', strtotime($dateEtHeure));
        return $dateEtHeure;
    } else {
        return '-';
    }
}

function aff_date($date)
{
    if (empty($date) or $date == '0000-00-00') {
        return '-';
    } else {
        $date = date('d/m/Y', strtotime($date));
        return $date;
    }
}

function aff_jour_mois($date)
{
    /*$date = date('d/m/Y \à H:i:s', strtotime($date));*/ // Date heure et seconde
    //$date = date('\l\e d/m/Y', strtotime($date));
    if ($date != '0000-00-00' or !empty($dateEtHeure)) {
        $date = date('d/m', strtotime($date));
        return $date;
    } else {
        return '-';
    }
}

//** Afficher une date relative (en français)
function getRelativeTime($date)
{
    $date_a_comparer = new DateTime($date);
    $date_actuelle = new DateTime("now");

    $intervalle = $date_a_comparer->diff($date_actuelle);

    if ($date_a_comparer > $date_actuelle) {
        $prefixe = 'dans ';
    } else {
        $prefixe = 'il y a ';
    }

    $ans = $intervalle->format('%y');
    $mois = $intervalle->format('%m');
    $jours = $intervalle->format('%d');
    $heures = $intervalle->format('%h');
    $minutes = $intervalle->format('%i');
    $secondes = $intervalle->format('%s');

    if ($ans != 0) {
        $relative_date = $prefixe . $ans . ' an' . (($ans > 1) ? 's' : '');
        if ($mois >= 6) {
            $relative_date .= ' et demi';
        }
    } elseif ($mois != 0) {
        $relative_date = $prefixe . $mois . ' mois';
        if ($jours >= 15) {
            $relative_date .= ' et demi';
        }
    } elseif ($jours != 0) {
        $relative_date = $prefixe . $jours . ' jour' . (($jours > 1) ? 's' : '');
    } elseif ($heures != 0) {
        $relative_date = $prefixe . $heures . ' heure' . (($heures > 1) ? 's' : '');
    } elseif ($minutes != 0) {
        $relative_date = $prefixe . $minutes . ' minute' . (($minutes > 1) ? 's' : '');
    } else {
        $relative_date = $prefixe . ' quelques secondes';
    }

    return $relative_date;
}

function removeAccents($str, $charset = 'utf-8')
{
    $str = htmlentities($str, ENT_NOQUOTES, $charset);

    $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
    $str = preg_replace('#&[^;]+;#', '-', $str); // supprime les autres caractères

    return $str;
}

function format_uri($string, $separator = '-')
{
    $accents_regex = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
    $special_cases = [ '&' => 'and', "'" => ''];
    $string = mb_strtolower(trim($string), 'UTF-8');
    $string = str_replace(array_keys($special_cases), array_values($special_cases), $string);
    $string = preg_replace($accents_regex, '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
    $string = preg_replace("/[^a-z0-9]/u", "$separator", $string);
    $string = preg_replace("/[$separator]+/u", "$separator", $string);
    return $string;
}

function removeAllWhitespace($string)
{
    $string = str_replace(' ', '', $string);
    $string = preg_replace('/\s/', '', $string);
    $string = preg_replace('/\s+/', ' ', $string);
    $string = trim(preg_replace('/\s+/', ' ', $string));
    return $string;
}

function seperateThousands($number, $seperator = ' ')
{
    $number_formated = number_format($number, $decimals = 0, $decimalpoint = 0, $seperator);

    return $number_formated;

    /*if(is_float($number))
    {
        $number_formated = number_format($number, $decimals=2, $decimalpoint=',', $seperator);
    }
    else
    {
        $number_formated = number_format($number, $decimals=0, $decimalpoint=0, $seperator);
    }

    return $number_formated;*/
}

function shorten_my_string($string, $length)
{
    if (strlen($string) > $length) {
        $string = trim(substr($string, 0, $length)) . "...";
    }
    return $string;
}

function getMACaddressOfServer()
{
    if (function_exists('exec')) {
        // PHP code to get the MAC address of Server
        $mac_address = exec('getmac');

        // Storing 'getmac' value in $MAC
        $mac_address = strtok($mac_address, ' ');

        // Updating $mac_address value using strtok function,
        // strtok is used to split the string into tokens
        // split character of strtok is defined as a space
        // because getmac returns transport name after
        // MAC address
        return $mac_address;
    }
}

function getIPaddressOfServer()
{
    $ip_address = getenv('REMOTE_ADDR');

    if (!empty($ip_address)) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    return $ip_address;
}

/* Crypte et décrypte une chaine avec une clé
=============================================*/
function crypt_or_decrypt_string($type_operation, $string)
{
    $key_password = "KLG-Caisse";

    if ($type_operation == 'crypt') { // CRYPTER
        $string_to_return = openssl_encrypt($string, "AES-128-ECB", $key_password);
    } elseif ($type_operation == 'decrypt') { // DECRYPTER
        $string_to_return = openssl_decrypt(
            $string,
            "AES-128-ECB",
            $key_password,
        );
    }

    return $string_to_return;
}

function startSessionIfNotStart()
{
    if (version_compare(phpversion(), '5.4.0', '<')) { //Si PHP < 5.4.0
        if (session_id() == '') {
            session_start();
        }
    } else { //Si PHP >= 5.4.0 , PHP 7, PHP 8
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

function this_device_is_connected_to_internet($domain = 'https://www.google.bj')
{
    if (@fopen($domain, "r")) { //Si le périphérique est connecté à internet
        return true;
    } else {
        return false;
    }
}

function randomNDigitNumber($length)
{
    $result = '';

    /*for($i = 0; $i < $length; $i++) {

        $result .= mt_rand(0, 9);

        // On retire le chiffre 0 s'il apparaît en premier
        if($i == 0 AND $result == 0)
        {
            $i = 0;
            $result = '';
        }
    }*/
    for ($i = 0; $i < $length; $i++) {
        $result .= mt_rand(1, 9);
    }

    return $result;
}

//*** Générer une clé de licence pour l'application KLG-Caisse ***
//================================================================
function generer_cle_licence_klg_caisse()
{
    for ($i = 1; $i <= 5; $i++) {
        $chars[] = mb_strtoupper(generer_random_code($length = 4, $type_character = 'alphanumerique'));
    }

    $cle_licence = implode('-', $chars);

    return $cle_licence;
}

function generer_random_code($length = 8, $type_character = 'alphanumerique')
{
    switch ($type_character) {
        case 'alphanumerique':
            $chars = '23456789abcdefghjklmnprstuvxyzABCDEFGHJKLMNPRSTUVXYZ';
            break;
        case 'only_numbers':
            $chars = '23456789';
            break;
        case 'only_letters':
            $chars = 'abcdefghjklmnprstuvxyzABCDEFGHJKLMNPRSTUVXYZ';
            break;
    }

    $ret = '';
    for ($i = 0; $i < $length; ++$i) {
        $random = str_shuffle($chars);
        $ret .= $random[0];
    }
    return $ret;
}

function generer_code_carte_cadeau($length = 8)
{
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; //0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ
    $ret = '';
    for ($i = 0; $i < $length; ++$i) {
        $random = str_shuffle($chars);
        $ret .= $random[0];
    }
    return $ret;
}

/* Generate in PHP all combinations of items in multiple arrays
===============================================================*/
function get_combinations($arrays, $i = 0)
{
    if (!isset($arrays[$i])) {
        return [];
    }
    if ($i == count($arrays) - 1) {
        return $arrays[$i];
    }

    // get combinations from subsequent arrays
    $tmp = get_combinations($arrays, $i + 1);

    $result = [];

    // concat each array from tmp with each element from $arrays[$i]
    foreach ($arrays[$i] as $v) {
        foreach ($tmp as $t) {
            $result[] = is_array($t)
                ? array_merge([$v], $t)
                : [$v, $t];
        }
    }

    return $result;
}

function generate_unique_random_token()
{
    return md5(microtime(true) . mt_Rand());
}

/* Fonction pour rediriger vers une URL
=======================================*/
function redirectToURL($url)
{
    header('Location: ' . $url . '');
    exit();
}

/* Fonction pour retourner la date du jour et au format date
============================================================*/
function getCurrentDate()
{
    return date("Y-m-d");
}

/* Fonction pour retourner la date du jour et l'heure au format datetime
========================================================================*/
function getCurrentDateTime()
{
    return date("Y-m-d H:i:s");
}

/* Fonction pour afficher une datetime au format 'd/m/Y' ou 'd/m/Y à H:i:s'
   Paramètres : $showTime qui peut prendre deux valeurs :
   0 équivaut à ne pas afficher l'heure
   1 équivaut à afficher l'heure
===========================================================================*/
function formatDateTimeInFrench($dateTime, $showTime)
{
    if ($showTime == 0) {
        $format = 'd/m/Y';
    } elseif ($showTime == 1) {
        $format = 'd/m/Y à H:i:s';
    }

    $dateTime = date($format, strtotime($dateTime));

    return $dateTime;
}

function convert_iso8601_to_datetime($date_string)
{
    return date('Y-m-d H:i:s', strtotime($date_string));
}
