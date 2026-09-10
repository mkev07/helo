=== Helo — SMTP & Mail Log ===
Contributors: kevin
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

The `Update URI` header in the main plugin file points at a JSON manifest you
host yourself. WordPress polls it on its normal update schedule and shows the
update on the Plugins screen like any other. See "Updates" below.

== Updates ==

Updates use the `Update URI` header and the `update_plugins_{$hostname}` filter
that WordPress 5.8 added for self-hosted plugins. No update library, no
wordpress.org listing.

The source can live in a private repository; only two files need to be publicly
readable, and neither of them is the source:

* `update.json` — the manifest WordPress polls
* `helo-smtp-X.Y.Z.zip` — the package it downloads

= Setup =

**1. Create a public prefix in the bucket.** Everything else stays private.
With AWS, keep Block Public Access on for the bucket and grant read on the one
prefix instead:

`{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::YOUR-BUCKET/plugins/helo-smtp/*"
  }]
}`

**2. Fill in `build.sh`** — `BASE_URL`, `S3_URI`, and `S3_ENDPOINT` (empty for
AWS, the endpoint host for Hetzner and other S3-compatible providers).

**3. Point the plugin header at the manifest**, in `helo-smtp.php`:

`Update URI: https://YOUR-BUCKET.s3.REGION.amazonaws.com/plugins/helo-smtp/update.json`

It must match `BASE_URL/update.json` exactly. `build.sh` refuses to run if the
two disagree, because a mismatch kills the update channel silently.

= Releasing =

1. Bump `Version:` in the plugin header, and add a `changelog` entry in `update.json`.
2. `./build.sh --publish`

That builds `helo-smtp-X.Y.Z.zip`, rewrites `update.json` to match
the header version, and uploads both with the right content types and cache
headers. Commit and tag afterwards — the repository keeps the history, the
bucket does the distribution.

= Notes =

* The download URL must be HTTPS. The plugin refuses plain HTTP, because an update package is executable code.
* The folder *inside* the zip must stay `helo-smtp`; the zip's own filename carries the version. `build.sh` gets both right.
* `update.json` is uploaded with a 5 minute cache and the versioned zips with a 1 year immutable cache. Sites cache the manifest for a further 6 hours; **Helo → Settings → Check for updates** clears that and re-checks immediately.
* Anyone who knows the URL can download the package. A private repository hides the history and work in progress, not the released code — presigned URLs expire, so they are not usable as a long-lived update channel.
* Rolling back means re-uploading `update.json` with the older version and download URL. The old zips are still there.

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
