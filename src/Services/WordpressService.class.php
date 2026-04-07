<?php

namespace Src\Services;


class WordpressService
{
    /**
     * Retourne la liste des plugins
     *
     * @return array Un tableau de plugins actifs avec leurs informations et état de mise à jour
     */
    public static function getPlugins(): array
    {
        $all_plugins = get_plugins();
        $active_plugins_slugs = get_option('active_plugins');
        $update_plugins = get_site_transient('update_plugins');

        $plugins_list = [];

        foreach ($active_plugins_slugs as $plugin_path) {
            if (isset($all_plugins[$plugin_path])) {
                $info = $all_plugins[$plugin_path];
                $has_update = isset($update_plugins->response[$plugin_path]);

                $plugins_list[] = [
                    'name' => $info['Name'],
                    'version' => $info['Version'],
                    'update_available' => $has_update,
                    'new_version' => $has_update ? $update_plugins->response[$plugin_path]->new_version : null
                ];
            }
        }

        $plugins_list = array_values(array_unique($plugins_list, SORT_REGULAR));

        return $plugins_list;
    }

    /**
     * Calcule et retourne la taille de la base de données
     * 
     * @return int La taille totale de la base de données en octets
     */
    public static function getDbSize(): int
    {
        global $wpdb;
        $db_size = get_transient('srn_health_db_size');
        if ($db_size === false) {
            $db_size = 0;
            $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
            foreach ($tables as $table) {
                $db_size += $table['Data_length'] + $table['Index_length'];
            }
            set_transient('srn_health_db_size', $db_size, SRN_CACHE_DURATION);
        }
        return $db_size;
    }

    /**
     * Calcule et retourne la taille de la "Perte" de la base de données (overhead)
     * 
     * @return int La taille totale de la perte de la base de données en octets
     */
    public static function getOverheadSize(): int
    {
        global $wpdb;
        $overhead_size = get_transient('srn_health_overhead_size');
        if ($overhead_size === false) {
            $overhead_size = 0;
            $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
            foreach ($tables as $table) {
                $overhead_size += $table['Data_free'];
            }
            set_transient('srn_health_overhead_size', $overhead_size, SRN_CACHE_DURATION);
        }
        return $overhead_size;
    }

    /**
     * Retourne si le plugin est en mise à jour automatique
     * 
     * @return bool Vrai si la mise à jour automatique est activée, sinon faux
     */
    public static function isSelfAutoUpdateEnabled(): bool
    {
        $auto_updates = get_site_option( 'auto_update_plugins', array() );
        
        if (in_array('srn-health/srn-health.php', $auto_updates)) {
            return true;
        }
        return false;
    }
}