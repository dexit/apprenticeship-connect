# Architecture Analysis & Refactoring Plan

## FILE RELATIONSHIP MAP

### Import Flow - Current Messy State

```
USER ACTION                 EXECUTION PATH                   API CALL              DATABASE
───────────────────────────────────────────────────────────────────────────────────────────

Settings Page              Apprco_Admin                     wp_remote_get()      wp_posts
"Manual Sync"         →    ajax_manual_sync()          →    (direct)        →   wp_postmeta
                           ↓
                           Apprco_Core
                           fetch_and_save_vacancies()
                           ↓
                           Apprco_API_Importer
                           fetch_all_vacancies()
                           [ISSUE: Direct wp_remote_get, not using API Client]

───────────────────────────────────────────────────────────────────────────────────────────

Settings Page (NEW)        Apprco_REST_Controller           Apprco_API_Client    wp_apprco_import_tasks
"Manual Sync"         →    run_manual_import()         →    [via provider]  →   wp_apprco_import_logs
                           ↓                                                     wp_posts
                           Apprco_Import_Adapter                                 wp_postmeta
                           run_manual_sync()
                           ↓
                           Apprco_Import_Tasks
                           run_import()
                           ↓
                           Apprco_Provider_Registry
                           ↓
                           Apprco_UK_Gov_Provider
                           [ISSUE: Completely different flow for same action]

───────────────────────────────────────────────────────────────────────────────────────────

Import Wizard             Apprco_Import_Wizard             wp_remote_get()      wp_posts
"Execute Import"      →   ajax_execute()              →    (direct via       → wp_postmeta
                          ↓                                 API Importer)       wp_apprco_employers
                          Apprco_API_Importer                                   wp_apprco_import_logs
                          fetch_all_vacancies()
                          ↓
                          Apprco_Core
                          process_single_vacancy()
                          [ISSUE: Uses old API Importer, not Import Tasks]

───────────────────────────────────────────────────────────────────────────────────────────

WP-Cron Daily             Apprco_Scheduler                 wp_remote_get()      wp_posts
(Action Scheduler)    →   handle_scheduled_sync()     →    (via Core)      →   wp_postmeta
                          ↓
                          Apprco_Core
                          fetch_and_save_vacancies()
                          [ISSUE: Uses old Core logic, not Import Tasks]

───────────────────────────────────────────────────────────────────────────────────────────

Import Tasks              Apprco_Task_Scheduler            Apprco_API_Client    wp_apprco_import_tasks
Scheduled Task       →    execute_scheduled_task()    →    [via provider]  →   wp_apprco_import_logs
                          ↓                                                     wp_posts
                          Apprco_Import_Tasks                                   wp_postmeta
                          run_import()
                          ↓
                          Apprco_UK_Gov_Provider
                          [ISSUE: Separate scheduler for tasks vs main sync]
```

### Settings Access - Conflicting Patterns

```
COMPONENT                   SETTINGS ACCESS METHOD              ISSUES
─────────────────────────────────────────────────────────────────────────

Apprco_Admin               get_option('apprco_plugin_options')  OLD - 15+ uses
Apprco_Setup_Wizard        get_option('apprco_plugin_options')  OLD - direct save
Apprco_Core                Apprco_Settings_Manager              NEW - migrated

Apprco_Import_Adapter      Apprco_Settings_Manager              NEW
Apprco_REST_Controller     Apprco_Settings_Manager              NEW
Apprco_Scheduler           Apprco_Settings_Manager              NEW

[PROBLEM: Two settings systems exist simultaneously]
[SOLUTION: Eliminate all get_option('apprco_plugin_options') references]
```

### Database Tables - Who Writes What

