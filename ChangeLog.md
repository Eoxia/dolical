# ChangeLog

## 1.2.0

- Import d'un agenda depuis son fichier `.ics` (Nextcloud, Google Agenda, Outlook, Thunderbird…) : page « Importer un
  agenda », dans son propre agenda ou, avec le droit sur les événements des autres, dans celui d'un collègue. Les
  participants (organisateur compris) sont retrouvés par leur e-mail, ou à défaut par leur nom, avec leur réponse, et
  le tiers est relié ; les répétitions deviennent des séries, les rendez-vous annulés sont laissés de côté, aucun
  e-mail n'est envoyé. Un fichier réimporté ne crée pas de doublon et complète les rendez-vous déjà importés des
  participants et du tiers retrouvés depuis ; le bilan liste les personnes introuvables. Les imports précédents sont
  listés (clé native `import_key` sur leurs événements) et chacun se supprime d'un coup en cas d'erreur.
- Synchronisation : un rendez-vous créé sur le téléphone garde les réponses déjà données par les participants,
  retrouve participants et tiers de la même façon, et une série dont la première date est annulée commence à la
  suivante.
- Nouvelle entrée de menu : réactiver le module pour la voir.

## 1.1.0

- Plus aucune dépendance à Saturne ni à un autre module externe : le module ne demande que Agenda et Tiers, pour
  pouvoir rejoindre le cœur de Dolibarr.
- Style et scripts livrés tels quels (`css/dolical.css`, `js/dolical.js`, `js/modules/`), sans compilation.
- L'icône du menu du haut garde ses couleurs, comme dans la liste des modules (`css/dolical_menu.css`).
- Le compte-rendu redevient un texte : la dictée, le bloc médias et la médiathèque sont retirés ; les fichiers du
  rendez-vous restent ses pièces jointes.

## 1.0.0

- Calendrier d'équipe sur les événements natifs : vues Jour (une colonne par personne), Semaine, Équipe, Mois et Liste.
- Sélection de plusieurs agendas, par personne ou par groupe d'utilisateurs, mémorisée par navigateur.
- Glisser-déposer : déplacement, durée, passage d'une personne à une autre.
- Formulaire : participants internes et externes, tiers, projet, plusieurs documents liés, pièces jointes.
- Carte de l'événement : participants et réponses, facturation (devis, factures, reste à payer), pièces jointes.
- Bouton « Planifier dans l'agenda » sur les fiches des documents, tiers, projets et contacts.
- Invitations par e-mail : contact créé depuis une simple adresse, lien de réponse signé vers une page publique,
  fichier `.ics`, e-mail de modification, notification de l'organisateur, modèles d'e-mails Dolibarr.
- Annulation envoyée aux invités (suppression, participant retiré), relance manuelle et automatique des invités sans
  réponse, réponses visibles dans les vues, avertissement de conflit de disponibilité dans le formulaire.
- Rappels natifs dans le formulaire : interrupteur, délai, notification du navigateur et/ou e-mail, pour soi ou pour
  tous ; ils suivent l'événement déplacé.
- Événements récurrents : séries natives (`recurid`), modification et suppression de « cet événement », « celui-ci et
  les suivants » ou « tous », un seul e-mail par série pour les invités (`RRULE`, fuseau, exceptions).
- Compte-rendu après le rendez-vous : proposé sur la carte et dans le calendrier, écrit ou dicté (mémo vocal rédigé
  par le module IA), photos, documents, médiathèque et glisser-déposer via le bloc médias de Saturne.
- Synchronisation CalDAV dans les deux sens (DAVx⁵, iPhone, Mac, Thunderbird), agendas de collègues en lecture
  seule, page « Synchroniser mon téléphone ».
