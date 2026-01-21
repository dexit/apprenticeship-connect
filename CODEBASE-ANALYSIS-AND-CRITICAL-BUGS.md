# CODEBASE ANALYSIS & CRITICAL BUGS

**Date:** 2026-01-21
**Status:** CRITICAL ISSUES IDENTIFIED
**Analysis Type:** Complete Codebase Structure & API Settings Flow

---

## TABLE OF CONTENTS

1. [Executive Summary](#executive-summary)
2. [Logical File Usage Tree](#logical-file-usage-tree)
3. [API Settings Fragmentation Analysis](#api-settings-fragmentation-analysis)
4. [CRITICAL BUG: Settings Not Used](#critical-bug-settings-not-used)
5. [Evidence & Code Examples](#evidence--code-examples)
6. [Impact Assessment](#impact-assessment)
7. [Recommendations](#recommendations)

---

## EXECUTIVE SUMMARY

### Key Findings

**CRITICAL BUG IDENTIFIED:** The React Settings UI saves API credentials to `apprco_settings`, but the Import system reads from `apprco_plugin_options`. **This means user-entered API credentials are NEVER used during import operations.**

**Settings Fragmentation:**
- **52+ locations** accessing `apprco_plugin_options` (old system)
- **2 locations** using `Apprco_Settings_Manager` (new system)
- **NO synchronization** between the two systems
- **Multiple hardcoded defaults** scattered throughout codebase

**Architecture Pattern:**
The codebase exhibits a "random solutions stitched together" pattern where:
- New React UI (Settings.jsx) was built on top of a new Settings Manager
- Old PHP backend (Import Adapter, Core, Scheduler) still uses legacy options
- Migration code exists but doesn't keep systems in sync
- Result: Broken data flow between frontend and backend

---

## LOGICAL FILE USAGE TREE

### 1. PLUGIN BOOTSTRAP & INITIALIZATION

```
apprenticeship-connect.php (Main Plugin File)
├── Initializes all classes via singleton pattern
├── Registers activation/deactivation hooks
├── Sets up admin menu structure
└── Loads dependencies in order:
    ├── class-apprco-database.php         [Database schema & tables]
    ├── class-apprco-settings-manager.php [NEW unified settings system]
    ├── class-apprco-core.php             [Core import/sync logic]
    ├── class-apprco-admin.php            [Legacy admin UI]
    ├── class-apprco-rest-api.php         [REST API routes for vacancies]
    ├── class-apprco-rest-controller.php  [Dashboard/Settings REST endpoints]
    └── ... (23 more classes)
```

### 2. SETTINGS SYSTEM (DUAL SYSTEMS - NOT SYNCHRONIZED!)

```
SETTINGS LAYER (BROKEN ARCHITECTURE):

┌─────────────────────────────────────────────────────────────┐
│ FRONTEND: React Settings UI                                 │
│                                                              │
│  src/admin/pages/Settings.jsx                               │
│  ├── Uses: @wordpress/api-fetch                            │
│  ├── GET:  /apprco/v1/settings                             │
│  └── POST: /apprco/v1/settings                             │
│                                                              │
│  Components:                                                │
│  ├── APISettings.jsx      (subscription_key, base_url)     │
│  ├── ImportSettings.jsx   (batch_size, rate_limit)         │
│  ├── ScheduleSettings.jsx (frequency, time)                │
│  ├── DisplaySettings.jsx  (items_per_page, show_*)         │
│  └── AdvancedSettings.jsx (geocoding, logging)             │
│                                                              │
│  SAVES TO: ✓ 'apprco_settings' (wp_options)                │
└─────────────────────────────────────────────────────────────┘
                            ↓
                    [NO SYNC MECHANISM]
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ BACKEND: PHP Import System                                  │
│                                                              │
│  includes/class-apprco-import-adapter.php                   │
│  ├── run_manual_sync() - Line 78                           │
│  └── get_option('apprco_plugin_options') ← WRONG!          │
│                                                              │
│  includes/class-apprco-core.php                             │
│  ├── __construct() - Line 159                              │
│  └── get_option('apprco_plugin_options') ← WRONG!          │
│                                                              │
│  includes/class-apprco-scheduler.php                        │
│  ├── schedule_sync() - Line 127                            │
│  └── get_option('apprco_plugin_options') ← WRONG!          │
│                                                              │
│  includes/class-apprco-api-importer.php                     │
│  ├── __construct() - Line 72                               │
│  └── get_option('apprco_plugin_options') ← WRONG!          │
│                                                              │
│  READS FROM: ✗ 'apprco_plugin_options' (EMPTY/OLD)         │
└─────────────────────────────────────────────────────────────┘
```

### 3. API REQUEST FLOW (THE BROKEN PATH)

```
USER INTERACTION FLOW:

1. USER ENTERS CREDENTIALS
   ↓
   Settings.jsx (React Component)
   └── Input: subscription_key = "abc123..."
   └── Input: base_url = "https://api.apprenticeships.education.gov.uk/vacancies"

2. USER CLICKS "SAVE"
   ↓
   POST /apprco/v1/settings
   ↓
   class-apprco-rest-api.php::update_settings()
   ↓
   Apprco_Settings_Manager::save()
   ↓
   update_option('apprco_settings', [...]) ✓ SAVED HERE

3. USER CLICKS "MANUAL SYNC" BUTTON
   ↓
   Dashboard.jsx → POST /apprco/v1/import/manual
   ↓
   Apprco_REST_Controller::run_manual_import() [Line 120]
   ↓
   Apprco_Import_Adapter::run_manual_sync() [Line 78]
   ↓
   get_option('apprco_plugin_options') ← READS FROM WRONG PLACE!
   ↓
   Returns: [] (empty array) or OLD DATA

4. VALIDATION CHECK
   ↓
   if ( empty( $options['api_subscription_key'] ) ) {
       return ['success' => false, 'error' => 'API credentials not configured.'];
   }
   ↓
   🔴 IMPORT FAILS - Credentials "not configured" even though user just entered them!
```

### 4. FILE DEPENDENCY TREE

```
CORE DATA FLOW:

apprenticeship-connect.php (Bootstrap)
    ↓
    ├── Settings Layer (DUAL SYSTEMS)
    │   ├── class-apprco-settings-manager.php [NEW - apprco_settings]
    │   │   └── Used by: REST API endpoints only
    │   │
    │   └── Legacy get_option('apprco_plugin_options') [OLD]
    │       └── Used by: Everything else (52+ locations)
    │
    ├── Import System
    │   ├── class-apprco-import-adapter.php
    │   │   ├── run_manual_sync()      → Uses OLD settings ✗
    │   │   └── run_wizard_import()    → Uses provider config
    │   │
    │   ├── class-apprco-import-tasks.php
    │   │   └── Task-based import engine
    │   │
    │   ├── class-apprco-api-importer.php
    │   │   └── __construct()          → Uses OLD settings ✗
    │   │
    │   └── class-apprco-core.php
    │       └── __construct()          → Uses OLD settings ✗
    │
    ├── API Client Layer
    │   ├── class-apprco-api-client.php
    │   │   ├── HTTP requests with rate limiting
    │   │   ├── Retry logic & error handling
    │   │   └── Cache management
    │   │
    │   └── providers/class-apprco-uk-gov-provider.php
    │       └── UK Gov API implementation
    │
    ├── REST API Layer
    │   ├── class-apprco-rest-api.php
    │   │   └── Vacancy CRUD endpoints (/apprco/v1/vacancy/*)
    │   │
    │   ├── class-apprco-rest-controller.php
    │   │   ├── /stats              → Uses NEW settings ✓
    │   │   ├── /api/test           → Uses NEW settings ✓
    │   │   └── /import/manual      → Calls adapter (uses OLD) ✗
    │   │
    │   └── Settings REST Endpoints (in rest-api.php)
    │       ├── GET  /settings      → Uses NEW settings ✓
    │       └── POST /settings      → Uses NEW settings ✓
    │
    └── Frontend React Layer
        └── src/admin/
            ├── pages/
            │   ├── Dashboard.jsx    → Calls /import/manual
            │   └── Settings.jsx     → Calls /settings (POST)
            │
            ├── components/
            │   ├── APIStatus.jsx    → Calls /api/test ✓ Works!
            │   └── settings/*       → All NEW system ✓ Works!
            │
            └── utils/api.js         → REST API helpers
```

### 5. PROVIDER SYSTEM ARCHITECTURE

```
Provider Registry Pattern:

interface-apprco-provider.php (Contract)
    ↓
abstract-apprco-provider.php (Base Implementation)
    ↓
class-apprco-uk-gov-provider.php (UK Gov Implementation)
    ├── BASE_URL: 'https://api.apprenticeships.education.gov.uk/vacancies'
    ├── get_config()
    │   ├── subscription_key (required)
    │   ├── base_url (optional)
    │   ├── ukprn (optional)
    │   └── page_size (default: 100)
    │
    ├── get_headers()
    │   ├── Ocp-Apim-Subscription-Key: {subscription_key}
    │   └── X-Version: 2
    │
    └── get_field_mapping()
        └── Maps API fields to WordPress meta fields (130+ mappings)

class-apprco-provider-registry.php (Registry)
    └── register() / get() / get_all()
```

---

## API SETTINGS FRAGMENTATION ANALYSIS

### Settings Storage Locations Count

#### 1. OLD SYSTEM: `apprco_plugin_options`

**Total References: 52+ locations**

##### Write Operations (11 locations):
```php
// apprenticeship-connect.php
Line 538: update_option('apprco_plugin_options', $merged_options);
Line 541: update_option('apprco_plugin_options', $merged_options);

// includes/class-apprco-setup-wizard.php
Line 450: update_option('apprco_plugin_options', $options);
Line 467: update_option('apprco_plugin_options', $options);

// includes/class-apprco-admin.php
Line 816: update_option('apprco_plugin_options', $options);
Line 900: update_option('apprco_plugin_options', $options);
```

##### Read Operations (41+ locations):
```php
// apprenticeship-connect.php
Line 202: get_option('apprco_plugin_options', array());
Line 538: get_option('apprco_plugin_options', array());
Line 860: get_option('apprco_plugin_options', array());

// includes/class-apprco-import-adapter.php
Line 79:  get_option('apprco_plugin_options', array()); ← CRITICAL!

// includes/class-apprco-core.php
Line 159: get_option('apprco_plugin_options', array()); ← CRITICAL!

// includes/class-apprco-scheduler.php
Line 127: get_option('apprco_plugin_options', array()); ← CRITICAL!
Line 410: get_option('apprco_plugin_options', array());

// includes/class-apprco-api-importer.php
Line 72:  get_option('apprco_plugin_options', array()); ← CRITICAL!

// includes/class-apprco-admin.php (Legacy UI)
Lines: 428, 435, 442, 469, 488, 495, 502, 508, 515, 522, 529, 536, 543
       701, 762, 811, 898 [17 locations in admin alone!]

// includes/class-apprco-setup-wizard.php
Lines: 91, 179, 246, 433, 459 [5 locations]

// includes/class-apprco-import-logger.php
Line 102: get_option('apprco_plugin_options', array());

// verify.php
Line 173: get_option('apprco_plugin_options', array());
```

#### 2. NEW SYSTEM: `apprco_settings`

**Total References: Only 2 locations (Settings Manager only!)**

```php
// includes/class-apprco-settings-manager.php
Line 26:  public const OPTION_NAME = 'apprco_settings';
Line 379: get_option('apprco_plugin_options', array()); // For migration only
```

**Used By:**
- `class-apprco-rest-api.php` (via Settings_Manager::get_instance())
- `class-apprco-rest-controller.php` (via Settings_Manager::get_instance())
- **NOWHERE ELSE!**

### Settings Access Patterns by Component

| Component | Settings Source | Status |
|-----------|----------------|--------|
| **React Settings UI** | apprco_settings (via REST API) | ✓ Correct |
| **REST Controller** | apprco_settings (via Settings Manager) | ✓ Correct |
| **Import Adapter** | apprco_plugin_options (direct) | ✗ **WRONG** |
| **Core Import** | apprco_plugin_options (direct) | ✗ **WRONG** |
| **Scheduler** | apprco_plugin_options (direct) | ✗ **WRONG** |
| **API Importer** | apprco_plugin_options (direct) | ✗ **WRONG** |
| **Admin UI (Legacy)** | apprco_plugin_options (direct) | ⚠️ Legacy |
| **Setup Wizard** | apprco_plugin_options (direct) | ⚠️ Legacy |
| **Elementor** | apprco_plugin_options (direct) | ⚠️ Display |

### Hardcoded Default URLs (Multiple Definitions)

```php
// includes/providers/class-apprco-uk-gov-provider.php:36
public const BASE_URL = 'https://api.apprenticeships.education.gov.uk/vacancies';

// apprenticeship-connect.php:522
'api_base_url' => 'https://api.apprenticeships.education.gov.uk/vacancies',

// includes/class-apprco-settings-manager.php:89
'base_url' => 'https://api.apprenticeships.education.gov.uk/vacancies',

// includes/class-apprco-admin.php:429-430
$value = $options['api_base_url'] ?? 'https://api.apprenticeships.education.gov.uk/vacancies';

// includes/class-apprco-setup-wizard.php:200
value="<?php echo esc_attr( isset( $options['api_base_url'] ) ? $options['api_base_url'] : 'https://api.apprenticeships.education.gov.uk/vacancies' ); ?>"

// includes/class-apprco-import-tasks.php:211
'api_base_url' => 'https://api.apprenticeships.education.gov.uk/vacancies',

// includes/class-apprco-admin.php:1079
'api_base_url' => 'https://api.apprenticeships.education.gov.uk/vacancies',
```

**Count: 7 different locations define the same hardcoded default!**

---

## CRITICAL BUG: SETTINGS NOT USED

### Bug Description

**Title:** User-entered API credentials are never used for import operations

**Severity:** CRITICAL - Plugin functionality completely broken

**Affects:** All import operations (manual sync, scheduled imports, wizard imports)

### Root Cause

The codebase has two separate settings systems that DO NOT communicate:

1. **NEW System (React UI):**
   - Saves to: `apprco_settings` (wp_options)
   - Used by: REST API endpoints only
   - File: `class-apprco-settings-manager.php`

2. **OLD System (PHP Backend):**
   - Reads from: `apprco_plugin_options` (wp_options)
   - Used by: All import/sync operations
   - Files: Import Adapter, Core, Scheduler, API Importer

**The Problem:** When a user enters API credentials in the React Settings UI, they are saved to `apprco_settings`. However, when the user clicks "Manual Sync", the Import Adapter reads from `apprco_plugin_options`, which is empty or contains old data.

### Code Evidence

#### WHERE SETTINGS ARE SAVED (React UI)

**File:** `src/admin/pages/Settings.jsx`
```javascript
// Line ~180
const handleSave = async () => {
    const response = await apiFetch({
        path: '/apprco/v1/settings',
        method: 'POST',
        data: settings,  // Contains user-entered credentials
    });
};
```

**File:** `includes/class-apprco-rest-api.php` (Settings endpoint handler)
```php
// Line ~900
$settings_manager = Apprco_Settings_Manager::get_instance();
$result = $settings_manager->save($request->get_json_params());
```

**File:** `includes/class-apprco-settings-manager.php`
```php
// Line ~170
public function save(array $settings): array {
    // Validates and saves to 'apprco_settings'
    $updated = update_option(self::OPTION_NAME, $settings);
    // self::OPTION_NAME = 'apprco_settings'
}
```

#### WHERE SETTINGS ARE READ (Import System)

**File:** `includes/class-apprco-import-adapter.php`
```php
// Line 78-89 (run_manual_sync method)
public function run_manual_sync( array $override_options = array() ): array {
    // ❌ WRONG! Reading from old settings
    $options = get_option( 'apprco_plugin_options', array() );

    // Merge with overrides
    $options = array_merge( $options, $override_options );

    // ❌ This validation ALWAYS fails for users who entered credentials in React UI
    if ( empty( $options['api_subscription_key'] ) || empty( $options['api_base_url'] ) ) {
        return array(
            'success' => false,
            'error'   => 'API credentials not configured.',
        );
    }

    // Create task with settings from WRONG source
    $task_data = array(
        'api_base_url'   => $options['api_base_url'],    // ❌ Empty or old
        'api_auth_value' => $options['api_subscription_key'], // ❌ Empty or old
        // ...
    );
}
```

**File:** `includes/class-apprco-core.php`
```php
// Line 159 (__construct method)
public function __construct( $options = array() ) {
    // ❌ WRONG! Reading from old settings
    $this->options  = get_option( 'apprco_plugin_options', array() );
}
```

**File:** `includes/class-apprco-scheduler.php`
```php
// Line 127 (schedule_sync method)
public function schedule_sync(): void {
    // ❌ WRONG! Reading from old settings
    $options = get_option( 'apprco_plugin_options', array() );
}
```

### Request Flow Diagram

```
USER ENTERS CREDENTIALS IN SETTINGS UI:
┌─────────────────────────────────────────┐
│ Settings.jsx (React)                    │
│ ┌─────────────────────────────────────┐ │
│ │ Subscription Key: abc123...         │ │
│ │ Base URL: https://api.app...        │ │
│ └─────────────────────────────────────┘ │
│ [Save Button Clicked]                   │
└──────────────┬──────────────────────────┘
               ↓
    POST /apprco/v1/settings
               ↓
┌──────────────────────────────────────────┐
│ Apprco_REST_API::update_settings()       │
└──────────────┬───────────────────────────┘
               ↓
┌──────────────────────────────────────────┐
│ Settings_Manager::save()                 │
│                                          │
│ update_option('apprco_settings', [       │
│   'api' => [                            │
│     'subscription_key' => 'abc123...'   │ ✓ Saved!
│     'base_url' => 'https://...'         │
│   ]                                     │
│ ])                                      │
└──────────────────────────────────────────┘

USER CLICKS "MANUAL SYNC" BUTTON:
┌─────────────────────────────────────────┐
│ Dashboard.jsx (React)                   │
│ [Sync Now Button Clicked]               │
└──────────────┬──────────────────────────┘
               ↓
    POST /apprco/v1/import/manual
               ↓
┌──────────────────────────────────────────┐
│ REST_Controller::run_manual_import()     │
└──────────────┬───────────────────────────┘
               ↓
┌──────────────────────────────────────────┐
│ Import_Adapter::run_manual_sync()        │
│                                          │
│ $options = get_option(                  │
│   'apprco_plugin_options',  ← ❌ WRONG! │
│   array()                               │
│ );                                      │
│                                          │
│ Result: []  (empty)                     │ ✗ Not found!
│                                          │
│ if (empty($options['api_subscription_key'])) { │
│   return [                              │
│     'success' => false,                 │
│     'error' => 'API credentials         │
│                 not configured.'        │
│   ];                                    │
│ }                                       │
└──────────────────────────────────────────┘
               ↓
        🔴 IMPORT FAILS!
   "API credentials not configured"

   Even though user just entered them!
```

### Why Migration Doesn't Help

**File:** `includes/class-apprco-settings-manager.php` (Line 372-419)

```php
public function maybe_migrate(): void {
    // Check if already migrated
    if ( get_option( self::OPTION_NAME . '_migrated' ) ) {
        return; // ← ONE-TIME ONLY!
    }

    // Get old settings
    $old_options = get_option( 'apprco_plugin_options', array() );

    // Map to new structure
    $new_settings['api']['base_url'] = $old_options['api_base_url'];
    $new_settings['api']['subscription_key'] = $old_options['api_subscription_key'];

    // Save to new system
    $this->save($new_settings);

    // Mark as migrated (NEVER RUNS AGAIN)
    update_option( self::OPTION_NAME . '_migrated', true );
}
```

**Problems with Migration:**
1. ✗ Only runs ONCE at plugin activation
2. ✗ Migrates from OLD → NEW (one direction only)
3. ✗ Does NOT sync NEW → OLD (reverse direction)
4. ✗ Does NOT keep systems in sync after migration
5. ✗ User enters new credentials in React UI → Old system never gets updated

### Expected vs Actual Behavior

#### Expected Behavior:
1. User enters API credentials in Settings UI
2. Credentials are saved to database
3. User clicks "Manual Sync"
4. Import system reads credentials from database
5. API request is made with user-entered credentials
6. Vacancies are imported successfully

#### Actual Behavior:
1. User enters API credentials in Settings UI ✓
2. Credentials are saved to `apprco_settings` ✓
3. User clicks "Manual Sync" ✓
4. Import system reads from `apprco_plugin_options` (empty!) ✗
5. Validation fails: "API credentials not configured" ✗
6. Import never happens ✗

---

## EVIDENCE & CODE EXAMPLES

### Test Case 1: Fresh Installation

**Scenario:** User installs plugin and configures via React Settings UI

```bash
# Initial state
wp option get apprco_settings
# Result: false (doesn't exist)

wp option get apprco_plugin_options
# Result: false (doesn't exist)
```

**User Action:** Enter credentials in Settings.jsx and save

```bash
# After saving via React UI
wp option get apprco_settings
# Result:
# {
#   "api": {
#     "subscription_key": "abc123...",
#     "base_url": "https://api.apprenticeships.education.gov.uk/vacancies"
#   }
# }

wp option get apprco_plugin_options
# Result: false (still empty!) ← ❌ PROBLEM!
```

**User Action:** Click "Manual Sync" button

```php
// Import Adapter reads:
$options = get_option('apprco_plugin_options', array());
// Returns: [] (empty array)

// Validation check:
if (empty($options['api_subscription_key'])) {
    return ['success' => false, 'error' => 'API credentials not configured.'];
}
// ❌ FAILS! Even though user just entered credentials!
```

### Test Case 2: Existing Installation with Old Settings

**Scenario:** User has old settings from legacy UI, migrates to React UI

```bash
# Initial state (legacy settings exist)
wp option get apprco_plugin_options
# Result:
# {
#   "api_subscription_key": "old-key-123",
#   "api_base_url": "https://old-url.com"
# }
```

**Migration Runs (One Time):**

```php
// Settings_Manager::maybe_migrate()
$old_options = get_option('apprco_plugin_options');
// Copies to new system

wp option get apprco_settings
# Result:
# {
#   "api": {
#     "subscription_key": "old-key-123",
#     "base_url": "https://old-url.com"
#   }
# }
```

**User Updates Via React UI:**

```bash
# User enters NEW credentials in Settings.jsx
# Saves to apprco_settings only!

wp option get apprco_settings
# Result:
# {
#   "api": {
#     "subscription_key": "NEW-key-456",  ← Updated!
#     "base_url": "https://NEW-url.com"
#   }
# }

wp option get apprco_plugin_options
# Result:
# {
#   "api_subscription_key": "old-key-123",  ← Still old!
#   "api_base_url": "https://old-url.com"
# }
```

**Import Uses OLD Credentials:**

```php
// Import Adapter:
$options = get_option('apprco_plugin_options');
// Returns: ['api_subscription_key' => 'old-key-123', ...]

// ❌ API request goes out with OLD credentials!
// ❌ Even though user entered NEW credentials in UI!
```

### Test Case 3: API Test vs Manual Sync

**Scenario:** Compare API Test (works) vs Manual Sync (broken)

#### API Test Endpoint (WORKS!) ✓

**File:** `includes/class-apprco-rest-controller.php` (Line 136-223)

```php
public static function test_api_connection(): WP_REST_Response {
    // ✓ Uses NEW settings system
    $settings_manager = Apprco_Settings_Manager::get_instance();

    $base_url = $settings_manager->get( 'api', 'base_url' );
    $api_key  = $settings_manager->get( 'api', 'subscription_key' );

    // Makes request with user-entered credentials
    $response = wp_remote_get(
        add_query_arg( array( 'PageNumber' => 1, 'PageSize' => 1 ), $base_url ),
        array(
            'headers' => array(
                'Ocp-Apim-Subscription-Key' => $api_key,
                'X-Version' => '2',
            ),
            'timeout' => 30,
        )
    );

    // ✓ This works! User sees "API connection successful!"
}
```

#### Manual Import Endpoint (BROKEN!) ✗

**File:** `includes/class-apprco-rest-controller.php` (Line 120-129)

```php
public static function run_manual_import(): WP_REST_Response {
    // Calls adapter
    $adapter = Apprco_Import_Adapter::get_instance();
    $result = $adapter->run_manual_sync();

    // Adapter uses OLD settings system
    // Returns: ['success' => false, 'error' => 'API credentials not configured.']
}
```

**File:** `includes/class-apprco-import-adapter.php` (Line 78-89)

```php
public function run_manual_sync( array $override_options = array() ): array {
    // ✗ Uses OLD settings system
    $options = get_option( 'apprco_plugin_options', array() );

    // ✗ Credentials not found (empty array)
    if ( empty( $options['api_subscription_key'] ) ) {
        return array(
            'success' => false,
            'error'   => 'API credentials not configured.',
        );
    }
}
```

**Result:**
- API Test button: ✓ "Connection successful!"
- Manual Sync button: ✗ "API credentials not configured"
- **Same credentials, different outcome!**

---

## IMPACT ASSESSMENT

### Affected Functionality

#### ✗ BROKEN:
1. **Manual Sync Button** (Settings page / Dashboard)
   - Always fails with "API credentials not configured"
   - Users cannot manually trigger imports

2. **Scheduled Imports** (WP Cron / Action Scheduler)
   - Reads from `apprco_plugin_options`
   - Uses old/empty credentials
   - Scheduled syncs fail silently

3. **Initial Import** (After configuration)
   - Users complete settings, expect data
   - No data imported (credentials not used)

4. **Wizard Imports** (Import Wizard)
   - May use hardcoded defaults instead of user settings
   - Inconsistent behavior

#### ✓ WORKING:
1. **API Test Button** (Settings page)
   - Uses Settings Manager (new system)
   - Shows "Connection successful"
   - Gives user false confidence!

2. **Settings Save/Load** (Settings page)
   - React UI correctly saves/loads to `apprco_settings`
   - UI works perfectly
   - Just doesn't affect imports!

3. **Dashboard Stats** (Dashboard page)
   - Shows correct counts
   - Doesn't require API calls

### User Experience Impact

**User Journey (Broken):**

```
1. User installs plugin
2. User navigates to Settings
3. User enters API credentials carefully
4. User clicks "Save" → "Settings saved successfully!" ✓
5. User clicks "Test Connection" → "API connection successful!" ✓
6. User clicks "Sync Now" → "API credentials not configured" ✗
7. User is confused (they just entered credentials!)
8. User re-enters credentials
9. User saves again
10. User tests again → Works!
11. User syncs again → Still fails!
12. User gives up and uninstalls plugin
```

**Quote from hypothetical user support ticket:**
> "I've entered my API key 5 times. The test button says it works, but sync says credentials not configured. What am I doing wrong???"

### Business Impact

- **Plugin appears broken** to new users
- **Negative reviews** likely
- **Support burden** increases (users report "bug")
- **Credibility damage** (plugin doesn't do basic job)
- **Lost users** (uninstall before using)

---

## RECOMMENDATIONS

### Immediate Fix (Required)

**Option 1: Make Import System Use Settings Manager (Recommended)**

Update all import-related classes to use Settings Manager instead of direct `get_option()` calls.

**Files to Update:**
1. `includes/class-apprco-import-adapter.php` (Line 79)
2. `includes/class-apprco-core.php` (Line 159)
3. `includes/class-apprco-scheduler.php` (Lines 127, 410)
4. `includes/class-apprco-api-importer.php` (Line 72)

**Example Fix for Import Adapter:**

```php
// BEFORE (Line 78-89):
public function run_manual_sync( array $override_options = array() ): array {
    $options = get_option( 'apprco_plugin_options', array() );
    // ...
}

// AFTER:
public function run_manual_sync( array $override_options = array() ): array {
    $settings_manager = Apprco_Settings_Manager::get_instance();

    // Get settings from NEW system
    $options = array(
        'api_subscription_key' => $settings_manager->get( 'api', 'subscription_key' ),
        'api_base_url'         => $settings_manager->get( 'api', 'base_url' ),
        'api_ukprn'            => $settings_manager->get( 'api', 'ukprn' ),
        // ... other settings
    );

    // Merge with overrides
    $options = array_merge( $options, $override_options );

    // Validation now works with correct data!
    if ( empty( $options['api_subscription_key'] ) ) {
        return array(
            'success' => false,
            'error'   => 'API credentials not configured.',
        );
    }

    // ... rest of method
}
```

**Option 2: Add Synchronization Hook**

Keep both systems but sync `apprco_settings` → `apprco_plugin_options` on save.

```php
// In class-apprco-settings-manager.php::save()
public function save( array $settings ): array {
    // Save to new system
    $updated = update_option( self::OPTION_NAME, $settings );

    // Also sync to old system for backwards compatibility
    $old_format = $this->convert_to_old_format( $settings );
    update_option( 'apprco_plugin_options', $old_format );

    return array( 'success' => true );
}
```

**Option 3: Deprecate Old System Entirely**

Remove `apprco_plugin_options` completely, update all 52+ references to use Settings Manager.

### Medium-Term Improvements

1. **Centralize Configuration**
   - Single source of truth for settings
   - Remove hardcoded defaults (7 locations)
   - Use Settings Manager everywhere

2. **Improve Testing**
   - Add integration tests for settings flow
   - Test: Save in UI → Read in backend → Verify match
   - Catch regressions before deployment

3. **Better Migration**
   - Bidirectional sync during transition period
   - Clear deprecation warnings in logs
   - Eventually remove old system entirely

4. **Code Documentation**
   - Document which system is canonical
   - Add deprecation notices to old functions
   - Update inline comments

### Long-Term Architecture

1. **Single Settings System**
   - Retire `apprco_plugin_options` completely
   - Settings Manager as only interface
   - All code uses singleton instance

2. **Better Separation of Concerns**
   - Frontend → REST API → Settings Manager → Database
   - No direct database access from components
   - Proper dependency injection

3. **Configuration Validation**
   - Schema validation on save
   - Required field enforcement
   - Better error messages

---

## APPENDIX: Complete File Listing

### Files Reading from `apprco_plugin_options` (OLD SYSTEM)

```
apprenticeship-connect.php (Lines: 202, 538, 860)
verify.php (Line: 173)
includes/class-apprco-api-importer.php (Line: 72) ← CRITICAL
includes/class-apprco-setup-wizard.php (Lines: 91, 179, 246, 433, 459)
includes/class-apprco-scheduler.php (Lines: 127, 410) ← CRITICAL
includes/class-apprco-import-logger.php (Line: 102)
includes/class-apprco-import-adapter.php (Line: 79) ← CRITICAL
includes/class-apprco-core.php (Line: 159) ← CRITICAL
includes/class-apprco-admin.php (Lines: 374, 428, 435, 442, 469, 488, 495, 502, 508, 515, 522, 529, 536, 543, 701, 762, 811, 898)
```

### Files Using Settings Manager (NEW SYSTEM)

```
includes/class-apprco-settings-manager.php (Definition)
includes/class-apprco-rest-controller.php (Lines: 88, 137) ← GOOD!
includes/class-apprco-rest-api.php (Settings endpoints)
```

### React Files Using REST API (Indirectly NEW SYSTEM)

```
src/admin/pages/Settings.jsx
src/admin/pages/Dashboard.jsx
src/admin/components/APIStatus.jsx
src/admin/components/settings/*.jsx (All 5 settings components)
```

---

## SUMMARY STATISTICS

| Metric | Count | Status |
|--------|-------|--------|
| **Total PHP Files** | 28 | |
| **Total React Files** | 28 | |
| **References to `apprco_plugin_options`** | 52+ | ❌ Old System |
| **References to `apprco_settings`** | 2 | ✓ New System |
| **Files Using Settings Manager** | 2 | ⚠️ Too Few |
| **Files Using Old System** | 10+ | ⚠️ Too Many |
| **Hardcoded Default URLs** | 7 | ⚠️ Should be 1 |
| **Critical Bugs Identified** | 1 | 🔴 HIGH SEVERITY |
| **Affected User Actions** | 3 | (Manual sync, scheduled, wizard) |
| **Working User Actions** | 3 | (API test, settings, stats) |

---

## CONCLUSION

The Apprenticeship Connect plugin has a **critical architectural flaw** where user-entered API credentials are saved to a new settings system (`apprco_settings`) but all import operations read from an old system (`apprco_plugin_options`). This causes the plugin to appear non-functional to users who configure it via the modern React UI.

**The fix is straightforward:** Update 4 core files to use `Apprco_Settings_Manager::get_instance()` instead of `get_option('apprco_plugin_options')`. This would immediately restore plugin functionality and align the backend with the frontend.

**Root cause:** "Random solutions stitched together" - a new Settings Manager and React UI were built without updating the underlying import system to use them. The two systems operate independently, causing data to be saved in one place but read from another.

---

**End of Analysis**
