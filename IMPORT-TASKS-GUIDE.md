# Import Tasks - Complete Setup Guide

This guide explains how the Import Tasks system works and how to use it to automatically import apprenticeship vacancies from APIs.

## 🎯 What You Asked For vs What's Implemented

### ✅ Your Requirements

1. **Provider API Settings** ✓
   - Configure API base URL, endpoint, authentication
   - Custom headers and parameters
   - Multiple authentication types (header key, bearer token, basic auth)

2. **Import Jobs with Frequency** ✓
   - Create unlimited import tasks
   - Schedule with WP-Cron: hourly, twice daily, daily, weekly
   - Set specific time of day for execution

3. **Pagination & Data Fetching** ✓
   - Automatically loops through ALL pages (like your POC)
   - Rate limiting: 250ms delay between requests
   - Safety limit: max 100 pages per run
   - Extracts data using JSONPath-style syntax

4. **Duplicate Detection** ✓
   - Compares using `unique_id_field` (e.g., vacancyReference)
   - Updates existing vacancies
   - Creates new vacancies
   - Tracks: fetched, created, updated, errors

5. **Logs & Status Tracking** ✓
   - Database table: `wp_apprco_import_logs`
   - Tracks: import ID, timestamp, status, counts
   - Viewable in admin: `/wp-admin/admin.php?page=apprco-logs`

6. **Apply Functionality** ✓
   - External URLs stored in `_apprco_vacancy_url`
   - Opens in new tab with `target="_blank"`
   - Already working on frontend!

## 🚀 Quick Start (Fix 400 Errors)

If you're getting 400 errors on the Import Tasks page:

### Step 1: Create Database Tables

```
Navigate to: /wp-admin/admin.php?page=apprco-db-upgrade
Click: "Run Database Upgrade"
```

This creates:
- `wp_apprco_import_tasks` - Task configurations
- `wp_apprco_import_logs` - Import logs
- `wp_apprco_employers` - Employer cache

### Step 2: Create Your First Import Task

```
Navigate to: /wp-admin/admin.php?page=apprco-import-tasks
Click: "Add New"
```

Fill in:
- **Name**: UK Government Apprenticeships
- **API Base URL**: `https://api.apprenticeships.education.gov.uk/vacancies`
- **API Endpoint**: `/vacancy`
- **Auth Key**: `Ocp-Apim-Subscription-Key`
- **Auth Value**: Your API key (87d12a11654d4b20acf7b232e89899d2)
- **Data Path**: `vacancies` (where the array of items is)
- **Total Path**: `total` (where the total count is)
- **Unique ID Field**: `vacancyReference`
- **Page Size**: 100

### Step 3: Test Connection

Click "Test Connection" to verify the API works.

### Step 4: Run Import

- Set Status to "Active"
- Click "Run Now"
- Watch the logs in real-time!

### Step 5: Enable Scheduling

- Check "Enable Schedule"
- Select Frequency: Daily
- Set Time: 03:00:00 (3 AM)
- Save

Now imports run automatically every day at 3 AM!

## 📊 How It Works (Like Your POC)

### Your POC Script Flow

```php
// 1. Geocode location (if provided)
$coords = getCoordinatesFromLocation($location);

// 2. Pagination loop
$page = 1;
do {
    $params['PageNumber'] = $page;
    $response = $client->get($url, $params);
    $allVacancies = array_merge($allVacancies, $response['vacancies']);
    $page++;
} while ($page <= $totalPages);

// 3. Cache result
writeCache($cacheFile, $allVacancies);

// 4. Return
return $allVacancies;
```

### Plugin Implementation

```php
// 1. Geocoding available via Apprco_Geocoder class
$geocoder = Apprco_Geocoder::get_instance();
$coords = $geocoder->geocode_postcode('SW1A 1AA');

// 2. Pagination loop (class-apprco-import-tasks.php:539-565)
$page = 1;
do {
    $result = $this->fetch_page($task, $page);
    $all_items = array_merge($all_items, $result['items']);
    $page++;
    usleep(250000); // Rate limiting
} while (!empty($result['items']) && $page <= $max_pages);

// 3. Duplicate detection (line 689)
$existing = $this->find_existing_post($unique_id, 'apprco_vacancy');

// 4. Create or update (lines 715-762)
if ($existing) {
    wp_update_post($post_data);
} else {
    wp_insert_post($post_data);
}

// 5. WP-Cron scheduling (class-apprco-task-scheduler.php)
wp_schedule_event($next_run, 'daily', 'apprco_run_scheduled_task', [$task_id]);
```

## 🗄️ Database Tables

### wp_apprco_import_tasks

Stores import task configurations:

| Field | Description |
|-------|-------------|
| `id` | Task ID |
| `name` | Task name |
| `status` | active, inactive, draft |
| `api_base_url` | API base URL |
| `api_endpoint` | API endpoint path |
| `api_auth_key` | Auth header name |
| `api_auth_value` | API key/token |
| `api_headers` | JSON: additional headers |
| `api_params` | JSON: query parameters |
| `data_path` | JSON path to items array |
| `total_path` | JSON path to total count |
| `page_param` | Page number parameter name |
| `page_size` | Items per page |
| `field_mappings` | JSON: field mapping config |
| `unique_id_field` | Field for duplicate detection |
| `schedule_enabled` | 1 = scheduled, 0 = manual |
| `schedule_frequency` | hourly, daily, weekly |
| `last_run_at` | Last execution timestamp |
| `last_run_fetched` | Items fetched in last run |
| `last_run_created` | Items created in last run |
| `last_run_updated` | Items updated in last run |
| `last_run_errors` | Errors in last run |

### wp_apprco_import_logs

