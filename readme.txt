=== Apprenticeship Connect ===
Contributors: jules
Tags: apprenticeship, recruitment, jobs, gov api, vacancies
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 3.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import and manage UK Government Apprenticeship vacancies directly into WordPress.

== Description ==

Apprenticeship Connect is an enterprise-grade WordPress plugin designed for training providers and recruitment agencies. It seamlessly integrates with the UK Government Display Advert API v2 to import rich, deep-fetched vacancy data.

Key Features:
* **Deep-Fetch Engine**: Automatically acquires full vacancy details (wages, courses, address) beyond standard listing data.
* **API Resilience**: Built-in HTTP 429 rate-limit awareness and exponential backoff retries.
* **Modern React Dashboard**: Real-time "Fancy" log viewer and resilience monitoring.
* **Enterprise Scheduling**: Uses Action Scheduler for reliable, non-blocking background imports.
* **Universal Support**: Full compatibility with Gutenberg Blocks and Elementor Dynamic Tags.

== Installation ==

1. Upload the `apprenticeship-connect` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Appr Connect' -> 'Settings' and enter your API Subscription Key.
4. Create an 'Import Task' and click 'Run Now'.

== Changelog ==

= 3.1.0 =
* Complete architectural overhaul.
* Unified Settings Manager.
* New Deep-Fetch Import Engine.
* React-based Dashboard with Fancy Log Viewer.
* Action Scheduler integration.
* Elementor Dynamic Tags support.

= 2.0.0 =
* Initial v2 API support.
