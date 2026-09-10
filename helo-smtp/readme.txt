=== Helo — SMTP & Mail Log ===
Contributors: Kevin Mukoond
Tags: smtp, email, mail, log, wp_mail
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends every WordPress email through your own SMTP server, logs what was sent, and lets you preview or resend any message.

== Description ==

* One settings screen for host, port, encryption, credentials and the From address.
* Test button that reports the actual SMTP error when delivery fails.
* A log of every outgoing email — contact form notifications, WooCommerce mail, password resets, everything that goes through `wp_mail()`.
* A two-pane reading view like a mail client: message list on the left, the selected email on the right.
* Full preview of each message, HTML rendered in a sandboxed frame, with headers, attachments and the failure reason.
* Resend any logged message.
* Automatic daily cleanup of old log entries.

== Installation ==

1. Upload the `helo-smtp` folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **Helo** in the admin menu, fill in your mail server details, save, then send a test.

Recommended: keep the password out of the database by adding this to `wp-config.php`:

`define( 'HELO_SMTP_PASSWORD', 'your-password' );`

== Frequently Asked Questions ==

= Why is nothing showing in the log? =

The log records anything sent through `wp_mail()`. Plugins that talk to an email API directly (SendGrid, Mailgun, Brevo and similar official plugins) bypass `wp_mail()` and will not appear.

= Where is the password stored? =

Encrypted in the options table, using a key derived from the site's `AUTH_SALT`. Changing the salts invalidates it and you will need to re-enter it. The `HELO_SMTP_PASSWORD` constant avoids database storage entirely.

= Gmail / Google Workspace? =

Host `smtp.gmail.com`, port 587, TLS, and an App Password — not the account password.

= How do updates work if this is not on wordpress.org? =

The `Update URI` header points at the manifest attached to the latest GitHub
release. WordPress polls it on its normal update schedule and shows the update
on the Plugins screen like any other. See "Updates" below.

== Updates ==

Updates use the `Update URI` header and the `update_plugins_{$hostname}` filter
that WordPress 5.8 added for self-hosted plugins. No update library, no
wordpress.org listing. Releases are published as GitHub releases.

Two files have to be readable by anyone, because WordPress fetches them with no
credentials:

* `update.json` — the manifest, attached to every release
* `helo-smtp-X.Y.Z.zip` — the package, attached to the same release

**The repository must therefore be public.** A private repository's release
assets and raw files both require an `Authorization` header, which the updater
does not send, and embedding a token in the plugin would ship a credential to
every site that installs it.

= How the URLs work =

The header points at GitHub's permanent "latest release" redirect:

`Update URI: https://github.com/mkev07/helo/releases/latest/download/update.json`

That URL never changes and always resolves to the newest release's manifest, so
publishing a release *is* publishing the update. The manifest in turn points at
that release's zip:

`https://github.com/mkev07/helo/releases/download/vX.Y.Z/helo-smtp-X.Y.Z.zip`

= Releasing =

1. Bump the version in **three** places — `Version:` in the plugin header, `HELO_VERSION` just below it, and `Stable tag:` in this file. `build.sh` refuses to build if they disagree.
2. Add a `changelog` entry to `update.json` and a matching one below.
3. `./build.sh` — builds the zip and rewrites `update.json`.
4. Commit and push.
5. `./build.sh --publish` — tags, creates the release, and uploads both files.

Step 5 will not run against a dirty or unpushed tree, so the tag always matches
what actually shipped.

= Notes =

* The download URL must be HTTPS. The plugin refuses plain HTTP, because an update package is executable code.
* The folder *inside* the zip must stay `helo-smtp`; the zip's own filename carries the version. `build.sh` gets both right.
* Do not use GitHub's automatic "Source code (zip)" as the download URL: its top folder is `helo-X.Y.Z`, which installs as a second, separate plugin.
* Sites cache the manifest for 6 hours. **Helo → Settings → Check for updates** clears that and re-checks immediately.
* Rolling back means publishing a new release with an older-but-higher version number, or editing the latest release's `update.json` asset. WordPress only ever offers an update when the remote version is greater than the installed one.

== Upgrading from "Simple SMTP + Mail Log" ==

Helo is the same plugin under a new name, so it lives in a new folder and
WordPress treats it as a separate install. Do it in this order:

1. **Deactivate** the old plugin. Do not delete it yet.
2. Upload and **activate** Helo. On activation it adopts the old settings and renames the log table, so your credentials and history carry over. The stored password still decrypts, because it is keyed to the site's auth salt and that has not changed.
3. **Delete** the old plugin. Its uninstaller will find nothing left to remove.

Deleting the old plugin before activating Helo drops the log table and the
settings, and there is no recovering them.

== Changelog ==

= 1.2.0 =
* Renamed to Helo, with a new menu icon.
* Email log is now a two-pane mail client view instead of a table plus a separate page.
* Existing settings and logs are adopted automatically on activation.

= 1.1.0 =
* Redesigned admin screens: cards, status summary, badges, toggles.
* Email preview now has a desktop/mobile width switcher.
* Self-hosted updates via the `Update URI` header.

= 1.0.0 =
* First release.
