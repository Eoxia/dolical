# DoliCal

Agenda d'équipe pour Dolibarr, dans la famille ReedCRM. Il s'appuie **uniquement sur les événements natifs** de
l'agenda Dolibarr : aucune table ajoutée, tout ce que l'on voit et modifie reste visible dans l'agenda standard.

## Ce que fait le module

- **Plusieurs agendas en même temps** : on coche les personnes (ou un groupe d'utilisateurs entier) dans la colonne de
  gauche, chacune avec sa couleur (celle de sa fiche utilisateur, sinon une couleur stable).
- **Cinq vues** :
  - *Jour* : une colonne par personne, côte à côte ;
  - *Semaine* : les sept jours, tous les agendas superposés ;
  - *Équipe* : une ligne par personne, les jours de la semaine en colonnes ;
  - *Mois* et *Liste* (deux semaines, pratique sur téléphone).
- **Glisser-déposer** : déplacer un événement dans le temps, l'étirer par le bas, ou le faire passer de la colonne d'une
  personne à celle d'une autre (vues Jour et Équipe).
- **Création en un clic** sur un créneau vide, et bouton « Planifier dans l'agenda » sur les fiches devis, commande,
  facture, contrat, intervention, ticket, expédition, documents fournisseurs, tiers, projet et contact : le formulaire
  s'ouvre déjà rempli.
- **Formulaire complet** : organisateur, collègues invités, contacts externes, tiers, projet, plusieurs documents liés
  (devis, commandes, factures, contrats, tickets…), description et pièces jointes (glisser-déposer).
- **Carte au clic** sur un événement : organisateur, dates et durée, lieu (copie, carte), participants avec leur réponse
  (acceptée, peut-être, refusée, en attente), copie des e-mails et e-mail groupé, tiers, projet, **facturation** (devis
  et factures, montant, reste à payer, retard de paiement), autres éléments liés, pièces jointes (dépôt direct sur la
  carte), description, auteur. Réponse à l'invitation, passage à « réalisé », duplication, suppression.

## Invitations par e-mail

- Dans le formulaire, on choisit un contact ou on **tape une adresse e-mail** (« Jean Dupont <jean@exemple.fr> ») :
  le contact est retrouvé, ou créé et rattaché au tiers de l'événement.
- Case « Envoyer les invitations par e-mail » (cochée d'office dès qu'on ajoute quelqu'un ou qu'on change la date ou
  le lieu) : les nouveaux participants reçoivent l'invitation, et tous reçoivent une « Modification » si la date ou
  le lieu change. Bouton « Inviter » et envoi par personne sur la carte.
- L'e-mail contient un bouton vers une **page publique sans compte** (Oui / Peut-être / Non) et un fichier `.ics`
  pour l'ajouter à Outlook, Gmail ou Apple Calendrier. Le lien est signé avec la clé de l'instance, pour une personne
  et un événement : rien à stocker, impossible à deviner.
- La réponse s'enregistre dans le champ natif `answer_status`, l'organisateur reçoit un e-mail (réglable).
- **Annulation** : supprimer l'événement, ou en retirer quelqu'un avec la case cochée, envoie un e-mail « Annulé » et
  un `.ics` d'annulation qui retire l'événement de l'agenda des personnes invitées.
- **Relance** : bouton « Relancer (N) » sur la carte pour les invités sans réponse, et relance automatique optionnelle
  quelques jours avant (tâche planifiée « Relance des invités DoliCal », une seule fois par personne).
- **Réponses dans le calendrier** : un événement refusé est barré dans la colonne de la personne, « peut-être » est en
  pointillés, et chaque événement affiche le décompte ✓ / ? / ✗ / en attente.
- **Disponibilités** : le formulaire avertit quand un collègue invité est déjà pris sur le créneau.
- Chaque envoi et chaque réponse laissent un événement automatique natif (onglet Événements du tiers et du contact).
- Les textes sont modifiables dans *Configuration > E-mails > Modèles* (types « DoliCal – … »), sinon un texte par
  défaut traduit est utilisé.

## Rappels

- Interrupteur « Me rappeler l'événement » dans le formulaire, allumé par défaut (réglable) : délai en minutes, heures,
  jours ou semaines, par **notification du navigateur** et/ou **e-mail**, pour soi seul ou pour tous les participants
  internes.
- Ce sont les rappels natifs (`llx_actioncomm_reminder`) : la notification s'affiche sur toutes les pages de Dolibarr,
  l'e-mail part par la tâche planifiée native « SendEmailsReminders ». Ils suivent l'événement quand on le déplace.
- Le formulaire demande l'autorisation des notifications au navigateur, et prévient quand elles sont bloquées.

## Événements récurrents

- « Ne se répète pas », tous les jours, jours ouvrés, chaque semaine le lundi, toutes les deux semaines, chaque mois
  (le 2, ou le premier lundi), chaque année, jusqu'à une date. Les libellés suivent le jour choisi.
- Comme dans l'agenda natif, chaque date est un événement à part entière (ses propres réponses, son compte-rendu, ses
  pièces jointes), les dates d'une série partageant `recurid` ; la règle est écrite en syntaxe iCalendar dans
  `recurrule`, la fin dans `recurdateend`. Une série de l'agenda natif est relue.