```
TABLE                        WRITTEN BY                          READ BY
─────────────────────────────────────────────────────────────────────────────

wp_posts                     Apprco_Core                         All (WP Query)
(apprco_vacancy CPT)         process_single_vacancy()

wp_postmeta                  Apprco_Core                         Apprco_Shortcodes
                             save_vacancy_meta()                 Apprco_REST_API
                             Apprco_Meta_Box                     Apprco_Meta_Box
                             save_meta()

wp_apprco_import_tasks       Apprco_Import_Tasks                 Apprco_Import_Tasks
                             create(), update()                  Apprco_Task_Scheduler
                                                                 Apprco_Import_Adapter

wp_apprco_import_logs        Apprco_Import_Logger                Apprco_Admin
                             log(), start_import()               Apprco_REST_Controller
                             end_import()                        Apprco_Import_Logger

wp_apprco_employers          Apprco_Employer                     Apprco_Import_Wizard
                             get_or_create()                     Apprco_Employer

wp_options                   Apprco_Settings_Manager             Apprco_Settings_Manager
('apprco_settings')          set()                              get()

wp_options                   Apprco_Admin (OLD)                  Apprco_Admin (OLD)
('apprco_plugin_options')    Apprco_Setup_Wizard (OLD)          Apprco_Core (migrated)

[PROBLEM: Two settings keys in wp_options]
[SOLUTION: Deprecate apprco_plugin_options completely]
```

---

## IDENTIFIED CONFLICTS & PROBLEMS

### 1. DUAL IMPORT SYSTEMS ❌

**Problem:** Two completely separate import execution paths
- **OLD**: Admin → Core → API Importer → wp_remote_get()
- **NEW**: Admin → REST Controller → Import Adapter → Import Tasks → Provider

**Impact:**
- Confusing which path is used when
- Different behavior depending on entry point
- Maintenance nightmare

**Solution:**
- Deprecate old path completely
- ALL imports go through Import Adapter → Import Tasks → Provider
- Remove Apprco_API_Importer completely

---

### 2. DUAL SETTINGS SYSTEMS ❌

**Problem:** Two settings storage locations
- **OLD**: `apprco_plugin_options` (flat array)
- **NEW**: `apprco_settings` (categorized JSON)

**Currently Using OLD:**
- `class-apprco-admin.php` (15+ places)
- `class-apprco-setup-wizard.php` (5 places)

**Impact:**
- Settings can get out of sync
- Unclear which is source of truth
- Migration only goes one direction (old → new)

**Solution:**
- Update all OLD uses to use Settings Manager
- Remove apprco_plugin_options writes
- Keep migration for backward compatibility

---

### 3. DUPLICATE API CALLING ❌

**Problem:** Two HTTP client implementations
- **Apprco_API_Importer** uses `wp_remote_get()` directly
- **Apprco_API_Client** is proper abstraction with retry/cache

**Impact:**
- API Importer duplicates functionality
- No retry logic in old path
- No rate limiting in old path

**Solution:**
- Make API Importer use API Client internally
- Or deprecate API Importer completely

---

### 4. DUAL SCHEDULERS ❌

**Problem:** Two scheduling systems
- **Apprco_Scheduler**: For main daily sync (uses Action Scheduler/WP-Cron)
- **Apprco_Task_Scheduler**: For import tasks (uses WP-Cron)

**Impact:**
- Confusing which scheduler handles what
- Both can schedule same action

**Solution:**
- Consolidate to single scheduler
- Use Task Scheduler for everything
- Remove Apprco_Scheduler

---

### 5. DEAD CODE ❌

**Files That Should Be Removed:**
- `class-apprco-db-upgrade.php` - Redundant (Database class handles it)
- Large portions of `class-apprco-admin.php` - Old PHP settings forms never shown

**Files That Need Refactoring:**
- `class-apprco-core.php` - Should delegate to Import Tasks
- `class-apprco-api-importer.php` - Should use API Client or be removed
- `class-apprco-import-wizard.php` - Should use Import Tasks

---

### 6. ADMIN BLOAT ❌

**Problem:** `class-apprco-admin.php` is 96KB with mixed concerns
- Renders 5+ admin pages
- Handles 15+ AJAX endpoints
- Manages settings forms
- Handles logs
- Handles import tasks

