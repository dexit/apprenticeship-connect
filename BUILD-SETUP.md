# Modern Build Setup

This document explains the modernized build system for the Apprenticeship Connect plugin using **@wordpress/scripts** and modern JavaScript tooling.

## 🚀 What Changed?

### Before (Traditional)
- Plain jQuery scripts loaded directly
- No build process
- No dependency management
- No code quality tools
- Manual file management

### After (Modern)
- ✅ **React components** with @wordpress/components
- ✅ **Modern ES6+** with Babel transpilation
- ✅ **SCSS** with automatic compilation
- ✅ **Webpack bundling** with code splitting
- ✅ **ESLint & Prettier** for code quality
- ✅ **PHP CodeSniffer** for WordPress standards
- ✅ **npm & Composer** dependency management

## 📦 Prerequisites

- **Node.js** >= 18.0.0
- **npm** >= 8.0.0
- **PHP** >= 7.4
- **Composer** >= 2.0

## 🛠️ Installation

### 1. Install Node Dependencies

```bash
npm install
```

This installs:
- @wordpress/scripts (webpack, babel, eslint, etc.)
- @wordpress/components (React UI library)
- @wordpress/api-fetch (REST API client)
- All other dependencies

### 2. Install PHP Dependencies

```bash
composer install
```

This installs:
- PHP CodeSniffer (PHPCS)
- WordPress Coding Standards
- PHPUnit for testing

## 📝 Available Commands

### JavaScript Build Commands

```bash
# Development build (creates source maps)
npm run start

# Production build (minified, optimized)
npm run build

# Format code with Prettier
npm run format

# Lint JavaScript
npm run lint:js

# Lint CSS/SCSS
npm run lint:css

# Check npm package licenses
npm run check-licenses

# Create plugin ZIP file
npm run plugin-zip

# Run JavaScript tests
npm run test:unit
```

### PHP Commands

```bash
# Lint PHP code
composer run lint

# Auto-fix PHP code style issues
composer run lint:fix

# Run PHP unit tests
composer run test

# Run tests with coverage report
composer run test:coverage
```

## 📁 New Directory Structure

```
apprenticeship-connect/
├── src/                          ← Source files (you edit these)
│   ├── admin/                    ← Admin JavaScript
│   │   ├── index.js             ← Main admin entry
│   │   ├── style.scss           ← Admin styles
│   │   ├── modules/             ← Feature modules
│   │   │   ├── sync.js
│   │   │   ├── api-test.js
│   │   │   ├── cache.js
│   │   │   └── ...
│   │   ├── utils/               ← Utility functions
│   │   │   ├── api.js
│   │   │   └── notice.js
│   │   ├── import-wizard/       ← Import wizard
│   │   │   ├── index.js
│   │   │   └── components/
│   │   │       └── ImportWizard.jsx
│   │   └── meta-box/            ← Meta box functionality
│   │       └── index.js
│   └── frontend/                ← Frontend JavaScript
│       ├── index.js
│       └── style.scss
│
├── assets/
│   ├── build/                   ← Built files (auto-generated, don't edit)
│   │   ├── admin.js
│   │   ├── admin.asset.php     ← Auto-generated dependency info
│   │   ├── admin-style.css
│   │   ├── frontend.js
│   │   ├── frontend-style.css
│   │   └── ...
│   ├── js/                      ← Legacy files (to be migrated)
│   └── css/                     ← Legacy files (to be migrated)
│
├── includes/                     ← PHP classes
├── node_modules/                ← npm dependencies (gitignored)
├── vendor/                      ← Composer dependencies (gitignored)
│
├── package.json                 ← npm configuration
├── composer.json                ← Composer configuration
├── webpack.config.js            ← Webpack build config
├── .eslintrc.js                 ← ESLint config
├── .prettierrc.js               ← Prettier config
├── phpcs.xml                    ← PHP CodeSniffer config
└── .gitignore                   ← Git ignore rules
```

## 🔧 Development Workflow

### 1. Start Development Mode

```bash
npm run start
```

This watches for file changes and automatically rebuilds. It includes:
- Hot module replacement
- Source maps for debugging
- Fast incremental builds

### 2. Make Changes

Edit files in `src/` directory:

```javascript
// src/admin/modules/my-feature.js
import { adminAjax } from '../utils/api';
import { showNotice } from '../utils/notice';

export function initMyFeature() {
    // Your code here
}
```

### 3. Import in Entry Point

```javascript
// src/admin/index.js
import { initMyFeature } from './modules/my-feature';

document.addEventListener('DOMContentLoaded', () => {
    initMyFeature();
});
```

### 4. Build for Production

```bash
npm run build
```

This creates optimized, minified bundles in `assets/build/`.

## 🎨 Using @wordpress/components

Example React component with WordPress components:

