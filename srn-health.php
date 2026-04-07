<?php
/**
 * Plugin Name: SRN Health
 * Description: Un plugin SRN pour vérifier la santé du site.
 * Version: 0.7
 * Author: Speedi Rychi Nylon
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
    register_rest_route('srn-health/v1', '/infos', array(
        'methods' => 'GET',
        'callback' => 'get_site_infos_for_dashboard',
        'permission_callback' => 'check_dashboard_api_token'
    ));
    register_rest_route('srn-health/v1', '/reset-cache', array(
        'methods' => 'POST',
        'callback' => 'srn_health_reset_cache',
        'permission_callback' => 'check_dashboard_api_token'
    ));
    register_rest_route('srn-health/v1', '/tree', array(
        'methods' => 'GET',
        'callback' => 'get_site_volume_tree',
        'permission_callback' => 'check_dashboard_api_token'
    ));
});

function check_dashboard_api_token(WP_REST_Request $request)
{
    if (!defined('SRN_HEALTH_TOKEN')) {
        return new WP_Error('rest_forbidden', 'Token non configuré.', array('status' => 500));
    }

    $token = $request->get_header('X-SRN-Token');
    if (empty($token) || !hash_equals(SRN_HEALTH_TOKEN, $token)) {
        return new WP_Error('rest_forbidden', 'Accès refusé.', array('status' => 401));
    }
    return true;
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