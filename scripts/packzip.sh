#!/usr/bin/env bash
# Build the same plugin archive locally and in GitHub Actions.
# Usage: bash scripts/packzip.sh [output.zip]
set -euo pipefail

PLUGIN_DIR="ticketmind-ost-signals"

if [[ $# -gt 1 || ${1:-} == --help || ${1:-} == -h ]]; then
  echo "Usage: bash scripts/packzip.sh [output.zip]"
  if [[ $# -gt 1 ]]; then exit 1; fi
  exit 0
fi

command -v zip >/dev/null 2>&1 || { echo "zip is required." >&2; exit 1; }

REPO_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ $# -eq 1 ]]; then
  ZIP_PATH="$1"
else
  ZIP_PATH="${REPO_ROOT}/${REPO_ROOT##*/}.zip"
fi

case "$ZIP_PATH" in
  /*) ;;
  *) ZIP_PATH="${PWD}/${ZIP_PATH}" ;;
esac
if [[ "$ZIP_PATH" != *.zip ]]; then
  echo "Output path must end in .zip." >&2
  exit 1
fi

# Build a fresh archive so files removed or excluded since the previous build
# cannot remain in an existing zip. Replace the output only after zip succeeds.
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf -- "$BUILD_DIR"' EXIT
EXCLUDE_PATTERNS=(
     ".git" ".git/*" \
     ".gitignore" \
     "scripts/*" "*/scripts/*" \
     ".github/*" "*/.github/*" \
     ".claude*" "*/.claude*" \
     ".codex*" "*/.codex*" \
     ".agents" ".agents/*" "*/.agents/*" \
     ".aws" ".aws/*" "*/.aws/*" \
     ".vscode/*" "*/.vscode/*" \
     ".idea/*" "*/.idea/*" \
     "tests/*" "*/tests/*" \
     "docs/*" "*/docs/*" \
     "CLAUDE.md" \
     "composer.lock" \
     "RELEASE_NOTES.md" \
     "*.zip" \
     "*.sh" \
     ".DS_Store" "*/.DS_Store"
)
ZIP_EXCLUDES=()
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
  ZIP_EXCLUDES+=("${PLUGIN_DIR}/${pattern}")
done

# zip follows the directory symlink, giving every entry a stable parent name
# without copying the checkout or depending on its local directory name.
ln -s -- "$REPO_ROOT" "${BUILD_DIR}/${PLUGIN_DIR}"
cd -- "$BUILD_DIR"
zip -r "${BUILD_DIR}/plugin.zip" "$PLUGIN_DIR" -x "${ZIP_EXCLUDES[@]}"
mkdir -p -- "$(dirname -- "$ZIP_PATH")"
mv -f -- "${BUILD_DIR}/plugin.zip" "$ZIP_PATH"
ls -lah -- "$ZIP_PATH"
