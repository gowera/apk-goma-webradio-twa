# GOMA WEBRADIO — Récompenses Application

Plugin WordPress installable, version 1.2.0. WordPress 6.5+, PHP 8.0+, MySQL/MariaDB avec tables InnoDB et HTTPS requis. Les comptes et toutes les opérations de points restent sur votre WordPress ; aucun compte ni solde n’est enregistré dans Lovable Cloud.

## Installation

1. Sauvegarder WordPress et sa base de données.
2. Dans WordPress : **Extensions → Ajouter une extension → Téléverser une extension**, choisir `goma-app-rewards.zip`, installer puis activer.
3. Ouvrir **GOMA Récompenses**. Vérifier l’adresse de l’application : `https://app.gomawebradio.com`.
4. Dans **Origines autorisées**, garder l’adresse exacte de l’application et ajouter l’adresse HTTPS exacte de votre déploiement Vercel si utilisée. Une origine par ligne, sans chemin, slash final ni joker.
5. Vérifier que WordPress envoie réellement les emails (configurer votre service SMTP si nécessaire). Les nouveaux comptes doivent confirmer leur email ; aucun accès avant confirmation. « Mot de passe oublié » renvoie aussi la confirmation aux comptes non vérifiés.
6. Régler le coût et le stock réel des t-shirts/chapeaux puis les activer. Ils sont désactivés au départ : aucun stock ni coût n’a été inventé.
7. Choisir le bonus de parrainage : **0 par défaut**, car aucun montant n’a été fixé. Le suivi et le classement fonctionnent même sans bonus.
8. Publier les nouvelles pages de l’application, puis tester avec un vrai email depuis le téléphone.

Ne pas installer les scripts de test sur votre site de production. Les fichiers `tests/` du projet sont réservés à une installation WordPress isolée et sont exclus du ZIP installable.

## Rappels hebdomadaires (version 1.1.0)

Installer le nouveau ZIP en remplaçant l’extension existante : les comptes et points sont conservés. Les rappels sont activés par défaut et désactivables dans **GOMA Récompenses → Emails hebdomadaires**. Chaque compte application avec email confirmé reçoit le bilan de la dernière semaine complète (lundi au dimanche, fuseau horaire WordPress), y compris 0 point si aucune action. L’email indique les points gagnés par les actions, le solde actuel, le barème, un encouragement et le lien vers les cadeaux. Les dépenses, remboursements et ajustements manuels ne modifient pas le total des gains d’actions.

Envoi via `wp_mail`, donc le service email/SMTP du site, sans service Lovable. WP-Cron traite jusqu’à 100 destinataires par heure ; prévoir un cron serveur régulier si le site est peu visité. Lors de l’activation, le premier passage envoie la dernière semaine complète. Chaque utilisateur/semaine a une entrée unique dans la table `gwr_emails`. Les envois acceptés ne sont pas renvoyés ; un échec connu est retenté jusqu’à 3 fois avec une heure entre les tentatives. Un résultat inconnu après interruption n’est pas renvoyé automatiquement pour éviter les doublons. Les statistiques conservées 90 jours apparaissent dans l’administration. Acceptation par SMTP ne garantit pas la réception en boîte email.

Un lien signé permet au destinataire de confirmer son désabonnement sans connexion, sans supprimer son compte ni ses points. Exclure `?gwr_email_unsubscribe=...` des caches. Les confirmations d’inscription et récupérations de mot de passe restent actives. Mettre à jour la politique de confidentialité pour mentionner ces rappels et tester un email réel après installation.

## Pages de l’application

- `/inscription` : nom affiché, email, mot de passe, confirmation, code de parrainage facultatif et acceptation des conditions.
- `/connexion`, `/mot-de-passe-oublie`, `/verification`, `/reset-password` : accès et récupération du compte.
- `/recompenses` : solde, lien d’invitation, progression de l’écoute, cadeaux, demandes, classement et historique.

Ces comptes sont créés par le plugin pour l’application. Les comptes déjà présents sur le site ne sont pas automatiquement convertis. Un email déjà utilisé ne peut pas créer un second compte ; une migration des anciens comptes doit être décidée séparément.

## Bonus de bienvenue (version 1.2.0)

Chaque nouveau compte reçoit **30 points de bienvenue** à l’inscription, crédités par le serveur une seule fois par compte (montant modifiable dans le barème, 0 pour désactiver). Le bonus apparaît dans l’historique des points et dans le bilan hebdomadaire de la semaine d’inscription.

Cette version corrige aussi l’autorisation d’accès depuis l’application : l’origine de l’adresse configurée dans **Adresse application** est désormais toujours autorisée, même si la liste des origines est incomplète (port conservé lorsqu’il est non standard). Les demandes de contrôle OPTIONS et les réponses d’erreur conservent les autorisations nécessaires. Les autres origines restent refusées. Si la création de compte affichait « Impossible de joindre le programme de points », ajoutez aussi l’adresse HTTPS exacte de l’aperçu Lovable ou du déploiement Vercel dans **Origines autorisées**, si vous testez depuis ces adresses. La mise à jour conserve les réglages existants : elle ne remplace pas votre liste d’origines.

