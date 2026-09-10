#!/usr/bin/env bash
# Builds the release zip and publishes it as a GitHub release.
#
#   ./build.sh              build and sync update.json
#   ./build.sh --publish    build, then create the GitHub release
#
# The repo must be PUBLIC. WordPress fetches the manifest and the zip with no
# credentials, and neither raw.githubusercontent.com nor a release asset on a
# private repo will answer an unauthenticated request.
#
# The folder INSIDE the zip must stay "helo-smtp" — WordPress installs an
# update into whatever folder the archive contains, so a mismatch leaves you
# with two copies of the plugin. The zip's own filename is free, which is why
# it carries the version.

set -euo pipefail
cd "$(dirname "$0")"

REPO="mkev07/helo"
SLUG="helo-smtp"

MAIN="$SLUG/$SLUG.php"
VERSION=$(grep -m1 "^ \* Version:" "$MAIN" | awk '{print $3}')
ZIP="$SLUG-$VERSION.zip"

MANIFEST_URL="https://github.com/$REPO/releases/latest/download/update.json"
DOWNLOAD_URL="https://github.com/$REPO/releases/download/v$VERSION/$ZIP"

die() { echo "error: $*" >&2; exit 1; }

# --- guards ---------------------------------------------------------------

# The version lives in three places and they must not drift: WordPress reads
# the header, the plugin reads the constant, readme.txt is what humans read.
CONSTANT=$(grep -m1 "define( 'HELO_VERSION'" "$MAIN" | sed -E "s/.*'([0-9][^']*)'.*/\1/")
STABLE=$(grep -m1 "^Stable tag:" "$SLUG/readme.txt" | awk '{print $3}')

[[ "$CONSTANT" == "$VERSION" ]] || die "HELO_VERSION is $CONSTANT but the header says $VERSION"
[[ "$STABLE"   == "$VERSION" ]] || die "readme.txt Stable tag is $STABLE but the header says $VERSION"

# The header decides which host WordPress polls. If it disagrees with REPO the
# update channel is silently dead, so fail loudly instead.
HEADER_URI=$(grep -m1 "^ \* Update URI:" "$MAIN" | sed 's/^ \* Update URI:[[:space:]]*//')
[[ "$HEADER_URI" == "$MANIFEST_URL" ]] || die "Update URI header is $HEADER_URI, expected $MANIFEST_URL"

# --- build ----------------------------------------------------------------

rm -f "$SLUG"-*.zip
zip -qr "$ZIP" "$SLUG" -x "*/tests/*" "*.DS_Store"

python3 - "$VERSION" "$DOWNLOAD_URL" <<'PY'
import datetime, json, sys

version, url = sys.argv[1], sys.argv[2]

with open('update.json') as fh:
    manifest = json.load(fh)

# Only rewrite when something real changed. Touching last_updated on every
# run would dirty the tree that --publish insists is clean.
if manifest.get('version') != version or manifest.get('download_url') != url:
    manifest['version'] = version
    manifest['download_url'] = url
    manifest['last_updated'] = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

    with open('update.json', 'w') as fh:
        json.dump(manifest, fh, indent=2, ensure_ascii=False)
        fh.write('\n')

    print('update.json rewritten — commit it before publishing')
PY

echo "built $ZIP"

if [[ "${1:-}" != "--publish" ]]; then
	echo "commit update.json, then run ./build.sh --publish"
	exit 0
fi

# --- publish --------------------------------------------------------------

# Release from committed code only, or the tag will not match what shipped.
[[ -z "$(git status --porcelain)" ]] || die "working tree is dirty — commit update.json first"
git diff --quiet @ @{u} 2>/dev/null || die "local commits not pushed — git push first"

gh release view "v$VERSION" --repo "$REPO" >/dev/null 2>&1 && die "release v$VERSION already exists"

# update.json rides along as an asset so /releases/latest/download/update.json
# always resolves — publishing the release IS publishing the manifest.
gh release create "v$VERSION" "$ZIP" update.json \
	--repo "$REPO" \
	--target main \
	--title "v$VERSION" \
	--generate-notes

echo "published v$VERSION"
echo "verify: curl -sL $MANIFEST_URL | head -5"
