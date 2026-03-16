#!/usr/bin/env bash
# =============================================================================
# Apprenticeship Connector – Production Build Script
#
# Usage:
#   bash build-plugin.sh [--version 1.2.3]
#
# Outputs:
#   dist/apprenticeship-connector-v{VERSION}.zip   ← ready for WordPress upload
#   dist/apprenticeship-connector-v{VERSION}/      ← extracted folder
# =============================================================================

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

PLUGIN_SLUG="apprenticeship-connector"
PLUGIN_FILE="apprenticeship-connector.php"
DIST_DIR="dist"

# ── Parse optional --version flag ────────────────────────────────────────────
VERSION_OVERRIDE=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --version) VERSION_OVERRIDE="$2"; shift 2;;
        *) shift;;
    esac
done

# ── Detect version from plugin header ────────────────────────────────────────
if [[ -n "$VERSION_OVERRIDE" ]]; then
    PLUGIN_VERSION="$VERSION_OVERRIDE"
else
    PLUGIN_VERSION=$(grep -m1 "^.*Version:" "$PLUGIN_FILE" | awk '{print $NF}' | tr -d '[:space:]')
fi

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
