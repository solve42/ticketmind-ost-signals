#!/usr/bin/env bash
# copy_licenses.sh — Mirror license files from lib/ into vendor/third_party_licenses
# Usage:
#   bash scripts/copy_licenses.sh                    # uses defaults: src=lib, dest=vendor/third_party_licenses
#   bash scripts/copy_licenses.sh path/to/lib path/to/dest
#   DRY_RUN=1 bash scripts/copy_licenses.sh          # show what would happen


set -euo pipefail

SRC_DIR="${1:-lib}"
DEST_DIR="${2:-vendor/third_party_licenses}"
MANIFEST="${DEST_DIR}/MANIFEST.tsv"
DRY_RUN="${DRY_RUN:-0}"


# Detect a SHA256 tool (sha256sum on Linux, shasum -a 256 on macOS)
hash_cmd() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum
  elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256
  else
    echo "ERROR: Need sha256sum or shasum installed." >&2
    exit 1
  fi
}


# Pretty print (and honor dry run)
do_copy() {
  local src="$1" dst="$2"
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY] cp \"$src\" \"$dst\""
  else
    echo "cp \"$src\" \"$dst\""
    mkdir -p "$(dirname "$dst")"
    cp -f "$src" "$dst"
  fi
}


# Input validation
if [[ ! -d "$SRC_DIR" ]]; then
  echo "ERROR: Source directory '$SRC_DIR' not found." >&2
  exit 1
fi

# Prepare destination + manifest header
if [[ "$DRY_RUN" != "1" ]]; then
  mkdir -p "$DEST_DIR"
  printf "source_abs_path\tdest_abs_path\tsha256\tlicense_first_line\n" > "$MANIFEST"
fi


# Find common license file names (case-insensitive)
# Add more patterns if needed.
mapfile -t LICENSE_FILES < <(find "$SRC_DIR" -type f \
  \( -iname 'license' -o -iname 'license.txt' -o -iname 'license.md' \
     -o -iname 'copying' -o -iname 'copying.txt' -o -iname 'copying.md' \
     -o -iname 'notice'  -o -iname 'notice.txt'  -o -iname 'notice.md' \) \
  | sort)


if [[ "${#LICENSE_FILES[@]}" -eq 0 ]]; then
  echo "No license files found under '$SRC_DIR'." >&2
  exit 0
fi


echo "Found ${#LICENSE_FILES[@]} license file(s)."
echo "Destination root: $DEST_DIR"
[[ "$DRY_RUN" == "1" ]] && echo "Running in DRY RUN mode."


for src in "${LICENSE_FILES[@]}"; do
  # Relative path from SRC_DIR (e.g., lib/symfony/http-client/LICENSE -> symfony/http-client/LICENSE)
  rel="${src#"$SRC_DIR"/}"
  dst="${DEST_DIR}/${rel}"

  do_copy "$src" "$dst"

  # Manifest entry
  if [[ "$DRY_RUN" != "1" ]]; then
    # Compute sha256
    sha=$((hash_cmd) < "$src" | awk '{print $1}')
    # First line preview (safe for tabs)
    first_line="$(head -n 1 "$src" | tr '\t' ' ' | tr -d '\r')"
    printf "%s\t%s\t%s\t%s\n" "$src" "$dst" "$sha" "$first_line" >> "$MANIFEST"
  fi
done

if [[ "$DRY_RUN" == "1" ]]; then
  echo "Dry run complete. No files were written."
else
  echo "Done. Wrote $(wc -l < "$MANIFEST") lines (including header) to $MANIFEST"
fi