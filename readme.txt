=== Apprenticeship Connect ===
Contributors: dexit
Tags: apprenticeships, jobs, vacancies, uk-gov, recruitment
Requires at least: 6.1
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Robust integration with UK Government Apprenticeships API v2. Deep-fetching, rate-limit resilience, jobs archive, enquiry management, and modern dashboard.

== Description ==

Apprenticeship Connect is a powerful WordPress plugin that imports and manages apprenticeship vacancies from the UK Government Display Advert API v2, storing the full dataset locally for lightning-fast, self-hosted search and display.

**Key Features:**

* **Two-Stage Import Engine** — Stage 1 paginates the full listing (~9,000+ vacancies). Stage 2 deep-fetches `/vacancy/{ref}` for each vacancy's extended details. Both stages run asynchronously via Action Scheduler.
* **Rate-Limit Resilience** — Exponential backoff (up to 5 retries), `Retry-After` header awareness, and proactive rate-limit monitoring keep imports running smoothly.
* **Self-Hosted Dataset** — All vacancy data is stored in custom DB tables (`apprco_vacancies`, `apprco_workplaces`), eliminating dependence on live API responses for frontend search.
* **Full-Featured Jobs Archive** — Haversine distance search, keyword filtering, level/route taxonomy filters, and responsive grid/list layout. Available as a Gutenberg block, Elementor v4 widget, and `[apprco_jobs]` shortcode.
* **Enquiry Management** — Built-in contact/enquiry form (`[apprco_enquiry_form]`) tied to specific vacancies. Submissions are logged to the database, email-notified, and managed from a dedicated admin screen.
* **Geocoding** — Forward geocoding via postcodes.io (with Nominatim fallback) and reverse geocoding for human-readable location display.
* **Training Providers** — Providers and workplace locations are extracted as separate CPTs (`apprco_provider`) with proximity search support.
* **DTO Field Mapper** — Configurable mapping rules translate API response fields to CPT post data, meta, taxonomies, and the vacancy store, with support for sandboxed PHP transform expressions.
* **Modern Admin Dashboard** — React-based dashboard with real-time import progress, log viewer, task management, and field-mapping reference.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Appr Connect → Settings** and enter your UK Gov API subscription key.
4. Go to **Appr Connect → Dashboard** and create an Import Task.
5. Run the task to begin importing vacancies.
6. Add the `[apprco_jobs]` shortcode, Gutenberg block, or Elementor widget to any page.

== Frequently Asked Questions ==

= Where do I get a UK Government Apprenticeships API key? =

Apply at the UK Government Developer Hub: https://developers.apprenticeships.education.gov.uk/

= How long does the full import take? =

Stage 1 (listing all ~9,000 vacancies) typically takes 2–5 minutes. Stage 2 (deep-fetching each vacancy) runs asynchronously via Action Scheduler and can take several hours on a standard hosting environment. You can monitor progress from the dashboard.

= Will the import overload my server? =

No. Stage 2 jobs are queued individually via Action Scheduler with built-in delays between requests and automatic retry/backoff on rate-limit responses.

= Does this work with WP Playground? =

Yes — the plugin can be activated and tested in WP Playground. However, live API imports require a valid UK Gov API key and outbound network access.

= Can I customise the field mappings? =

Yes. Go to **Appr Connect → Field Mappings** to view per-task mapping rules and reset to defaults. Advanced users can edit the JSON mapping array directly via the task editor.

= Is the enquiry form GDPR-compliant? =

The enquiry form records the submitter's name, email, phone, and message along with IP address and user agent for abuse prevention. Ensure your Privacy Policy covers this data collection. All enquiry data can be deleted from the Enquiries admin screen or during plugin uninstall.

== Screenshots ==

1. Admin dashboard with import progress and log viewer.
2. Jobs archive — grid layout with search and filters.
3. Single vacancy page with apply button and enquiry form.
4. Enquiries management screen.
5. Gutenberg block editor with InspectorControls.
6. Elementor widget controls panel.
7. Field Mappings reference page.
8. Import Task editor.

== Changelog ==

= 3.1.0 =
* Full architectural rebuild on flat PHP class structure.
* Two-stage async import pipeline via Action Scheduler.
* Self-hosted vacancy dataset with Haversine search.
* Gutenberg block, Elementor v4.x widget, and shortcode archive.
* DTO field mapper with sandboxed transform expressions.
* OSM/postcodes.io geocoding (forward, bulk, reverse, async Stage 3).
* Training provider CPT with workplace locations.
* Enquiry form system with admin management screen.
* API client with exponential backoff and full retry/rate-limit logging.
* Field Mappings admin page.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 3.1.0 =
Complete rebuild — deactivate and reactivate after upgrading to run the database upgrade routine.
