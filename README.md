# Sportlink Club Viewer — WordPress Plugin

Een WordPress-plugin die het [ClubInfoBoard](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer) wedstrijdscherm naar elke WordPress-site brengt. Toon live wedstrijdschema's, uitslagen en voorwedstrijdinformatie over kleedkamers en velden op elke pagina of elk bericht via shortcodes.

## Functies

- Live wedstrijdschema en uitslagen via de Sportlink API, Sportlink Proxy of Nevobo (volleybal)
- Voorwedstrijdinformatie: kleedkamerverdeling en veldindeling
- Stand van één team (`[sportlink_standing_display]`)
- Standen-overzicht: meerdere elftallen naast elkaar op één pagina (`[sportlink_standings_overview]`)
- Automatisch wisselen tussen schema en uitslagen
- Aanpasbaar kleurenschema (5 kolommen, achtergrond + tekst per kolom)
- Sponsorafbeeldingenbalk (maximaal 13 afbeeldingen, uit de mediabibliotheek of via URL)
- Aangepaste achtergrondafbeelding via de WordPress-mediabibliotheek
- Volledige configuratie via een native WordPress-beheerpagina
- Geen externe afhankelijkheden buiten de plugin zelf

## Vereisten

- WordPress 5.9 of hoger
- PHP 7.4 of hoger
- Een actieve Sportlink API client-ID, Sportlink Proxy-inloggegevens of een Nevobo-clubidentificatie

## Installatie

1. Download de nieuwste release-zip vanaf de [Releases](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress/releases)-pagina.
2. Ga in het WordPress-beheer naar **Plugins → Nieuwe plugin → Plugin uploaden**.
3. Upload de zip en klik op **Plugin activeren**.
4. Navigeer naar **Instellingen → Sportlink Viewer** en doorloop de installatie.

## Configuratie

De instellingenpagina heeft drie tabbladen:

### Instellingen (Algemeen)

| Instelling | Omschrijving |
|---|---|
| Sport | Kies je sport (Voetbal, Basketbal, Korfbal, Volleybal, Waterpolo, Hockey België, Handbal, Soft- en Honkbal) |
| Verbindingstype | Sportlink API, Sportlink Proxy of Nevobo Proxy |
| Client ID | Je Sportlink API client-ID (alleen bij Sportlink API) |
| Gebruikersnaam / Wachtwoord | Sportlink-app-inloggegevens (alleen bij Sportlink Proxy) |
| Club identifier | Je Nevobo-clubcode, bijv. `NELO123` (alleen bij Nevobo Proxy) |
| Club ophalen | Beschikbare clubs ophalen en je club kiezen uit de vervolgkeuzelijst |
| Programma dagen | Hoeveel dagen vooruit geplande wedstrijden worden getoond (standaard 7) |
| Uitslag dagen | Hoeveel dagen terug uitslagen worden getoond (standaard 7) |
| Voorwedstrijd verversing | Automatisch verversingsinterval in seconden voor het voorwedstrijdscherm (standaard 15) |
| Scherm automatisch wisselen | Automatisch wisselen tussen schema en uitslagen na 2 scrollcycli in- of uitschakelen |
| Sponsors weergeven | De sponsorafbeeldingenbalk tonen |
| Achtergrondafbeelding | URL van de achtergrondafbeelding (kies uit de mediabibliotheek of voer direct in) |
| Debug modus | Ruwe API-antwoorden naar de browserconsole loggen (F12) |

#### Team standen (één team)

Voor de shortcode `[sportlink_standing_display]`:

| Instelling | Omschrijving |
|---|---|
| Standen weergeven | Schakelt de stand-functionaliteit aan/uit |
| Team | Kies het team waarvan je de stand wilt tonen (klik eerst **Teams ophalen**) |
| Competitie | Kies de competitie/poule waarin het team speelt |

#### Standen overzicht (meerdere elftallen)

Voor de shortcode `[sportlink_standings_overview]` — toont de standen van meerdere elftallen naast elkaar in een raster.

| Instelling | Omschrijving |
|---|---|
| Aantal kolommen | Automatisch (responsief, zoveel mogelijk naast elkaar) of een vast aantal van 1–6 kolommen |
| Volledige paginabreedte | Breekt uit het inhoudsgebied van het thema zodat het overzicht de volledige paginabreedte gebruikt (handig als je thema een smalle inhoudsbreedte heeft). Schakel uit als dit problemen geeft, bijv. bij een sidebar |
| Elftallen | Repeatbare lijst — voeg per elftal een team + competitie toe via de twee dropdowns. Klik **+ Elftal toevoegen** voor extra rijen, of het prullenbakje om een rij te verwijderen |

Teams die in meerdere competities spelen (bijv. beker én reguliere competitie) worden in de team-dropdown als één keuze getoond — bij het kiezen van een competitie zie je alle pools waarin dat team speelt.

### Stijl

Kies achtergrond- en tekstkleuren voor elk van de vijf weergavekolommen via de WordPress-kleurkiezer. Een livevoorbeeld wordt bijgewerkt terwijl je kiest.

### Sponsors

Voeg sponsorafbeeldingen toe vanuit de WordPress-mediabibliotheek of via een directe URL. Maximaal 13 sponsors.

## Shortcodes

| Shortcode | Omschrijving |
|---|---|
| `[sportlink_match_display]` | Wedstrijdschema voor de komende *n* dagen; schakelt automatisch over naar uitslagen na 2 scrollcycli (indien ingeschakeld) |
| `[sportlink_prematch_display]` | Voorwedstrijdinformatie (kleedkamers + veld) voor wedstrijden die binnen de komende 3 uur beginnen |
| `[sportlink_standing_display]` | Stand van één team in zijn competitie/poule (configureer onder **Team standen**) |
| `[sportlink_standings_overview]` | Standen van meerdere elftallen naast elkaar in een raster, voor een overzichtspagina (configureer onder **Standen overzicht**) |

**Tip:** gebruik een paginasjabloon op volledige breedte of zonder kop- en voettekst voor een strak weergaveschermformaat.

## Bouwen vanuit broncode

De Vue 3-frontend bevindt zich in `vue-app/`. Bouw de bundel opnieuw na het maken van wijzigingen:

```bash
cd vue-app
npm install
npm run build
```

De gecompileerde bestanden worden weggeschreven naar `assets/dist/` en samen met de PHP-bronbestanden opgeslagen, zodat een Node.js-omgeving niet vereist is voor een standaardinstallatie.

## Licentie

MIT — vrij te gebruiken, aan te passen en te verspreiden.
