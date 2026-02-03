#!/bin/bash
#
# Build WordPress Plugin for Distribution
#
# This script creates a deployable zip file for WordPress plugin upload.
# It includes built assets and production dependencies only.
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  WordPress Plugin Build Script${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Get plugin details
PLUGIN_SLUG="apprenticeship-connect"
PLUGIN_VERSION=$(grep "Version:" apprenticeship-connect.php | awk '{print $3}')
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo -e "${YELLOW}Plugin:${NC} ${PLUGIN_SLUG}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""

# Clean previous builds
echo -e "${BLUE}→ Cleaning previous builds...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}"
rm -f "${ZIP_NAME}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DIST_DIR}"

# Install Node dependencies
echo -e "${BLUE}→ Installing Node dependencies...${NC}"
if ! command -v npm &> /dev/null; then
    echo -e "${RED}✗ npm is not installed${NC}"
    exit 1
fi
npm ci --prefer-offline --no-audit

# Build JavaScript/CSS assets
echo -e "${BLUE}→ Building JavaScript and CSS assets...${NC}"
npm run build
if [ ! -d "assets/build" ]; then
    echo -e "${RED}✗ Build failed - assets/build directory not found${NC}"
    exit 1
fi

# Install Composer dependencies (production only)
echo -e "${BLUE}→ Installing Composer dependencies (production only)...${NC}"
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction
    COMPOSER_INSTALLED=true
else
    echo -e "${YELLOW}⚠ Composer not found - skipping PHP dependencies${NC}"
    COMPOSER_INSTALLED=false
fi

# Copy plugin files to build directory
echo -e "${BLUE}→ Copying plugin files...${NC}"

# Function to check if file/dir should be excluded
should_exclude() {
    local path=$1
    # Read .distignore and check if path matches
    while IFS= read -r pattern; do
        # Skip comments and empty lines
        [[ "$pattern" =~ ^#.*$ ]] && continue
        [[ -z "$pattern" ]] && continue

        # Remove leading/trailing whitespace
        pattern=$(echo "$pattern" | xargs)

        # Check if path matches pattern
        if [[ "$path" == $pattern* ]] || [[ "$path" == *"$pattern"* ]]; then
            return 0  # Should exclude
        fi
    done < .distignore
    return 1  # Should include
}

# Copy files efficiently (exclude heavy directories immediately)
echo -e "  Copying plugin files (excluding node_modules, .git, src, tests)..."

# Copy directories we WANT (fast - skip the bloat)
for dir in includes assets languages vendor; do
    if [ -d "$dir" ]; then
        cp -r "$dir" "${BUILD_DIR}/${PLUGIN_SLUG}/"
    fi
done

# Copy root PHP and text files
cp *.php "${BUILD_DIR}/${PLUGIN_SLUG}/" 2>/dev/null || true
cp *.txt "${BUILD_DIR}/${PLUGIN_SLUG}/" 2>/dev/null || true
cp *.md "${BUILD_DIR}/${PLUGIN_SLUG}/" 2>/dev/null || true
cp LICENSE "${BUILD_DIR}/${PLUGIN_SLUG}/" 2>/dev/null || true

# Now remove unwanted files based on .distignore
echo -e "  Removing excluded files..."
while IFS= read -r pattern; do
    # Skip comments and empty lines
    [[ "$pattern" =~ ^#.*$ ]] && continue
    [[ -z "$pattern" ]] && continue

    # Remove leading/trailing whitespace and slashes
    pattern=$(echo "$pattern" | xargs | sed 's:^/::')

    # Skip directories we never copied in the first place
    [[ "$pattern" == "node_modules" ]] && continue
    [[ "$pattern" == ".git" ]] && continue
    [[ "$pattern" == "src" ]] && continue
    [[ "$pattern" == "tests" ]] && continue

    # Remove matching files/directories
    if [ -e "${BUILD_DIR}/${PLUGIN_SLUG}/${pattern}" ]; then
        rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}/${pattern}"
    fi
done < .distignore

# Verify critical files are present
echo -e "${BLUE}→ Verifying build...${NC}"

REQUIRED_FILES=(
    "${BUILD_DIR}/${PLUGIN_SLUG}/apprenticeship-connect.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/includes"
    "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -e "$file" ]; then
        echo -e "${RED}✗ Required file/directory missing: ${file}${NC}"
        exit 1
    fi
done

# Verify built assets exist
BUILT_ASSETS=(
    "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build/admin.js"
    "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build/dashboard.js"
    "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build/settings.js"
)

for asset in "${BUILT_ASSETS[@]}"; do
    if [ ! -f "$asset" ]; then
        echo -e "${RED}✗ Built asset missing: ${asset}${NC}"
        exit 1
    fi
done

echo -e "${GREEN}✓ All required files present${NC}"

# Show what's included
echo -e "${BLUE}→ Build contents:${NC}"
echo ""
du -sh "${BUILD_DIR}/${PLUGIN_SLUG}"/* | sort -h | sed 's/^/  /'
echo ""

# Create ZIP file
echo -e "${BLUE}→ Creating ZIP file...${NC}"
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Verify ZIP
if [ ! -f "${DIST_DIR}/${ZIP_NAME}" ]; then
    echo -e "${RED}✗ Failed to create ZIP file${NC}"
    exit 1
fi

# Show ZIP details
ZIP_SIZE=$(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)
echo -e "${GREEN}✓ ZIP created: ${DIST_DIR}/${ZIP_NAME} (${ZIP_SIZE})${NC}"

# List what's in the ZIP
echo ""
echo -e "${BLUE}→ ZIP contents summary:${NC}"
unzip -l "${DIST_DIR}/${ZIP_NAME}" | head -20
echo "..."
echo ""

# Cleanup build directory (keep dist)
echo -e "${BLUE}→ Cleaning up...${NC}"
rm -rf "${BUILD_DIR}"

# Restore dev dependencies if composer was used
if [ "$COMPOSER_INSTALLED" = true ]; then
    echo -e "${BLUE}→ Restoring dev dependencies...${NC}"
    composer install --no-interaction
fi

# Final summary
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Build Complete! ✓${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Plugin ZIP:${NC} ${DIST_DIR}/${ZIP_NAME}"
echo -e "${YELLOW}Size:${NC} ${ZIP_SIZE}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""
echo -e "${GREEN}→ Ready to upload to WordPress!${NC}"
echo ""
echo -e "${BLUE}Upload locations:${NC}"
echo "  • WordPress Admin → Plugins → Add New → Upload Plugin"
echo "  • /wp-content/plugins/ (extract zip manually)"
echo ""
