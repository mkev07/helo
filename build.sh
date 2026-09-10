#!/usr/bin/env bash
# Builds the release zip, syncs update.json, and optionally uploads both to S3.
#
#   ./build.sh              build only
#   ./build.sh --publish    build and upload
#
# The folder INSIDE the zip must stay "helo-smtp" — WordPress
# installs an update into whatever folder the archive contains, so a mismatch
# leaves you with two copies of the plugin. The zip's own filename is free,
# which is why it carries the version.

set -euo pipefail
cd "$(dirname "$0")"

# --- configure once -------------------------------------------------------
# Public HTTPS prefix the site will fetch from.
BASE_URL="https://YOUR-BUCKET.s3.eu-central-1.amazonaws.com/plugins/helo-smtp"
# Same location, as an s3:// URI, for uploads.
S3_URI="s3://YOUR-BUCKET/plugins/helo-smtp"
# Leave empty for AWS. For Hetzner/other S3-compatible, e.g.
# https://fsn1.your-objectstorage.com
S3_ENDPOINT=""
# Add --acl public-read here if the bucket uses ACLs instead of a bucket policy.
EXTRA_ARGS=()
# --------------------------------------------------------------------------

SLUG="helo-smtp"
VERSION=$(grep -m1 "^ \* Version:" "$SLUG/$SLUG.php" | awk '{print $3}')
ZIP="$SLUG-$VERSION.zip"
MANIFEST_URL="$BASE_URL/update.json"

# The plugin header decides which host WordPress polls. If it disagrees with
# BASE_URL the update channel is silently dead, so fail loudly instead.
HEADER_URI=$(grep -m1 "^ \* Update URI:" "$SLUG/$SLUG.php" | sed 's/^ \* Update URI:[[:space:]]*//')
if [[ "$HEADER_URI" != "$MANIFEST_URL" ]]; then
	echo "error: Update URI header does not match BASE_URL" >&2
	echo "  header:   $HEADER_URI" >&2
	echo "  expected: $MANIFEST_URL" >&2
	exit 1
fi

rm -f "$SLUG"-*.zip "$SLUG.zip"
zip -qr "$ZIP" "$SLUG" -x "*/tests/*" "*.DS_Store"

python3 - "$VERSION" "$BASE_URL/$ZIP" <<'PY'
import datetime, json, sys

version, url = sys.argv[1], sys.argv[2]

with open('update.json') as fh:
    manifest = json.load(fh)

manifest['version'] = version
manifest['download_url'] = url
manifest['last_updated'] = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

with open('update.json', 'w') as fh:
    json.dump(manifest, fh, indent=2, ensure_ascii=False)
    fh.write('\n')
PY

echo "built $ZIP"

if [[ "${1:-}" != "--publish" ]]; then
	echo "run ./build.sh --publish to upload"
	exit 0
fi

endpoint=()
[[ -n "$S3_ENDPOINT" ]] && endpoint=(--endpoint-url "$S3_ENDPOINT")

# Versioned zips never change, so they cache forever. The manifest is the
# thing that must go stale quickly, or a release takes a day to show up.
aws "${endpoint[@]}" s3 cp "$ZIP" "$S3_URI/$ZIP" \
	--content-type application/zip \
	--cache-control "public, max-age=31536000, immutable" \
	"${EXTRA_ARGS[@]}"

aws "${endpoint[@]}" s3 cp update.json "$S3_URI/update.json" \
	--content-type application/json \
	--cache-control "public, max-age=300" \
	"${EXTRA_ARGS[@]}"

echo "published $VERSION"
echo "verify: curl -s $MANIFEST_URL"