**Solution:** Split into separate controllers:
- `class-apprco-admin-settings.php`
- `class-apprco-admin-logs.php`
- `class-apprco-admin-tasks.php`
- `class-apprco-admin-dashboard.php`

---

## CLEAN ARCHITECTURE PROPOSAL

### Proposed Import Flow (Single Path)

```
ALL USER ACTIONS → WP REST API → Import Adapter → Import Tasks → Provider → API Client → Gov.uk API
                                                                                        ↓
                                         Database ← Vacancy Processor ← Normalized Data
```

### Proposed File Structure

```
includes/
├── core/
│   ├── class-apprco-core.php              [Simplified - plugin initialization only]
│   ├── class-apprco-database.php          [Keep - table management]
│   └── class-apprco-settings-manager.php  [Keep - single source of truth]
│
├── import/
│   ├── class-apprco-import-adapter.php    [Keep - unified interface]
│   ├── class-apprco-import-tasks.php      [Keep - task engine]
│   ├── class-apprco-import-logger.php     [Keep - logging]
│   └── class-apprco-vacancy-processor.php [NEW - extract from Core]
│
├── providers/
│   ├── interface-apprco-provider.php      [Keep]
│   ├── abstract-apprco-provider.php       [Keep]
│   ├── class-apprco-provider-registry.php [Keep]
│   └── class-apprco-uk-gov-provider.php   [Keep]
│
├── api/
│   ├── class-apprco-api-client.php        [Keep - HTTP client]
│   └── class-apprco-api-proxy.php         [NEW - CORS proxy for frontend]
│
├── admin/
│   ├── class-apprco-admin.php             [Refactor - just menu registration]
│   ├── class-apprco-admin-dashboard.php   [NEW - dashboard page]
│   ├── class-apprco-admin-settings.php    [NEW - settings page]
│   ├── class-apprco-admin-tasks.php       [NEW - import tasks page]
│   ├── class-apprco-admin-logs.php        [NEW - logs page]
│   └── class-apprco-setup-wizard.php      [Refactor - use Settings Manager]
│
├── rest/
│   ├── class-apprco-rest-settings.php     [NEW - settings endpoints]
│   ├── class-apprco-rest-import.php       [NEW - import endpoints]
│   ├── class-apprco-rest-vacancies.php    [NEW - vacancy CRUD]
│   ├── class-apprco-rest-proxy.php        [NEW - CORS proxy]
│   └── class-apprco-rest-geocoding.php    [NEW - OSM geocoding]
│
├── services/
│   ├── class-apprco-geocoder.php          [Keep - OSM integration]
│   ├── class-apprco-employer.php          [Keep - employer management]
│   └── class-apprco-task-scheduler.php    [Keep - single scheduler]
│
├── frontend/
│   ├── class-apprco-shortcodes.php        [Keep]
│   ├── class-apprco-meta-box.php          [Keep]
│   └── class-apprco-elementor.php         [Keep - optional]
│
└── deprecated/
    ├── class-apprco-api-importer.php      [DEPRECATED - use providers]
    ├── class-apprco-scheduler.php         [DEPRECATED - use task scheduler]
    ├── class-apprco-rest-api.php          [DEPRECATED - use new REST classes]
    └── class-apprco-db-upgrade.php        [DEPRECATED - use database class]
```

---

## USER'S REQUIREMENTS IMPLEMENTATION

Based on your specifications, here's what needs to be implemented:

### 1. ✅ Keep Existing Pages (They're Good!)
- **Add Vacancy** - `Apprco_Admin` + `Apprco_Meta_Box` [EXISTS]
- **Vacancies List** - WordPress CPT list [EXISTS]
- **Import Logs** - `Apprco_Admin::logs_page()` [EXISTS]
- **Import List/Status** - `Apprco_Admin::import_tasks_page()` [EXISTS]
- **Add Import** (Amazing!) - `Apprco_Import_Tasks` [EXISTS - KEEP AS IS]

### 2. ❌ Setup Wizard Using Import Presets (NEEDS FIX)
**Current Problem:**
- Setup Wizard has its own field definitions
- Import Tasks has separate field definitions
- No sharing of configuration

