# Plugin Upgrade Plan - From Poor to Professional

## Current State Analysis: Why It's "Unbelievably Poor"

### Critical Problems

#### 1. **Three Competing Import Systems** 🔥
```
- Apprco_Core::manual_sync()          ← Old system
- Import Wizard (class-apprco-import-wizard.php)  ← Half-implemented
- Import Tasks (class-apprco-import-tasks.php)    ← New system

Result: Confusion, code duplication, maintenance nightmare
```

#### 2. **Settings Chaos**
```
- Settings Page: /wp-admin/admin.php?page=apprco-settings
  → Saves to wp_options as 'apprco_plugin_options'

- Import Tasks: Each task has its own API config
  → Saves to wp_apprco_import_tasks table

- Provider Config: Stored in provider instances
  → No persistence!

Result: No single source of truth, settings conflicts
```

#### 3. **Mixed JavaScript Paradigms**
```
assets/js/
  ├── admin.js          ← 500 lines of jQuery spaghetti
  ├── frontend.js       ← More jQuery
  └── import-wizard.js  ← Actually decent modern JS

src/
  └── admin/            ← Modern ES6+ we added but NEVER USED!
      ├── modules/      ← Written but not integrated
      └── components/   ← React components not loaded anywhere

Result: New code exists but old code still runs
```

#### 4. **No Clear User Flow**
```
User wants to import vacancies:
  Option 1: Settings → Test & Sync button
  Option 2: Import Wizard → 4-step process
  Option 3: Import Tasks → Create task, run it

Which one should they use? Nobody knows!
```

#### 5. **Poor Error Handling**
```php
// Typical error handling:
if ($error) {
    wp_send_json_error('Something went wrong');
}

// No details, no recovery, no user guidance
```

#### 6. **No TypeScript/Types**
```javascript
// Everything is any/unknown
function processData(data) {
    return data.items.map(i => i.thing); // What is 'thing'?
}
```

#### 7. **Database Table Creation Fail**
```php
// Only runs on activation - if plugin already active, tables never created!
register_activation_hook(__FILE__, 'create_tables');
```

#### 8. **No Admin UI Framework**
```
- Some pages use WordPress Settings API
- Some use custom HTML forms
- Some use JavaScript rendering
- Zero consistency
```

---

## The Upgrade Plan

### Phase 1: Consolidate & Unify (CRITICAL)

#### 1.1 Single Import System
**Decision**: Use Import Tasks as the ONE import system