```jsx
import { Button, Card, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function MyComponent() {
    const [loading, setLoading] = useState(false);

    return (
        <Card>
            <Notice status="success">
                {__('Success!', 'apprenticeship-connect')}
            </Notice>
            <Button
                isPrimary
                isBusy={loading}
                onClick={() => setLoading(true)}
            >
                {__('Click Me', 'apprenticeship-connect')}
            </Button>
        </Card>
    );
}
```

## 🔌 Updating PHP to Use Built Assets

Instead of:

```php
// Old way
wp_enqueue_script(
    'apprco-admin',
    APPRCO_PLUGIN_URL . 'assets/js/admin.js',
    array('jquery'),
    APPRCO_PLUGIN_VERSION
);
```

Use:

```php
// Modern way
$asset_file = include APPRCO_PLUGIN_DIR . 'assets/build/admin.asset.php';

wp_enqueue_script(
    'apprco-admin',
    APPRCO_PLUGIN_URL . 'assets/build/admin.js',
    $asset_file['dependencies'],  // Auto-generated dependencies
    $asset_file['version']         // Content-based versioning
);

// Enqueue styles
wp_enqueue_style(
    'apprco-admin',
    APPRCO_PLUGIN_URL . 'assets/build/admin-style.css',
    array(),
    $asset_file['version']
);
```

The `.asset.php` files are auto-generated by @wordpress/scripts and include:
- All @wordpress/* dependencies
- Content-based version hash
- Translation information

## 📚 Key Technologies

### @wordpress/scripts
Provides:
- Webpack configuration
- Babel for ES6+ transpilation
- ESLint for JavaScript linting
- Stylelint for CSS linting
- Jest for testing

### @wordpress/components
Pre-built React components:
- Button, Card, Panel
- TextControl, SelectControl
- Notice, Spinner
- Modal, Popover
- And 50+ more!

### @wordpress/api-fetch
WordPress REST API client with:
- Automatic nonce handling
- Middleware support
- Error handling
- Path/URL flexibility

### @wordpress/element
React abstraction for WordPress:
- Same as React but WordPress-branded
- Ensures compatibility across WP versions

## 🧪 Testing

### JavaScript Tests

```bash
npm run test:unit
```

Example test:

```javascript
import { showNotice } from '../utils/notice';

describe('showNotice', () => {
    it('should create a notice element', () => {
        showNotice('Test message', 'success');
        const notice = document.querySelector('.apprco-notice');
        expect(notice).toBeTruthy();
    });
});
```

### PHP Tests

```bash
composer run test
```

Example test:

```php
use ApprCo\UK_Gov_Provider;
use PHPUnit\Framework\TestCase;

class UK_Gov_Provider_Test extends TestCase {
    public function test_get_name() {
        $provider = new UK_Gov_Provider();
        $this->assertEquals('UK Government Apprenticeships', $provider->get_name());
    }
}
```

## 🎯 Migration Strategy

### Phase 1: Setup (✅ Complete)
- Install build tools
- Create source structure
- Configure linting

### Phase 2: Migrate Admin Scripts
1. Start with one module (e.g., sync)
2. Test thoroughly
3. Move to next module
4. Keep old files until fully migrated

### Phase 3: Migrate Frontend
1. Convert frontend.js to ES6+
2. Add React components if needed
3. Optimize bundle size

### Phase 4: Add React Components
1. Import Wizard → Full React
2. Settings Pages → React forms
3. Dashboard Widgets → React

## 💡 Best Practices

### Code Organization
- One feature per module file
- Export named functions
- Keep utils small and focused

### Naming Conventions
- Use camelCase for JavaScript
- Use kebab-case for CSS classes
- Prefix with 'apprco'

### Performance
- Code splitting for large features
- Lazy load React components
- Tree-shake unused dependencies

### Accessibility
- Use @wordpress/components (built-in a11y)
- Add ARIA labels
- Test with keyboard navigation

## 🐛 Troubleshooting

### "Module not found" errors
```bash
rm -rf node_modules package-lock.json
npm install
```

### Build fails
```bash
npm run check-engines  # Verify Node version
npm run lint:js        # Check for syntax errors
```

### PHP lint fails
```bash
composer run lint:fix  # Auto-fix style issues
```

## 📖 Resources

- [@wordpress/scripts Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)
- [@wordpress/components Storybook](https://wordpress.github.io/gutenberg/?path=/story/docs-introduction--page)
- [WordPress JavaScript Handbook](https://developer.wordpress.org/block-editor/how-to-guides/javascript/)
- [webpack Documentation](https://webpack.js.org/)

## 🚦 Next Steps

1. ✅ Run `npm install` to get started
2. ✅ Run `npm run start` for development
3. ⏳ Migrate old JavaScript files to `src/`
4. ⏳ Update PHP enqueue functions
5. ⏳ Add React components
6. ⏳ Write tests
7. ⏳ Build for production with `npm run build`

---

**Questions?** Check the documentation or review example code in `src/admin/modules/`.