- Modifier ou supprimer une date d'une série demande « Cet événement / Cet événement et les suivants / Tous les
  événements ». Changer la règle reconstruit les dates à venir, jamais celles déjà passées ; « et les suivants » coupe
  la série en deux. Glisser-déposer une date ne déplace qu'elle.
- Les invités reçoivent **un seul e-mail pour toute la série** (`.ics` avec `RRULE` et fuseau horaire), une
  exception (`RECURRENCE-ID`) quand une seule date change, et leur réponse vaut pour toutes les dates à venir.

## Compte-rendu après le rendez-vous

- Une fois le rendez-vous terminé, sa carte demande « comment s'est-il passé ? », et le calendrier le propose dans un
  encart le jour même : **Écrire le compte-rendu** ou **Dicter**.
- La dictée utilise le micro du bloc médias de Saturne : le mémo vocal est joint à l'événement, transcrit puis rédigé
  en compte-rendu par le module IA (ChatGPT), comme la description vocale de ReedCRM. Sans IA, le mémo reste joint.
- Même bloc pour les photos (appareil photo, éditeur, médiathèque) et les documents ; une zone accepte aussi le
  glisser-déposer de plusieurs fichiers d'un coup. Tout va dans les documents natifs de l'événement.
- Le texte est le champ complémentaire « Compte-rendu » de l'événement natif (visible aussi dans l'agenda de
  Dolibarr) ; l'événement peut être marqué réalisé au passage.

## Synchronisation avec les téléphones (CalDAV)

- Menu *Synchroniser mon téléphone* : l'adresse, l'identifiant, les étapes pour **DAVx⁵ (Android)**, **iPhone / iPad /
  Mac** et **Thunderbird**. Connexion avec l'identifiant et le mot de passe Dolibarr.
- Serveur CalDAV `dav/caldav.php`, sur la bibliothèque SabreDAV livrée avec Dolibarr. Dans les deux sens : créer,
  déplacer, renommer, supprimer un rendez-vous sur le téléphone met à jour l'événement natif ; accepter ou refuser
  une invitation remplit `answer_status` ; une alarme du téléphone devient un rappel, et inversement.
- Une répétition créée sur le téléphone devient une série native. Un rendez-vous refusé quitte le téléphone ;
  supprimer un rendez-vous dont on n'est qu'invité le refuse.
- Les agendas de collègues choisis sur la page s'ajoutent en lecture seule, chacun avec sa couleur.
- Rien de nouveau n'est stocké : le nom de fichier et l'identifiant qu'un téléphone donne à un événement qu'il crée vont
  dans la référence externe de l'événement natif (`ref_ext = dav:<fichier>|<uid>`).
- Les rendez-vous des 180 derniers jours et tous ceux à venir. HTTPS indispensable en production (l'iPhone refuse un
  compte en http).

## Où sont stockées les données

| Donnée | Stockage natif |
| --- | --- |
| Événement | `llx_actioncomm` |
| Collègues et contacts invités, réponses | `llx_actioncomm_resources` (`user`, `socpeople`, `answer_status`) |
| Premier document lié | `llx_actioncomm.fk_element` / `elementtype` (onglet *Événements* du document) |
| Autres documents liés | `llx_element_element` (type `action`) |
| Pièces jointes, photos et mémos vocaux du compte-rendu | `documents/agenda/<id>` + index ECM |
| Rappels | `llx_actioncomm_reminder` |
| Série | `llx_actioncomm.recurid`, `recurrule`, `recurdateend` |
| Compte-rendu | champ complémentaire `dolical_report` de l'événement |
| Événement créé sur un téléphone | `llx_actioncomm.ref_ext` : `dav:` + nom du fichier + `\|` + identifiant |
| Agendas de collègues synchronisés | paramètre utilisateur `DOLICAL_CALDAV_SHARED` |

## Droits

Ceux du module Agenda : lire / créer / supprimer *mes* événements ou *ceux de tous*. Le module ajoute seulement
« Consulter DoliCal » et l'accès à sa page de configuration.

## Prérequis

Dolibarr 23 ou 24, modules Saturne, Agenda et Tiers.

## Compilation des assets

```sh
cd htdocs/custom/saturne
node build-assets.js --module=dolical
```
