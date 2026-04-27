# Plugin Deployment Guide

## Quick Start

To build a WordPress-ready plugin zip file:

```bash
npm run build:plugin
```

The deployable zip will be created at: `dist/apprenticeship-connect.zip`

---

## What Gets Included in the ZIP

### ✅ INCLUDED
- **Built JavaScript/CSS** (`assets/build/`)
  - All compiled JS files (`admin.js`, `dashboard.js`, `settings.js`, etc.)
  - All compiled CSS files with RTL support
  - Asset PHP files for dependency management
- **PHP Source Code** (`includes/`, `*.php`)
  - All class files
  - REST API endpoints
  - Admin pages
  - Frontend functionality
- **Composer Dependencies** (`vendor/`)
  - Production dependencies only (`composer/installers`)
  - Optimized autoloader
- **Assets** (`assets/`)
  - Images
  - Icons
  - Other static files
- **Languages** (`languages/`)
  - Translation files (.po, .pot, .mo)
- **Essential Files**
  - `apprenticeship-connect.php` (main plugin file)
  - `uninstall.php` (cleanup on uninstall)
  - `readme.txt` (WordPress.org readme)
  - `LICENSE.txt`

### ❌ EXCLUDED
- **Development Files**
  - `/node_modules/` (18,000+ npm packages)
  - `/src/` (React/JS source files)
  - `package.json`, `package-lock.json`
  - `composer.json`, `composer.lock`
  - `webpack.config.js`
- **Build Tools**
  - `.eslintrc.js`, `phpcs.xml`
  - `.prettierrc`, `.editorconfig`
- **Git & CI**
  - `.git/`, `.github/`
  - `.gitignore`, `.gitattributes`
- **Testing**
  - `/tests/`, `/coverage/`
  - `phpunit.xml`
- **Documentation** (optional - can be removed)
  - Most `.md` files (except `README.md`)

---

## Build Commands

### Full Production Build
```bash
npm run build:production
```
Runs `npm run build` + creates zip in one command

### Build Script Only
```bash
npm run build:plugin
```
Uses existing built assets, creates zip

### Manual Build
```bash
bash build-plugin.sh
```
Direct execution of build script

### Clean Everything
```bash
npm run clean
```
Removes: `assets/build/`, `node_modules/`, `build/`, `dist/`

---

## Build Process Details

The `build-plugin.sh` script does the following:

1. **Clean previous builds**
   - Removes `build/` and `dist/` directories

2. **Install Node dependencies**
   - Runs `npm ci` (clean install)

3. **Build JavaScript/CSS**
   - Runs `npm run build` (webpack compilation)
   - Verifies `assets/build/` exists

4. **Install Composer dependencies (production)**
   - Runs `composer install --no-dev --optimize-autoloader`
   - Only installs runtime dependencies
   - Optimizes autoloader for production

5. **Copy plugin files**
   - Copies all files to `build/apprenticeship-connect/`
   - Excludes files matching `.distignore` patterns

6. **Verify build**
   - Checks required files exist
   - Checks built assets exist
   - Shows build directory size

7. **Create ZIP**
   - Creates `dist/apprenticeship-connect.zip`
   - Shows ZIP size and contents

8. **Cleanup**
   - Removes `build/` directory
   - Restores dev Composer dependencies
   - Keeps `dist/` directory with ZIP

---

## Uploading to WordPress

### Method 1: WordPress Admin (Recommended)
1. Navigate to: **Plugins → Add New → Upload Plugin**
2. Click **Choose File** and select `dist/apprenticeship-connect.zip`
3. Click **Install Now**
4. Click **Activate Plugin**

### Method 2: Manual Upload
1. Upload `dist/apprenticeship-connect.zip` to server
2. Extract to `/wp-content/plugins/`
   ```bash
   cd /wp-content/plugins/
   unzip /path/to/apprenticeship-connect.zip
   ```
3. Activate via WordPress Admin

### Method 3: WP-CLI
```bash
wp plugin install /path/to/apprenticeship-connect.zip --activate
```

---

## Troubleshooting

### "Build failed - assets/build directory not found"
**Problem:** JavaScript build failed

**Solution:**
```bash
npm install
npm run build
```

### "Composer not found"
**Problem:** Composer isn't installed

**Solution:** Build still works, but won't include `vendor/` folder. Install Composer:
```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### "ZIP is too large"
**Problem:** Development files included

**Solution:** Check `.distignore` file, ensure patterns match dev files

### "Plugin doesn't work after upload"
**Problem:** Missing built assets or dependencies

**Solution:**
1. Check ZIP contents:
   ```bash
   unzip -l dist/apprenticeship-connect.zip | grep "assets/build"
   ```
2. Verify built files exist:
   ```bash
   ls -lh assets/build/
   ```
3. Rebuild:
   ```bash
   npm run clean
   npm run build:production
   ```

---

## File Size Comparison

**Development (with node_modules):** ~350 MB
**Production ZIP:** ~400 KB (0.4 MB)

**Size breakdown:**
- Built JavaScript: ~43 KB
- Built CSS: ~10 KB
- PHP Source: ~620 KB
- Vendor (Composer): ~230 KB
- Assets (images, etc.): Variable

---

## Deployment Checklist

Before uploading:
- [ ] Run `npm run build:plugin`
- [ ] Verify ZIP created: `ls -lh dist/apprenticeship-connect.zip`
- [ ] Check ZIP size (~400 KB expected)
- [ ] Test ZIP extraction locally
- [ ] Verify `assets/build/` exists in ZIP
- [ ] Verify `vendor/` exists in ZIP
- [ ] Verify `includes/` has all PHP files
- [ ] Check no `node_modules/` in ZIP
- [ ] Check no `/src/` in ZIP

After uploading:
- [ ] Plugin activates without errors
- [ ] Settings page loads
- [ ] JavaScript loads (check browser console)
- [ ] REST endpoints work (`/wp-json/apprco/v1/`)
- [ ] Import functionality works
- [ ] Frontend displays correctly

---

## WordPress.org Submission

If submitting to WordPress.org plugin directory:

1. Build plugin: `npm run build:plugin`
2. The ZIP at `dist/apprenticeship-connect.zip` is ready
3. Submit via: https://wordpress.org/plugins/developers/add/
4. Ensure `readme.txt` follows WordPress format
5. Tag releases in Git matching version

**WordPress.org checks:**
- All code must be GPL compatible
- No external dependencies without disclosure
- No "phone home" functionality
- Security best practices followed

---

## CI/CD Integration

### GitHub Actions Example
```yaml
name: Build Plugin

on:
  push:
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: npm ci && composer install
      - name: Build plugin
        run: npm run build:plugin
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: apprenticeship-connect
          path: dist/apprenticeship-connect.zip
```

---

## Development vs Production

**Development:**
- Node modules installed (~350 MB)
- Source files in `/src/`
- Composer dev dependencies
- Development tools (ESLint, PHPCS)
- Git repository

**Production (ZIP):**
- Built assets only (~50 KB)
- No source files
- Production Composer deps only (~230 KB)
- No development tools
- No Git files

---

## Support

For build issues:
1. Check build script output for errors
2. Verify Node.js >= 18.0.0: `node --version`
3. Verify NPM >= 8.0.0: `npm --version`
4. Try clean rebuild: `npm run clean && npm run build:production`

For plugin issues after deployment:
1. Check WordPress error log
2. Check browser console for JavaScript errors
3. Verify PHP version >= 7.4
4. Verify WordPress version >= 6.0

---

**Build successful? Upload and enjoy!** 🚀
