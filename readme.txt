=== Apprenticeship Connector ===
Contributors:      eparkteam
Donate link:       https://buymeacoffee.com/epark
Tags:              apprenticeships, vacancies, jobs, api, elementor
Requires at least: 6.4
Tested up to:      6.7
Requires PHP:      8.2
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Import UK Government apprenticeship vacancies into WordPress via a two-stage API import, with Gutenberg blocks, Elementor widgets, and Action Scheduler support.

== Description ==

**Apprenticeship Connector** is a professional-grade WordPress plugin for training providers, colleges, and careers portals. It connects directly to the UK Government **Find an Apprenticeship Display Advert API v2** to import, store, and display live apprenticeship vacancies.

= How it works =

The plugin uses a **two-stage import engine**:

1. **Stage 1** – Fetches a paginated list of vacancy references (up to 25,000 per run) and queues them for detail fetching.
2. **Stage 2** – Fetches full vacancy details for each reference via Action Scheduler, respecting the API rate limit (configurable, default 2 s per request).

Each imported vacancy becomes a custom post type (`appcon_vacancy`) with all structured meta (employer, wage, course, location, closing date, etc.) ready for templating.

= Key Features =

* **Import Jobs** – Create multiple import jobs with separate API keys, filters, field mappings, and schedules (hourly / daily / weekly).
* **Visual Field Mapper** – Drag-and-drop style interface to map API fields → WordPress post/meta/taxonomy fields using dot notation. Loads a live Stage 2 sample to browse the real API structure.
* **Two-Stage Import Engine** – Handles datasets of 10,000+ vacancies reliably using Action Scheduler for non-blocking background processing.
* **Gutenberg Blocks** – `Vacancy Listing` block (filterable list/grid/table with search and pagination) and `Vacancy Card` block (works in Query Loop).
* **Elementor Widgets** – `Vacancy Listing` widget and `Vacancy Card` widget for Loop Grid templates. Elementor Pro dynamic tags expose all 16 vacancy meta fields.
* **Classic PHP Fallback** – All admin pages render a fully functional PHP UI if the React build is not present.
* **Vacancy Expiry** – Automatically sets vacancies to Draft when their closing date passes (via a daily Action Scheduler action). Dashboard highlights vacancies expiring within 7 days.
* **Archive & Search** – Frontend search with keyword, level, and route filters. Custom `appcon_archived` post status. Admin list table expiry filter.
* **Media Import** – Sideloads employer logos as post thumbnails, with URL-based deduplication.
* **Secure Custom Fields (SCF) Integration** – Full field group registration for rich vacancy editing in the classic editor.
* **Dependency Checker** – Admin notices guide site owners through missing requirements (Composer vendor, Action Scheduler, SCF/ACF).
* **Internationalization** – All strings translated, with British English (en_GB) included. Build scripts generate `.pot` and `.json` files.

= Requirements =

* WordPress 6.4 or higher
* PHP 8.2 or higher
* [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) plugin (recommended for rich editing)
* Composer (to install Action Scheduler; included in pre-built release ZIPs)
* A UK Government Find an Apprenticeship API subscription key – [register here](https://api.apprenticeships.education.gov.uk/)

= Privacy =

This plugin connects to the UK Government Find an Apprenticeship API (`api.apprenticeships.education.gov.uk`). No personal data is transmitted; only the subscription key you configure is sent as a request header. Vacancy data retrieved is stored in your WordPress database.

== Installation ==

= Automatic installation (WordPress.org) =

1. In your WordPress admin, go to **Plugins → Add New**.
2. Search for **Apprenticeship Connector**.
3. Click **Install Now**, then **Activate**.
4. Navigate to **Apprenticeships → Settings** and enter your API subscription key.
5. Go to **Apprenticeships → Import Jobs** and click **Add New Job** to configure your first import.

= Manual installation (ZIP) =

1. Download `apprenticeship-connector.zip` from the [GitHub Releases page](https://github.com/dexit/apprenticeship-connect/releases).
2. In WordPress: **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate.
4. Follow steps 4–5 above.

= From source =

1. Clone the repository and run `composer install --no-dev && npm ci && npm run build`.
2. Copy the resulting folder to `wp-content/plugins/`.

== Frequently Asked Questions ==

= Where do I get an API subscription key? =

Register at [https://api.apprenticeships.education.gov.uk/](https://api.apprenticeships.education.gov.uk/). Free and paid tiers are available. The free tier allows 150 requests per 5 minutes, which the plugin respects with its configurable rate limiter.

= How many vacancies can it import? =

The import engine handles up to 500 pages × 250 vacancies = 125,000 references per Stage 1 run. Stage 2 fetches each in full. On a typical site, a full import of ~10,000 vacancies completes within a few hours depending on your rate-limit setting.

= Does it work with Elementor Free? =

Yes. The `Vacancy Listing` and `Vacancy Card` widgets work with Elementor Free. Dynamic Tags (for single vacancy templates) require Elementor Pro.

= Does it work without React / a built version? =

Yes. Every admin page has a PHP fallback rendered without JavaScript. Build `npm run build` once to get the full React UI.

= Is it compatible with Multisite? =

Each site in a Multisite network operates independently. The plugin can be network-activated but import jobs and settings are per-site.

= Can I display vacancies without the block editor? =

Yes. Use the shortcode `[appcon_vacancies]` or the Elementor widget. You can also query `appcon_vacancy` posts with standard WordPress template tags.

== Screenshots ==

1. **Dashboard** – Live stats: published vacancies, expired count, upcoming expirations, last import summary, and quick-action links.
2. **Import Jobs** – React-powered job management table with run/edit/delete and bulk-delete actions.
3. **Job Editor – Field Mapper** – Visual mapping interface with a live API sample browser (Stage 2 structure).
4. **Job Editor – Two-Stage Config** – Per-job Stage 1 and Stage 2 configuration with sort, filters, delay, and batch size.
5. **Run Progress Monitor** – Real-time import progress with adaptive polling, index stats, and scrollable log output.
6. **Settings Page** – Five-section settings: API credentials, Import Defaults, Vacancy Expiry, Cache/Performance, and Display.
7. **Vacancy Card Block** – Gutenberg block with configurable field toggles, expiry badge, and Query Loop support.
8. **Elementor Vacancy Listing Widget** – Filterable vacancy list with level/route dropdowns in the Elementor panel.

== Changelog ==

= 1.0.0 =
Initial public release.

* Two-stage import engine with Action Scheduler support.
* Visual Field Mapper with live Stage 2 API sample.
* Gutenberg blocks: Vacancy Listing and Vacancy Card (Query Loop compatible).
* Elementor widgets: Vacancy Listing, Vacancy Card, and Elementor Pro dynamic tags.
* Vacancy expiry manager with daily Action Scheduler cron.
* Frontend search with keyword, level, and route filter query vars.
* Custom `appcon_archived` post status and WP admin expiry filter.
* Media importer for employer logos (sideloads, deduplicates by URL).
* Dependency checker with dismissible admin notices.
* Full Settings API with five sections and bounded sanitisation.
* Classic PHP fallback for all admin pages (no React build required).
* Secure Custom Fields field group registration.
* British English (en_GB) translation included.
* GitHub Actions CI (PHP 8.2/8.3, ESLint, Stylelint) and release pipeline.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade path from the legacy "Apprenticeship Connect" (v2/v3) plugin. Please deactivate the old plugin before activating this one.
