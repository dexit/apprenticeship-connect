#!/usr/bin/env bash
# =============================================================================
# Apprenticeship Connector – Production Build Script
#
# Usage:
#   bash build-plugin.sh [--version 1.2.3]
#
# This script creates a deployable build directory and zip file for WordPress plugin upload.
# It includes built assets and production dependencies only.
#

set -e  # Exit on error
# Outputs:
#   dist/apprenticeship-connector-v{VERSION}.zip   ← ready for WordPress upload
#   dist/apprenticeship-connector-v{VERSION}/      ← extracted folder
# =============================================================================

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

# Get plugin details
PLUGIN_SLUG="apprenticeship-connect"
PLUGIN_VERSION=$(grep "Version:" apprenticeship-connect.php | awk '{print $3}')
BUILD_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo -e "${YELLOW}Plugin:${NC} ${PLUGIN_SLUG}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""

# Clean previous builds
echo -e "${BLUE}→ Cleaning previous builds...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "dist"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Install Node dependencies
echo -e "${BLUE}→ Installing Node dependencies...${NC}"
if ! command -v npm &> /dev/null; then
    echo -e "${RED}✗ npm is not installed${NC}"
    exit 1
fi
npm install --prefer-offline --no-audit

# Build JavaScript/CSS assets
echo -e "${BLUE}→ Building JavaScript and CSS assets...${NC}"
npm run build
if [ ! -d "assets/build" ]; then
    echo -e "${RED}✗ Build failed - assets/build directory not found${NC}"
    exit 1
fi

# ── Detect version from plugin header ────────────────────────────────────────
if [[ -n "$VERSION_OVERRIDE" ]]; then
    PLUGIN_VERSION="$VERSION_OVERRIDE"
else
    PLUGIN_VERSION=$(grep -m1 "^.*Version:" "$PLUGIN_FILE" | awk '{print $NF}' | tr -d '[:space:]')
fi

# Copy plugin files to build directory
echo -e "${BLUE}→ Copying plugin files...${NC}"

# Copy all files (exclude build directory to avoid copying into itself)
echo -e "  Copying files..."
find . -mindepth 1 -maxdepth 1 ! -name "${BUILD_DIR}" ! -name "dist" ! -name "*.zip" -exec cp -r {} "${BUILD_DIR}/${PLUGIN_SLUG}/" \;

# Remove files matching .distignore patterns from the build directory
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

# Also remove any recursive build directory if it somehow got copied
rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}/${BUILD_DIR}"

# Verify critical files are present
echo -e "${BLUE}→ Verifying build...${NC}"
EXPORT_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-v${PLUGIN_VERSION}.zip"

echo -e "${BLUE}══════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Apprenticeship Connector  v${PLUGIN_VERSION}${NC}"
echo -e "${BLUE}══════════════════════════════════════════════${NC}"

# ── 1. Clean ─────────────────────────────────────────────────────────────────
echo -e "${BLUE}→ Cleaning dist/...${NC}"
rm -rf "$DIST_DIR"
mkdir -p "$EXPORT_DIR"

# ── 2. Node – build JS/CSS ────────────────────────────────────────────────────
echo -e "${BLUE}→ Installing Node dependencies...${NC}"
npm ci --prefer-offline --no-audit --loglevel error

echo -e "${BLUE}→ Building JS/CSS assets (production)...${NC}"
NODE_ENV=production npm run build

if [[ ! -f "build/admin/index.js" ]]; then
    echo -e "${RED}✗ Build failed – build/admin/index.js not found${NC}"; exit 1
fi
echo -e "${GREEN}✓ JS/CSS assets built${NC}"

# ── 3. PHP – Composer production install ─────────────────────────────────────
echo -e "${BLUE}→ Installing PHP production dependencies (Composer)...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
echo -e "${GREEN}✓ Composer dependencies installed${NC}"

# ── 4. Copy plugin files ──────────────────────────────────────────────────────
echo -e "${BLUE}→ Copying plugin files to ${EXPORT_DIR}/...${NC}"

INCLUDE_FILES=(
    "$PLUGIN_FILE"
    "uninstall.php"
    "readme.txt"
    "README.md"
    "LICENSE"
    "LICENSE.txt"
)

INCLUDE_DIRS=(
    "includes"
    "languages"
    "build"
    "vendor"
)

for FILE in "${INCLUDE_FILES[@]}"; do
    [[ -f "$FILE" ]] && cp "$FILE" "$EXPORT_DIR/" && echo "  + $FILE"
done

for DIR in "${INCLUDE_DIRS[@]}"; do
    if [[ -d "$DIR" ]]; then
        cp -r "$DIR" "$EXPORT_DIR/$DIR"
        echo "  + $DIR/"
    fi
done

# ── 5. Strip dev artefacts from vendor ────────────────────────────────────────
echo -e "${BLUE}→ Removing dev-only vendor files...${NC}"
find "$EXPORT_DIR/vendor" \( -name "*.md" -o -name "*.txt" -o -name "tests" -o -name "test" -o -name "*.test.php" \) -prune -exec rm -rf {} + 2>/dev/null || true

# Create ZIP file inside the build directory
echo -e "${BLUE}→ Creating ZIP file...${NC}"
cd "${BUILD_DIR}"
zip -r "${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Verify ZIP
if [ ! -f "${BUILD_DIR}/${ZIP_NAME}" ]; then
    echo -e "${RED}✗ Failed to create ZIP file${NC}"
    exit 1
fi

# Show ZIP details
ZIP_SIZE=$(du -h "${BUILD_DIR}/${ZIP_NAME}" | cut -f1)
echo -e "${GREEN}✓ ZIP created: ${BUILD_DIR}/${ZIP_NAME} (${ZIP_SIZE})${NC}"

# List what's in the ZIP
echo ""
echo -e "${BLUE}→ ZIP contents summary (verifying assets):${NC}"
unzip -l "${BUILD_DIR}/${ZIP_NAME}" | grep -E "assets/build/|apprenticeship-connect.php" | head -n 20
echo "..."
echo ""

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
echo -e "${YELLOW}Build Directory:${NC} ./${BUILD_DIR}/"
echo -e "${YELLOW}Plugin ZIP:${NC} ./${BUILD_DIR}/${ZIP_NAME}"
echo -e "${YELLOW}Size:${NC} ${ZIP_SIZE}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""
echo -e "${GREEN}→ Ready for distribution!${NC}"
echo ""
# ── 6. Create ZIP ────────────────────────────────────────────────────────────
echo -e "${BLUE}→ Creating ZIP ${ZIP_FILE}...${NC}"
cd "$DIST_DIR"
zip -rq "../$ZIP_FILE" "$(basename "$EXPORT_DIR")"
cd ..

echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Build Complete!${NC}"
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo -e "  Plugin folder : ${YELLOW}./${EXPORT_DIR}/${NC}"
echo -e "  ZIP archive   : ${YELLOW}./${ZIP_FILE}${NC}"
echo -e "  Version       : ${YELLOW}${PLUGIN_VERSION}${NC}"