**Solution:**
- Extract default provider config from UK Gov Provider
- Setup Wizard uses those defaults
- No duplication

### 3. ❌ CORS Proxy for Display Advert API v2 (MISSING)
**What's Needed:**
- WP REST endpoint: `/wp-json/apprco/v1/proxy/vacancies`
- Accepts query params: `Lat`, `Lon`, `Sort`, `DistanceInMiles`, `PageSize`, `PageNumber`, `PostedInLastNumberOfDays`
- Proxies to: `https://api.apprenticeships.education.gov.uk/vacancies/vacancy`
- Adds headers: `X-Version: 2`, `Ocp-Apim-Subscription-Key`
- Returns JSON with CORS headers

**Implementation:**
```php
// New file: includes/rest/class-apprco-rest-proxy.php
class Apprco_REST_Proxy {
    public function proxy_vacancies( $request ) {
        // Get API credentials from Settings Manager
        // Forward all query params
        // Add required headers
        // Return response with CORS
    }
}
```

### 4. ✅ OSM Geocoding (EXISTS)
**Already Implemented:** `class-apprco-geocoder.php`
- Forward geocode: Postcode/City → Lat/Long
- Reverse geocode: Lat/Long → Address
- Rate limited (1 req/sec)
- 7-day cache

**What's Missing:**
- Frontend interface for user input
- REST endpoint for frontend to call

**Solution:**
```php
// New file: includes/rest/class-apprco-rest-geocoding.php
class Apprco_REST_Geocoding {
    public function geocode( $request ) {
        $geocoder = Apprco_Geocoder::get_instance();
        $location = $request->get_param( 'location' );
        return $geocoder->forward_geocode( $location );
    }
}
```

### 5. ❌ Reference Data Endpoints (MISSING)
**What's Needed:**
- `/wp-json/apprco/v1/proxy/courses` → Proxies to `/vacancies/referencedata/courses`
- `/wp-json/apprco/v1/proxy/routes` → Proxies to `/vacancies/referencedata/courses/routes`
- `/wp-json/apprco/v1/proxy/vacancy/{ref}` → Proxies to `/vacancies/vacancy/{vacancyReference}`

**Implementation:** Extend `Apprco_REST_Proxy` with additional methods

---

## REFACTORING PRIORITY

### Phase 1: Critical Fixes (Do First)
1. **Consolidate Settings Access**
   - Update `class-apprco-admin.php` to use Settings Manager
   - Update `class-apprco-setup-wizard.php` to use Settings Manager
   - Remove all `get_option('apprco_plugin_options')` writes

2. **Add CORS Proxy**
   - Create `class-apprco-rest-proxy.php`
   - Register routes for all proxy endpoints
   - Test with Display Advert API v2

3. **Add Geocoding REST Endpoint**
   - Create `class-apprco-rest-geocoding.php`
   - Expose OSM geocoding to frontend

### Phase 2: Architecture Cleanup (Do Second)
4. **Consolidate Import Flow**
   - Make ALL imports use Import Adapter
   - Update wizard to use Import Tasks
   - Update scheduler to use Import Tasks
   - Deprecate `Apprco_API_Importer`

5. **Consolidate Schedulers**
   - Remove `Apprco_Scheduler`
   - Use only `Apprco_Task_Scheduler`

6. **Remove Dead Code**
   - Remove `class-apprco-db-upgrade.php`
   - Clean up unused admin code

### Phase 3: Polish (Do Last)
7. **Split Admin Class**
   - Extract separate controller classes
   - Convert AJAX to REST

8. **Documentation**
   - Update all docs to reflect new architecture
   - Add API documentation

---

## NEXT STEPS

Would you like me to:
1. **Implement CORS proxy and geocoding REST endpoints** (Phase 1 - Critical)
2. **Fix settings consolidation** (Phase 1 - Critical)
3. **Clean up import flow** (Phase 2 - Architecture)
4. **All of the above in sequence**

Let me know and I'll proceed with clean, well-tested code.