**Actions**:
- ✅ Keep: `Apprco_Import_Tasks` (most complete)
- ❌ Deprecate: `Apprco_Core::manual_sync()` (redirect to Import Tasks)
- ❌ Remove: Import Wizard UI (replace with Tasks UI)
- ✅ Keep: Provider abstraction (it's good!)

**Result**: ONE way to import, no confusion

#### 1.2 Unified Settings
**Decision**: Settings control DEFAULTS, Tasks can override

**Structure**:
```
Settings Page (Global Defaults):
  - Default API Provider: UK Gov
  - Default API Key: ****
  - Default Schedule: Daily 3am
  - Display Settings

Import Tasks (Per-Task Overrides):
  - Task Name
  - Use Default API ☑ OR Custom API config
  - Use Default Schedule ☑ OR Custom schedule
  - Field mappings (advanced)
```

**Result**: Simple for beginners, powerful for advanced users

#### 1.3 Single Admin Interface
**Decision**: React-based admin using @wordpress/components

**Pages**:
```
Dashboard          → Overview stats, quick actions
  ↓
Import Tasks       → List of tasks, create/edit
  ↓
Task Editor (React) → Form with live validation
  ↓
Logs & History     → Import results, errors
  ↓
Settings           → Global defaults
```

**Result**: Consistent, modern UI

---

### Phase 2: Modern JavaScript Migration

#### 2.1 Use The Build Tools We Created
```bash
# We created these but never used them:
- webpack.config.js       ← USE IT!
- src/admin/              ← MIGRATE HERE!
- @wordpress/components   ← BUILD WITH THIS!

# Stop using:
- assets/js/admin.js      ← OLD, REPLACE
- jQuery DOM manipulation ← NO MORE
```

#### 2.2 React Component Architecture
```
src/admin/
  ├── index.js                    ← Main entry (loads React)
  ├── pages/
  │   ├── Dashboard.jsx           ← Dashboard page
  │   ├── TaskList.jsx            ← Import tasks list
  │   ├── TaskEditor.jsx          ← Create/edit task form
  │   └── Logs.jsx                ← Import logs viewer
  ├── components/
  │   ├── TaskForm/
  │   │   ├── ApiConfigSection.jsx
  │   │   ├── FieldMappingSection.jsx
  │   │   └── ScheduleSection.jsx
  │   ├── ProviderSelector.jsx
  │   ├── TestConnection.jsx
  │   └── ImportProgress.jsx
  └── store/
      └── tasks.js                ← @wordpress/data store
```

#### 2.3 Build Process
```bash
npm run build
  ↓
src/admin/index.js
  ↓
webpack bundle
  ↓
assets/build/admin.js (+ admin.asset.php)
  ↓
PHP enqueues with auto-generated dependencies
```

---

### Phase 3: Provider System Enhancement

#### 3.1 Provider Registry (Keep & Enhance)
```php
✅ Keep:
- Apprco_Provider_Interface
- Apprco_Abstract_Provider
- Apprco_UK_Gov_Provider
- Provider Registry

✨ Add:
- Provider discovery (auto-register from directory)
- Provider marketplace (install new providers)
- Provider validation (test before save)
```

#### 3.2 Settings Integration
```php
// Settings store DEFAULT provider config
$defaults = get_option('apprco_provider_defaults', [
    'uk-gov-apprenticeships' => [
        'subscription_key' => '...',
        'base_url' => '...',
        'page_size' => 100
    ]
]);

// Tasks can use defaults OR override
$task['use_default_api'] = true;  // ← Simple!
$task['api_config'] = [...];      // ← Override if needed
```

---

### Phase 4: Database & State Management

#### 4.1 Guaranteed Table Creation
```php
// Run on EVERY admin page load (with caching)
add_action('admin_init', function() {
    $version = get_option('apprco_db_version');
    if ($version !== APPRCO_DB_VERSION) {
        Apprco_Import_Tasks::create_table();
        Apprco_Import_Logger::create_table();
        Apprco_Employer::create_table();
        update_option('apprco_db_version', APPRCO_DB_VERSION);
    }
});
```

#### 4.2 WordPress Data Store
```javascript
// Use @wordpress/data for state management
import { createReduxStore, register } from '@wordpress/data';

const tasksStore = createReduxStore('apprco/tasks', {
    reducer: (state = {}, action) => {
        switch (action.type) {
            case 'SET_TASKS':
                return { ...state, tasks: action.tasks };
            case 'UPDATE_TASK':
                return {
                    ...state,
                    tasks: state.tasks.map(t =>
                        t.id === action.id ? action.task : t
                    )
                };
            default:
                return state;
        }
    },
    selectors: {
        getTasks: (state) => state.tasks || [],
        getTask: (state, id) => state.tasks?.find(t => t.id === id),
    },
    actions: {
        setTasks: (tasks) => ({ type: 'SET_TASKS', tasks }),
        updateTask: (id, task) => ({ type: 'UPDATE_TASK', id, task }),
    },
    resolvers: {
        *getTasks() {
            const tasks = yield apiFetch({ path: '/apprco/v1/tasks' });
            return { type: 'SET_TASKS', tasks };
        },
    },
});

register(tasksStore);
```

---

### Phase 5: User Experience Overhaul

#### 5.1 Onboarding Flow
```
First Activation:
  ↓
Welcome Screen
  "Let's get you set up!"
  ↓
Provider Selection
  [x] UK Government Apprenticeships (Recommended)
  [ ] Custom API
  ↓
API Key Input
  "Get your key at: https://..."
  [Test Connection] ← Instant feedback
  ↓
First Import
  "Import your first 10 vacancies?"
  [Import Now]
  ↓
Success!
  "✓ Imported 10 vacancies"
  [View Vacancies] [Create Schedule]
```

#### 5.2 Task Creation Wizard (Simplified)
```
Create Import Task:

  Step 1: Basic Info
    - Name: [UK Gov Daily Sync]
    - Description: [Daily import of all UK apprenticeships]

  Step 2: API Config
    ☑ Use default API settings
    ☐ Custom API configuration

  Step 3: Schedule
    ☑ Enable automatic imports
    Frequency: [Daily ▼]
    Time: [03:00 ▼]

  Step 4: Test & Save
    [Test Connection] → Shows sample data
    [Save & Run Now] or [Save Only]
```

#### 5.3 Dashboard (React)
```jsx
<Dashboard>
  <StatsCards>
    <Card title="Total Vacancies">{stats.total}</Card>
    <Card title="Active Tasks">{stats.activeTasks}</Card>
    <Card title="Last Import">{stats.lastImport}</Card>
  </StatsCards>

  <QuickActions>
    <Button onClick={runImport}>Import Now</Button>
    <Button onClick={createTask}>Create Task</Button>
  </QuickActions>

  <RecentImports>
    {imports.map(i => <ImportRow key={i.id} import={i} />)}
  </RecentImports>
</Dashboard>
```

---

### Phase 6: Error Handling & Validation

#### 6.1 Comprehensive Error Messages
```php
// Before:
return array('success' => false, 'error' => 'Failed');

// After:
return array(
    'success' => false,
    'error' => array(
        'code' => 'API_CONNECTION_FAILED',
        'message' => 'Could not connect to UK Government API',
        'details' => 'HTTP 401: Invalid subscription key',
        'suggestion' => 'Check your API key in Settings',
        'doc_url' => 'https://docs.example.com/api-key'
    )
);
```

#### 6.2 Input Validation
```javascript
// React Hook Form + Yup validation
import { useForm } from 'react-hook-form';
import * as yup from 'yup';

const taskSchema = yup.object({
    name: yup.string()
        .required('Task name is required')
        .min(3, 'Must be at least 3 characters'),
    api_key: yup.string()
        .required('API key is required')
        .matches(/^[a-f0-9]{32}$/, 'Invalid API key format'),
    schedule: yup.string()
        .oneOf(['hourly', 'daily', 'weekly']),
});
```

---

### Phase 7: Testing & Documentation

#### 7.1 Unit Tests
```bash
npm run test:unit     # Jest for JavaScript
composer run test     # PHPUnit for PHP
```

#### 7.2 E2E Tests
```bash
npm run test:e2e      # @wordpress/e2e-test-utils
```

#### 7.3 User Documentation
```
- Getting Started Guide
- API Configuration Guide
- Troubleshooting Guide
- Developer API Documentation
```

---

## Implementation Order

### Week 1: Foundation
- [ ] Create database upgrade mechanism (runs on admin_init)
- [ ] Build React dashboard page
- [ ] Unify settings structure
- [ ] Set up proper webpack build

### Week 2: Core Features
- [ ] React Task Editor component
- [ ] Migrate AJAX to REST API endpoints
- [ ] Implement @wordpress/data store
- [ ] Provider settings integration

### Week 3: Polish
- [ ] Onboarding wizard
- [ ] Error handling overhaul
- [ ] Input validation
- [ ] Loading states & feedback

### Week 4: Migration & Cleanup
- [ ] Migration tool (old data → new structure)
- [ ] Remove deprecated code
- [ ] Update documentation
- [ ] Testing & bug fixes

---

## Success Criteria

### User Experience
- [ ] Single, obvious way to import vacancies
- [ ] No 400 errors on first use
- [ ] Clear error messages with solutions
- [ ] Under 3 clicks to create first import

### Code Quality
- [ ] All JavaScript is modern ES6+
- [ ] No jQuery in new code
- [ ] Consistent use of @wordpress/components
- [ ] Type safety (JSDoc or TypeScript)
- [ ] < 10% code duplication

### Performance
- [ ] Dashboard loads < 1s
- [ ] Task creation < 2s
- [ ] Import 1000 vacancies < 5 minutes
- [ ] Memory usage < 128MB per import

### Reliability
- [ ] 100% table creation success rate
- [ ] Automatic error recovery
- [ ] Failed imports can be retried
- [ ] Zero data loss

---

## Breaking Changes

### Removed Features
- ❌ Old "Test & Sync" button (replaced by Tasks)
- ❌ Import Wizard standalone page (merged into Tasks)
- ❌ Direct `manual_sync()` calls (use Tasks API)

### Deprecated APIs
```php
// Deprecated (still works, shows warning)
$core->manual_sync();

// New way
$task_id = Apprco_Import_Tasks::create_default_task();
Apprco_Import_Tasks::get_instance()->run_import($task_id);
```

### Migration Path
```php
// Auto-migration on upgrade
if (get_option('apprco_version') < '3.0.0') {
    apprco_migrate_to_v3();
}
```

---

## Next Steps

1. **Review this plan** - Approve/modify
2. **Set priority** - Which phase first?
3. **Start implementation** - Begin with Phase 1?

**Estimated Time**: 4 weeks full-time development
**Difficulty**: High (major refactor)
**Risk**: Medium (good test coverage mitigates)
**Reward**: High (professional, maintainable plugin)
