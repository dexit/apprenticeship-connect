=== Apprenticeship Connect ===
Contributors: epark
Donate Link: https://buymeacoffee.com/epark
Tags: apprenticeships, vacancies, jobs, api, uk, elementor
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Centralised Import Tasks engine for UK Government Apprenticeships. Support for API v2, Action Scheduler, and Elementor.

== Description ==
Apprenticeship Connect is a professional-grade WordPress plugin for training providers and educational institutions. Version 3.0 introduces a centralized "Import Tasks" system that allows you to manage multiple API connections with high reliability.

= Key Features: =
- **Import Tasks Engine:** Manage multiple sync jobs with distinct filters and schedules.
- **Action Scheduler:** Reliable background syncing used by the world's largest WordPress sites.
- **API v2 Ready:** Built specifically for the latest UK Government Display Advert API.
- **Connection Test Drive:** Live API testing and data previews directly in the admin.
- **Elementor Support:** Dynamic tags for all vacancy data, compatible with Loop Grids.
- **Flexible Field Mapping:** Map any API field to WordPress using dot notation.

== Installation ==
1. Upload the `apprenticeship-connect` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Apprenticeship Connect > Import Tasks** to configure your first sync.

== Frequently Asked Questions ==
= Does it support API v2? =
Yes, version 3.0 is built for API v2.

= Is it compatible with Elementor? =
Yes. It provides native Dynamic Tags for Elementor Loop Grids and Single templates.

== Screenshots ==
1. The new Import Tasks dashboard showing task status and schedules.
2. The Task Editor with Connection Test Drive and Live Preview.
3. Field Mapping interface for API v2 data.
4. Elementor Dynamic Tags integration.

== Changelog ==
= 3.0.0 =
- Major Refactor: Centralized all logic around a modern "Import Tasks" engine.
- Feature: Added Action Scheduler support for robust background imports.
- Feature: Added "Connection Test Drive" for live API debugging.
- Feature: Full support for UK Gov API v2 nested data structures.
- Feature: Advanced Field Mappings with dot notation support.
- Feature: Native Elementor Dynamic Tags for all vacancy meta fields.
- Cleanup: Removed legacy Setup Wizard and merged all settings into a unified Manager.
- Optimization: Significant performance improvements to the import engine and REST API.

= 1.2.0 =
- Feature: Added admin setting to display a "No Vacancy" image when no vacancies are available.

== Upgrade Notice ==
= 3.0.0 =
Major architectural update. Your previous settings will be migrated automatically. Please visit the new Import Tasks page to verify your sync schedules.
