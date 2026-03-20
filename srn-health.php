<?php
/**
 * Plugin Name: SRN Health
 * Description: Un plugin SRN pour vérifier la santé du site.
 * Version: 0.3
 * Author: Speedi Rychi Nylon
 */

// On charge la bibliothèque
require __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
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
    global $wp_version, $wpdb;

    $cache_duration = HOUR_IN_SECONDS;

    $plugin_data = get_plugin_data(__FILE__);

    // Nombre de plugins à mettre à jour
    // --- 1. Gestion des Plugins ---
    $all_plugins = get_plugins(); // Nécessite wp-admin/includes/plugin.php si hors contexte admin
    $active_plugins_slugs = get_option('active_plugins');
    $update_plugins = get_site_transient('update_plugins');
    
    $plugins_list = [];
    
    foreach ($active_plugins_slugs as $plugin_path) {
        if (isset($all_plugins[$plugin_path])) {
            $info = $all_plugins[$plugin_path];
            $has_update = isset($update_plugins->response[$plugin_path]);
            
            $plugins_list[] = [
                'name'    => $info['Name'],
                'version' => $info['Version'],
                'update_available' => $has_update,
                'new_version'      => $has_update ? $update_plugins->response[$plugin_path]->new_version : null
            ];
        }
    }

    // --- 2. Tailles (avec cache) ---
    $db_size = get_transient('srn_health_db_size');
    if ($db_size === false) {
        $db_size = 0;
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($tables as $table) {
            $db_size += $table['Data_length'] + $table['Index_length'];
        }
        set_transient('srn_health_db_size', $db_size, $cache_duration);
    }

    $site_size = get_transient('srn_health_site_size');
    if ($site_size === false) {
        $site_size = srn_health_dir_size(ABSPATH);
        set_transient('srn_health_site_size', $site_size, $cache_duration);
    }

    $count_posts = wp_count_posts();

    return array(
        'site_name' => get_bloginfo('name'),
        'site_url' => get_bloginfo('url'),
        'wp_version' => $wp_version,
        'php_version' => phpversion(),
        'srn_health_version' => $plugin_data['Version'],
        'db_size' => $db_size,
        'site_size' => $site_size,
        'active_theme' => get_stylesheet(),
        'plugins' => $plugins_list,
        'count_posts' => $count_posts->publish,
    );
}

function srn_health_dir_size($path)
{
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    // Définition des éléments à exclure (Duplicator et autres archives)
    $exclude_patterns = [
        'backups-dup-lite', // Dossier par défaut Duplicator
        'wp-snapshots',     // Anciens dossiers Duplicator
        '_archive.zip',     // Extension archive Duplicator
        '_archive.daf',     // Format propriétaire Duplicator
        '.tar.gz',          // Autres sauvegardes courantes
        'node_modules'      // Pour nettoyer si tu as des restes de dev
    ];

    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        
        // Vérification de l'exclusion
        $should_exclude = false;
        foreach ($exclude_patterns as $pattern) {
            if (strpos($file_path, $pattern) !== false) {
                $should_exclude = true;
                break;
            }
        }

        if (!$should_exclude && $file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function srn_health_reset_cache()
{
    delete_transient('srn_health_db_size');
    delete_transient('srn_health_site_size');

    return array('success' => true, 'message' => 'Cache vidé.');
}