Stores import execution logs:

| Field | Description |
|-------|-------------|
| `id` | Log ID |
| `import_id` | Unique import session ID |
| `log_level` | debug, info, warning, error |
| `message` | Log message |
| `context` | api, core, scheduler, etc |
| `meta_data` | JSON: additional data |
| `created_at` | Timestamp |

## 🔧 API Configuration Examples

### UK Government Apprenticeships API

```json
{
  "api_base_url": "https://api.apprenticeships.education.gov.uk/vacancies",
  "api_endpoint": "/vacancy",
  "api_method": "GET",
  "api_headers": {
    "X-Version": "2",
    "Ocp-Apim-Subscription-Key": "YOUR_KEY_HERE"
  },
  "api_params": {
    "Sort": "AgeDesc",
    "PostedInLastNumberOfDays": 21
  },
  "data_path": "vacancies",
  "total_path": "total",
  "page_param": "PageNumber",
  "page_size_param": "PageSize",
  "page_size": 100,
  "unique_id_field": "vacancyReference"
}
```

### Field Mappings

Maps API fields to WordPress post/meta fields:

```json
{
  "post_title": "title",
  "post_content": "description",
  "_apprco_vacancy_reference": "vacancyReference",
  "_apprco_vacancy_url": "vacancyUrl",
  "_apprco_employer_name": "employerName",
  "_apprco_postcode": "addresses[0].postcode",
  "_apprco_latitude": "addresses[0].latitude",
  "_apprco_longitude": "addresses[0].longitude",
  "_apprco_closing_date": "closingDate"
}
```

**Array Access**: Use `addresses[0].postcode` to access nested arrays.

## 📅 WP-Cron Scheduling

### Available Frequencies

| Frequency | Runs | Best For |
|-----------|------|----------|
| `hourly` | Every hour | High-frequency updates |
| `twicedaily` | 12 AM & 12 PM | Regular updates |
| `daily` | Once per day | Standard sync |
| `weekly` | Once per week | Low-frequency sync |

### How Scheduling Works

1. **Task Saved/Updated**: Automatically schedules/reschedules
2. **WP-Cron Trigger**: WordPress checks scheduled tasks on page loads
3. **Execution**: Calls `apprco_run_scheduled_task` hook
4. **Import Runs**: Fetches all pages, processes items, updates stats
5. **Logs Created**: Saves to database for review

### View Scheduled Tasks

```php
// Check scheduled tasks
$cron = _get_cron_array();
foreach ($cron as $timestamp => $hooks) {
    if (isset($hooks['apprco_run_scheduled_task'])) {
        echo gmdate('Y-m-d H:i:s', $timestamp) . PHP_EOL;
    }
}
```

Or use WP-CLI:
```bash
wp cron event list --format=table
```

## 🔍 Debugging

### Check if Tables Exist

```sql
SHOW TABLES LIKE 'wp_apprco%';
```

Expected:
- `wp_apprco_import_tasks`
- `wp_apprco_import_logs`
- `wp_apprco_employers`

### Check Task Status

```sql
SELECT id, name, status, schedule_enabled, last_run_at, last_run_status
FROM wp_apprco_import_tasks;
```

### View Recent Logs

```sql
SELECT log_level, message, context, created_at
FROM wp_apprco_import_logs
ORDER BY created_at DESC
LIMIT 50;
```

### Common Issues

**400 Error on Import Tasks Page**:
- Tables not created → Run DB Upgrade
- Nonce mismatch → Clear cache, reload

**Import Not Running**:
- Task status ≠ "active" → Change to active
- Schedule disabled → Enable schedule
- WP-Cron not working → Check server cron or use WP-Crontrol plugin

**No Items Created**:
- Wrong `data_path` → Check API response structure
- Wrong `unique_id_field` → Verify field exists in API
- Field mapping errors → Check mappings match API fields

## 🎓 Advanced Features

### Custom Transforms

Add PHP code to transform data before saving:

```php
// Example: Convert dates
$item['closingDate'] = date('Y-m-d', strtotime($item['closingDate']));

// Example: Clean HTML
$item['description'] = wp_kses_post($item['description']);

// Example: Geocode if missing
if (empty($item['latitude'])) {
    $geocoder = Apprco_Geocoder::get_instance();
    $coords = $geocoder->geocode_postcode($item['postcode']);
    $item['latitude'] = $coords['lat'];
    $item['longitude'] = $coords['lon'];
}

return $item;
```

### Progress Callbacks

Monitor import progress in real-time:

```php
$tasks_manager = Apprco_Import_Tasks::get_instance();

$tasks_manager->run_import($task_id, function($progress) {
    if ($progress['phase'] === 'fetching') {
        echo "Fetching page {$progress['page']}: {$progress['fetched']} items\n";
    } else {
        echo "Processing: {$progress['current']}/{$progress['total']}\n";
    }
});
```

## 📝 Summary

You now have a complete system that:

✅ Configures provider API settings
✅ Schedules imports with WP-Cron
✅ Loops through all pages automatically
✅ Detects and handles duplicates
✅ Logs everything to database
✅ Displays apply links on frontend
✅ Uses geocoding for location searches
✅ Supports custom data transformations

**Everything from your POC is implemented!**

## 🆘 Need Help?

1. Check database tables exist: `/wp-admin/admin.php?page=apprco-db-upgrade`
2. View import logs: `/wp-admin/admin.php?page=apprco-logs`
3. Test API connection before running full import
4. Start with small page sizes (10-20) for testing
5. Check WordPress debug log: `wp-content/debug.log`

---

**Next Steps**: Navigate to Import Tasks, create your first task, and click "Run Now" to see it in action!