## Barème initial

| Action | Points | Validation et limite initiale |
| --- | ---: | --- |
| Bonus de bienvenue | 30 | une seule fois par compte, à l’inscription |
| Article lu | 3 | 30 secondes de visibilité dans l’app ; une fois par article ; 20 articles/jour |
| Application partagée | 7 | partage confirmé par le téléphone ; une fois/jour |
| Article partagé | 6 | une fois par article/jour ; 10 articles/jour |
| Radio en direct | 30 | heure complète de 60 minutes ; 4 heures récompensées/jour |
| Parrainage qualifié | 0 | compte filleul avec email confirmé et première action récompensée |

Les montants et plafonds quotidiens sont modifiables par l’administrateur. Les plafonds sont des valeurs initiales de protection, pas des montants demandés par GOMA WEBRADIO. Les journées sont calculées en UTC.

L’écoute est suivie par battements toutes les 30 secondes et temps serveur. Une pause explicite ou fermeture annule la progression de l’heure incomplète. L’application affiche un avertissement lors de la pause ; le navigateur peut afficher sa confirmation lors de la fermeture, mais Android ne garantit pas cet avertissement si l’application est tuée. Un intervalle supérieur à 90 secondes réinitialise le suivi ; une interruption tolérée compte au maximum 45 secondes par battement. Les heures déjà récompensées restent acquises. Les restrictions Android en arrière-plan peuvent arrêter les battements même si le son continue : ce plugin ne garantit pas le suivi lorsque le système suspend l’application.

## Limites honnêtes de validation

Un navigateur ne peut pas prouver une installation depuis Google Play, la publication effective d’un partage social ou l’attention de l’auditeur. Le plugin suit des déclarations avec limites, sessions uniques et détection des doublons ; il ne constitue pas une preuve inviolable d’écoute. Le parrainage est donc qualifié par confirmation email et première récompense, **pas par téléchargement vérifié**. Pour une campagne à forte valeur, ajouter une validation manuelle/antifraude avant remise des cadeaux.

Les liens d’invitation ouvrent une page WordPress avec le lien Google Play et le code : après installation, le filleul doit utiliser ce code à l’inscription. Le code n’est pas transmis automatiquement à travers le téléchargement Play Store.

## Administration

Dans **GOMA Récompenses** : barème, plafonds, cadeaux/coût/stock, demandes (remise ou annulation avec remboursement et remise en stock), auditeurs, classement et journal. Ajustements manuels avec motif et identifiant administrateur enregistrés. Les demandes ne génèrent pas automatiquement un email de remise : l’équipe doit contacter l’auditeur à l’adresse indiquée dans l’administration.

Le solde et le stock sont modifiés sous transaction avec verrouillage ; les récompenses et réservations sont idempotentes. Les comptes ont un rôle sans droits d’administration. Les jetons de session aléatoires sont stockés hachés sur WordPress (validité 7 jours), et seulement dans la session du navigateur côté app : après fermeture de cette session, une reconnexion peut être nécessaire. La récupération de mot de passe révoque les anciennes sessions. Les liens email sont temporaires et à usage unique.

Autoriser l’en-tête `Authorization` jusqu’à PHP si votre hébergeur ou pare-feu le filtre. Exclure `/wp-json/goma-app/v1/*` et les invitations `?gwr_invite=...` des caches/CDN. Ne jamais réécrire les réponses privées en pages HTML. Aucune clé privée ni mot de passe n’est inclus dans le plugin.

## Données et sauvegardes

Tables `gwr_accounts`, `gwr_sessions`, `gwr_ledger`, `gwr_listens`, `gwr_reads`, `gwr_gifts`, `gwr_claims`, préfixées selon WordPress. Les utilisateurs sont dans la table utilisateurs WordPress avec le rôle application. Le nettoyage quotidien supprime les sessions expirées et suivis temporaires ; les comptes, soldes et journaux restent conservés.

Désactiver ou supprimer l’extension ne supprime pas les points. Supprimer un utilisateur WordPress supprime ses données du plugin. Mettre à jour vos conditions, politique de confidentialité et durée de conservation, et prévoir des sauvegardes avant lancement.

## Vérification après installation

Créer un compte neuf, confirmer son email, se connecter, lire un article pendant 30 secondes, partager depuis Android, écouter une heure sans pause, puis contrôler le journal WordPress. Définir un cadeau de test et vérifier réservation, débit, annulation et remboursement. Les essais réalisés dans l’environnement isolé ne remplacent pas ce contrôle sur votre hébergement réel, notamment pour les emails et le suivi en arrière-plan Android.

API : `https://gomawebradio.com/wp-json/goma-app/v1`. `config` est public ; les autres données personnelles nécessitent le jeton Bearer du compte application. POST JSON pour les actions ; GET pour `me`, `gifts` et `leaderboard`.