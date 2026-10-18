=== Sportlink Club Viewer ===
Contributors: patrickst1991
Tags: sportlink, nevobo, voetbal, wedstrijdprogramma, uitslagen, standen, sportvereniging
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Toon het wedstrijdprogramma, uitslagen, kleedkamerindeling en standen van je sportvereniging (Sportlink of Nevobo) op je WordPress-site.

== Description ==

Sportlink Club Viewer brengt het [ClubInfoBoard](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer) wedstrijdscherm naar WordPress. Plaats een shortcode of het blok "Sportlink Club Viewer" op een pagina en je bezoekers zien live:

* het wedstrijdprogramma van de komende dagen
* de uitslagen van de afgelopen dagen
* kleedkamer- en veldindeling voor de thuiswedstrijden van vandaag
* de stand van één team, of een overzicht van meerdere elftallen naast elkaar

Ondersteunde databronnen: Sportlink Club.DataService (client ID), de Sportlink-app (gebruikersnaam en wachtwoord) en de publieke Nevobo API (volleybal).

**Gebouwd voor WordPress**

* Alle gegevens worden door de server opgehaald en gecachet. Inloggegevens komen nooit in de browser van een bezoeker terecht en Sportlink wordt maar één keer per interval bevraagd, hoeveel bezoekers je ook hebt.
* Instellingen via een normale WordPress-beheerpagina met de mediabibliotheek voor achtergrond en sponsors.
* Shortcode-attributen om per pagina af te wijken van de instellingen.
* Updates verschijnen gewoon op je Plugins-pagina.
* Bij verwijderen ruimt de plugin al zijn opties en cache op.

== Installation ==

1. Download de nieuwste zip van de [releasepagina](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress/releases/latest).
2. Ga naar **Plugins → Nieuwe plugin → Plugin uploaden**, upload de zip en activeer de plugin.
3. Ga naar **Instellingen → Sportlink Viewer**, kies je sport en verbindingstype en test de verbinding.
4. Plaats `[sportlink_match_display]` (of een van de andere shortcodes) op een pagina.

== Shortcodes ==

* `[sportlink_match_display days="14" results_days="30" height="700" switch="no" sponsors="no"]`
* `[sportlink_prematch_display refresh="10" height="600"]`
* `[sportlink_standing_display team="123" pool="456"]`
* `[sportlink_standings_overview columns="3" full_width="no"]`

Alle attributen zijn optioneel; zonder attribuut geldt de opgeslagen instelling.

== Frequently Asked Questions ==

= De weergave blijft op "laden" staan =

De weergaven halen hun gegevens op via de REST API van je site (`/wp-json/scv/v1/`). Sommige beveiligingsplugins blokkeren de REST API voor niet-ingelogde bezoekers; sta de namespace `scv/v1` toe. Op **Instellingen → Sportlink Viewer → Support** staat een link naar het statusadres om dit te controleren.

= Hoe snel zie ik wijzigingen uit Sportlink? =

Het programma wordt 5 minuten gecachet, uitslagen 10 minuten, standen 15 minuten en de kleedkamerindeling 30 seconden. Met **Cache legen** op het tabblad Support worden de gegevens direct opnieuw opgehaald.

= Waar worden teamlogo's opgeslagen? =

In `wp-content/uploads/scv-logos/`. Ze worden één keer opgehaald en daarna van je eigen site geserveerd.

== Changelog ==

= 1.1.0 =
* Gegevens worden nu op de server opgehaald en gecachet via `wp-json/scv/v1`; inloggegevens en API-sleutels staan niet meer in de pagina.
* Teamlogo's worden in de uploads-map gecachet.
* Automatische updates vanaf GitHub-releases.
* Shortcode-attributen voor dagen, hoogte, wisselen, sponsors, team en competitie.
* Knop "Cache legen" en REST-status op het tabblad Support.
* Blok-optie voor het standenoverzicht.
* Kleedkamerinformatie voor voetbal via Sportlink Proxy werkte niet (verkeerde host).
* Nevobo-clublijst toonde maar 600 van de 1744 verenigingen.
* "Test verbinding" voor Sportlink API gaf altijd een fout.
* Scrollen stopte na twee rondes op het programmascherm.
* Opruimen bij verwijderen van de plugin (`uninstall.php`).

= 1.0.5 =
* Vorige release.
