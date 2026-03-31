<?php

namespace Src\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class VolumeService
{
    /**
     * Liste des patterns à exclure lors du calcul de la taille du site
     * 
     * @var array
     */
    protected static $exclude_patterns = [
        'backups-dup-lite', // Dossier par défaut Duplicator
        'wp-snapshots',     // Anciens dossiers Duplicator
        '_archive.zip',     // Extension archive Duplicator
        '_archive.daf',     // Format propriétaire Duplicator
        '.tar.gz',          // Autres sauvegardes courantes
        'node_modules'      // Pour nettoyer si tu as des restes de dev
    ];

    /**
     * Calcule et retourne la taille du serveur
     *
     * @return int La taille totale du serveur en octets
     */
    public static function getSiteSize(): int
    {
        $site_size = get_transient('srn_health_site_size');
        if ($site_size === false) {
            $site_size = self::srn_health_dir_size(ABSPATH);
            set_transient('srn_health_site_size', $site_size, SRN_CACHE_DURATION);
        }

        return $site_size;
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
     * Calcule et retourne la taille d'un répertoire
     *
     * @param string $path Le chemin du répertoire
     * @return int La taille totale du répertoire en octets
     */
    private static function srn_health_dir_size($path)
    {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $file_path = $file->getRealPath();

            // Vérification de l'exclusion
            $should_exclude = false;
            foreach (self::$exclude_patterns as $pattern) {
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

    /**
     * Supprime les données mises en cache
     * 
     * @return void
     */
    public static function clearVolumeCache(): void
    {
        delete_transient('srn_health_db_size');
        delete_transient('srn_health_site_size');
        delete_transient('srn_health_overhead_size');
    }

    /**
     * Obtient un arbre des dossiers et fichiers du site avec leur taille, en excluant les patterns définis
     * 
     * @param int $max_depth Profondeur maximale de l'arborescence (-1 = illimitée)
     * @return array Un tableau représentant l'arborescence des fichiers et dossiers avec leur taille
     */
    public static function getSiteVolumeTree(int $max_depth = -1): array
    {
        return self::buildTree(rtrim(ABSPATH, DIRECTORY_SEPARATOR), $max_depth);
    }

    /**
     * Construit récursivement l'arborescence des fichiers et dossiers
     *
     * @param string $path Le chemin du répertoire à explorer
     * @param int $max_depth Profondeur maximale (-1 = illimitée)
     * @param int $current_depth Profondeur actuelle (usage interne)
     * @return array L'arborescence des fichiers et dossiers avec leur taille
     */
    private static function buildTree(string $path, int $max_depth = -1, int $current_depth = 0): array
    {
        $tree = [];
        $items = @scandir($path);
        if ($items === false) {
            return $tree;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;

            $should_exclude = false;
            foreach (self::$exclude_patterns as $pattern) {
                if (strpos($full_path, $pattern) !== false) {
                    $should_exclude = true;
                    break;
                }
            }

            if ($should_exclude) {
                continue;
            }

            $is_dir = is_dir($full_path);
            $node = [
                'name' => $item,
                'path' => $full_path,
                'type' => $is_dir ? 'dir' : 'file',
                'size' => $is_dir ? self::srn_health_dir_size($full_path) : filesize($full_path),
            ];

            if ($is_dir && ($max_depth === -1 || $current_depth < $max_depth)) {
                $node['children'] = self::buildTree($full_path, $max_depth, $current_depth + 1);
            }

            $tree[] = $node;
        }

        usort($tree, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $tree;
    }
}