=== Plugin Name ===
Tags: health, site check, performance
Tested up to: 8.4

Un plugin SRN pour vérifier la santé du site.

== Description ==

Plugin personnalisé pour vérifier la santé du site. Propriété de Speedi Rychi Nylon. Ce plugin est utilisé en interne pour vérifier certains aspects de la santé du site, tels que les performances, la sécurité et les mises à jour.

== Changelog ==

= 0.11.0 =
* Sécurité : l'accès à l'API se fait désormais par signature Ed25519 (en-têtes X-Horodatage et X-Signature) au lieu du token partagé. Le site ne contient plus aucun secret, seulement la clé publique.
* La signature porte sur l'horodatage, le domaine, la méthode HTTP et la route : elle n'est valable que pour une seule route et une seule méthode, pendant 5 minutes.
* La constante SRN_HEALTH_TOKEN du wp-config.php n'est plus utilisée et peut être supprimée.
* Mise à jour de la collection Bruno pour signer les requêtes.

= 0.10.0 =
* Ajout de la route /ping pour vérifier rapidement que le site répond et que le token est valide.
* Ajout du résumé de l'outil "Santé du site" de WordPress dans /infos.
* Correction de la détection de la mise à jour automatique lorsque le dossier du plugin est renommé.
* Ajout d'une collection Bruno pour tester l'API en local.

= 0.9 =
* Ajout de la description du plugin.
* Correction relative à la vérification de l'état de la mise à jour automatique.

= 0.8 =
* Ajout de la taille des médias.
* Ajout de la vérification de l'état de la mise à jour automatique.

= 0.7 =
* Hotfix : Correction d'une erreur de syntaxe dans le code.

= 0.6 =
* Refactorisation du code pour améliorer la lisibilité et la maintenabilité.
* Ajout de l'arbre des fichiers du site

= 0.5 =
* Correction d'un bug d'encodage des noms du site
