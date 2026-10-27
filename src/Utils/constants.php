<?php

define("SRN_CACHE_DURATION", HOUR_IN_SECONDS);

// Chemin du fichier principal du plugin, quel que soit le nom du dossier
define("SRN_HEALTH_PLUGIN_FILE", dirname(__DIR__, 2) . "/srn-health.php");

// Clé publique Ed25519 de l'application Nuxt (32 octets en base64 standard), non secrète
// TODO : valeur provisoire, à remplacer par la vraie clé publique
define("SRN_HEALTH_PUBLIC_KEY", "ZL4ThBhrRNJsnQBiPgamBHbArp9CUeHhNfSB471iwdA=");

// Écart maximal toléré (en secondes) entre l'horodatage signé et l'heure du serveur
define("SRN_HEALTH_SIGNATURE_MAX_AGE", 300);