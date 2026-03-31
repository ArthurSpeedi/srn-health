<?php

// Définition du chemin vers le dossier "src", un niveau au-dessus de la racine du document
$_SRC = __DIR__ . "./../../src/";

// Charge les constantes du plugin
require_once $_SRC . "Utils/constants.php";

// Enregistre la fonction d'autoload personnalisée
spl_autoload_register("loadClass");

/**
 * Fonction d'autoload : elle permet de charger automatiquement les classes quand elles sont utilisées.
 * Elle supprime le préfixe de namespace "Src\" et remplace les backslashes (\) par des slashes (/)
 * pour retrouver le fichier correspondant à la classe dans le dossier "src/".
 */
function loadClass($className)
{
    // Supprime le namespace "Src\" du nom de la classe
    $className = str_replace("Src\\", "", $className);
    
    // Remplace les backslashes (\) par des slashes (/) pour construire un chemin de fichier
    $className = str_replace("\\", "/", $className);
    
    // Construit le chemin complet du fichier de la classe (suffixé par ".class.php")
    $file = __DIR__ . "/../../src/" . $className . ".class.php";

    // Inclut le fichier s'il existe
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Supprime un fichier s’il existe.
 * Fonction unlink safe.
 *
 * @param string $fullpath Chemin complet vers le fichier à supprimer
 */
function deleteFile($fullpath)
{
    if (file_exists($fullpath)) {
        unlink($fullpath); // Supprime le fichier
    }
}

/**
 * Fonction pour échapper les caractères spéciaux d’une chaîne pour affichage HTML sécurisé.
 * Utilise htmlspecialchars().
 *
 * @param string $string Chaîne à échapper
 * @return string Chaîne sécurisée pour affichage HTML
 */
function hsc($string)
{
    if (empty($string)) {
        return ""; // Retourne une chaîne vide si la valeur est vide
    } else {
        return htmlspecialchars($string); // Échappe les caractères spéciaux HTML
    }
}

/**
 * Fait un var_dump et termine le script.
 * 
 * @param mixed $var
 * @return never
 */
function var_dump_exit($var){
    var_dump($var);
    exit;
}
