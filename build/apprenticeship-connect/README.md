# Apprenticeship Connect

**Apprenticeship Connect** is a robust WordPress plugin that seamlessly integrates with the official UK Government's Find an Apprenticeship service. Version 3.0 introduces a powerful **Import Task** engine, Action Scheduler support, and full Elementor Loop Grid compatibility.

## Description

Apprenticeship Connect connects to the UK Government's Display Advert API (v2) to pull and manage apprenticeship vacancies directly on your WordPress site. It's designed for high performance, reliability, and customisation.

### Key Features (v3.0)

- **Import Tasks Hub**: Create multiple import tasks with distinct configurations, endpoints, and schedules.
- **API v2 Ready**: Fully supports the latest UK Government API structure with deep-field mappings.
- **Action Scheduler Integration**: Uses Automattic's Action Scheduler for reliable background processing, ensuring your site stays fast during large imports.
- **Live Connection Test Drive**: Verify API credentials and see live data previews before saving your tasks.
- **Advanced Field Mappings**: Map nested API fields to WordPress post fields, meta, and taxonomies using intuitive dot notation.
- **Elementor Integration**: Custom dynamic tags for all vacancy data, fully compatible with Elementor's Loop Grid and Single Templates.
- **Robust Logging**: Comprehensive import logs with success, warning, and error tracking.
- **Simple Shortcode**: Still supports `[apprco_vacancies]` for quick out-of-the-box display.

## Installation

1. Upload the `apprenticeship-connect` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Apprenticeship Connect > Import Tasks** to create your first sync job.
4. (Optional) Configure global display settings in **Apprenticeship Connect > Settings**.

## Architecture (v3.0+)

The plugin has been redesigned for scalability:

- **Centralised Management**: All import logic is consolidated into the "Import Tasks" system.
- **Unified Settings**: Global settings are managed via a single source of truth in the Settings Manager.
- **Background Engine**: Tasks are processed in the background using a robust job queue, preventing timeouts on shared hosting.
- **Custom Post Type**: Vacancies are stored as `apprco_vacancy` posts, making them compatible with any theme, page builder, or search plugin.

## Usage

### Import Tasks

1.  **Add New Task**: Enter your API Base URL and Subscription Key.
2.  **Test Drive**: Use the "Test Connection" button to fetch a sample.
3.  **Field Mapping**: Map the API fields to WordPress (e.g., `title` -> `post_title`).
4.  **Schedule**: Set the frequency (Hourly, Daily, etc.).
5.  **Run**: Click "Run Now" to trigger an immediate sync.

### Shortcode

Use `[apprco_vacancies]` to display the latest vacancies using the default template. Display options like item count and vacancy images are managed in the global settings.

### Elementor

Apprenticeship Connect provides native dynamic tags for Elementor. When designing a Single Vacancy template or a Loop Item, look for "Apprenticeship Connect" tags in the dynamic tags list to display live data like Wage, Closing Date, or Employer Name.

## Frequently Asked Questions

### Does it support API v2?
Yes, version 3.0+ is built specifically for the UK Government API v2.

### What is Action Scheduler?
Action Scheduler is a robust job queue library used by WooCommerce. We use it to handle data imports in the background so they don't slow down your website.

### Can I have multiple sync jobs?
Yes. You can create different tasks for different providers, regions, or sectors.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- API subscription key from UK Government Apprenticeship service

## Credits

Developed by ePark Team.

---

**Get started with Apprenticeship Connect 3.0 today and transform how you manage apprenticeship vacancies!**
