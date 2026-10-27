<?php
/**
 * Plugin Name: SRN Health
 * Description: Un plugin SRN pour vérifier la santé du site.
 * Version: 0.11.0
 * Author: Speedi Rychi Nylon
 * Author URI: https://speedi.org/
 * Text Domain: srn-health
 * Domain Path: /languages
 */

// Chargement des fonctions utils et des classes php
require_once __DIR__ . '/src/Utils/functions.php';

// On charge la bibliothèque
require __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
use Src\Services\VolumeService;
use Src\Services\WordpressService;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// On initialise le checker
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/ArthurSpeedi/srn-health/', // URL de votre dépôt Git
    __FILE__, // Chemin vers le fichier principal du plugin
    'srn-health' // Le slug (nom du dossier du plugin)
);

// OPTIONNEL : Si votre repo GitHub est PRIVÉ, vous devez générer un 
// "Personal Access Token" (Classic) sur GitHub et l'ajouter ici :
// $myUpdateChecker->setAuthentication('votre_token_github_ici');

// Forcer la branche (par défaut c'est 'master' ou 'main')
$myUpdateChecker->setBranch('main');

// Si votre repo est privé, vous pouvez ajouter un token d'accès :
// $myUpdateChecker->setAuthentication('votre_token_github');

add_action('rest_api_init', function () {
    register_rest_route('srn-health/v1', '/ping', array(
        'methods' => 'GET',
        'callback' => 'srn_health_ping',
        'permission_callback' => 'check_dashboard_signature'
    ));
    register_rest_route('srn-health/v1', '/infos', array(
        'methods' => 'GET',
        'callback' => 'get_site_infos_for_dashboard',
        'permission_callback' => 'check_dashboard_signature'
    ));
    register_rest_route('srn-health/v1', '/reset-cache', array(
        'methods' => 'POST',
        'callback' => 'srn_health_reset_cache',
        'permission_callback' => 'check_dashboard_signature'
    ));
    register_rest_route('srn-health/v1', '/tree', array(
        'methods' => 'GET',
        'callback' => 'get_site_volume_tree',
        'permission_callback' => 'check_dashboard_signature'
    ));
});

/**
 * Vérifie la signature Ed25519 envoyée par l'application Nuxt.
 * Message signé : "horodatage|domaine|methode|route", domaine étant l'hôte de home_url(),
 * pour qu'une signature ne serve qu'à une seule route et une seule méthode.
 *
 * @return bool true si la signature est valide, false sinon (jamais d'erreur fatale)
 */
function check_dashboard_signature(WP_REST_Request $request)
{
    $timestamp = $request->get_header('X-Horodatage');
    $signature_b64 = $request->get_header('X-Signature');
    if (!is_string($timestamp) || !is_string($signature_b64)) {
        return false;
    }

    // Horodatage Unix en secondes, uniquement des chiffres
    if (!preg_match('/^\d{1,12}$/', $timestamp)) {
        return false;
    }
    if (abs(time() - (int) $timestamp) > SRN_HEALTH_SIGNATURE_MAX_AGE) {
        return false;
    }

    $signature = base64_decode($signature_b64, true);
    if ($signature === false || strlen($signature) !== 64) {
        return false;
    }

    $public_key = base64_decode(SRN_HEALTH_PUBLIC_KEY, true);
    if ($public_key === false || strlen($public_key) !== 32) {
        return false;
    }

    $domain = wp_parse_url(home_url(), PHP_URL_HOST);
    if (empty($domain)) {
        return false;
    }

    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        return false;
    }

    $message = $timestamp . '|' . $domain . '|' . $request->get_method() . '|' . $request->get_route();

    try {
        return sodium_crypto_sign_verify_detached($signature, $message, $public_key);
    } catch (\Throwable $e) {
        // SodiumException (ou TypeError côté sodium_compat) : on refuse sans planter
        return false;
    }
}


/**
 * Route légère pour vérifier que le site répond et que la signature est valide,
 * sans les calculs de taille de /infos.
 */
function srn_health_ping()
{
    return array(
        'status' => 'ok',
        'srn_health_version' => get_plugin_data(SRN_HEALTH_PLUGIN_FILE, false, false)['Version'],
        'timestamp' => time()
    );
}

function get_site_infos_for_dashboard()
{
    global $wp_version;

    return array(
        'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        'site_url' => get_bloginfo('url'),
        'wp_version' => $wp_version,
        'php_version' => phpversion(),
        'srn_health_version' => get_plugin_data(__FILE__)['Version'],
        'db_size' => VolumeService::getDbSize(),
        'db_overhead' => VolumeService::getOverheadSize(),
        'site_size' => VolumeService::getSiteSize(),
        'active_theme' => get_stylesheet(),
        'plugins' => WordpressService::getPlugins(),
        'count_posts' => wp_count_posts()->publish,
        'media_size' => VolumeService::getMediaLibrarySize(),
        'is_self_update_enabled' => WordpressService::isSelfAutoUpdateEnabled(),
        'site_health' => WordpressService::getSiteHealthSummary()
    );
}

function get_site_volume_tree(WP_REST_Request $request)
{
    $depth = (int) $request->get_param('depth');
    $depth = ($depth > 0) ? $depth : 3;
    $tree = VolumeService::getSiteVolumeTree($depth);
    return $tree;
}

function srn_health_reset_cache()
{
    VolumeService::clearVolumeCache();

    return array('success' => true, 'message' => 'Cache vidé.');
}

/** 
 * TODO
 * - Statistique des posts
 * - Statistique des médias
 * - Is self Mise à jour automatique
 */