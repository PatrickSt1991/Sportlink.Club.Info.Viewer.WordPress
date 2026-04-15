# Sportlink Club Viewer — WordPress Plugin

A WordPress plugin that brings the [ClubInfoBoard](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer) match display to any WordPress site. Embed live match schedules, results, and pre-match room/field information on any page or post using shortcodes.

## Features

- Live match schedule and results via Sportlink API, Sportlink Proxy, or Nevobo (volleyball)
- Pre-match information: dressing room assignments and field layout
- Automatic screen switching between schedule and results
- Customisable colour scheme (5 columns, background + text each)
- Sponsor image bar (up to 13 images, from media library or URL)
- Custom background image via the WordPress media library
- Full configuration through a native WordPress admin settings page
- No external dependencies beyond the plugin itself

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- An active Sportlink API client ID, Sportlink Proxy credentials, or a Nevobo club identifier

## Installation

1. Download the latest release zip from the [Releases](https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress/releases) page.
2. In WordPress Admin go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Activate Plugin**.
4. Navigate to **Instellingen → Sportlink Viewer** and complete the setup.

## Configuration

The settings page has three tabs:

### Instellingen (General)

| Setting | Description |
|---|---|
| Sport | Choose your sport (Voetbal, Basketbal, Korfbal, Volleybal, Waterpolo, Hockey België, Handbal, Soft- en Honkbal) |
| Verbindingstype | Sportlink API, Sportlink Proxy, or Nevobo Proxy |
| Client ID | Your Sportlink API client ID (Sportlink API only) |
| Gebruikersnaam / Wachtwoord | Sportlink app credentials (Sportlink Proxy only) |
| Club identifier | Your Nevobo club code, e.g. `NELO123` (Nevobo Proxy only) |
| Club ophalen | Fetch available clubs and pick yours from the dropdown |
| Programma dagen | How many days ahead to show scheduled matches (default 7) |
| Uitslag dagen | How many days back to show results (default 7) |
| Voorwedstrijd verversing | Auto-refresh interval in seconds for the pre-match display (default 15) |
| Scherm automatisch wisselen | Toggle automatic switching between schedule and results after 2 scroll cycles |
| Sponsors weergeven | Show the sponsor image bar |
| Achtergrondafbeelding | Background image URL (pick from media library or enter directly) |
| Debug modus | Log raw API responses to the browser console (F12) |

### Stijl (Style)

Pick background and text colours for each of the five display columns using the WordPress colour picker. A live preview updates as you choose.

### Sponsors

Add sponsor images from the WordPress media library or by direct URL. Maximum 13 sponsors.

## Shortcodes

| Shortcode | Description |
|---|---|
| `[sportlink_match_display]` | Match schedule for the next *n* days, auto-switches to results after 2 scroll cycles (if enabled) |
| `[sportlink_prematch_display]` | Pre-match information (dressing rooms + field) for matches starting within the next 3 hours |

**Tip:** use a full-width or blank page template (no header/footer) for a clean display-screen look.

## Building from Source

The Vue 3 frontend lives in `vue-app/`. After making changes, rebuild the bundle:

```bash
cd vue-app
npm install
npm run build
```

The compiled assets are written to `assets/dist/` and committed alongside the PHP source so a Node.js environment is not required for a standard installation.

## License

MIT — free to use, modify, and distribute.
