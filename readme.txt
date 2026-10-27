=== Fixpass ===
Contributors: mindanticipation
Tags: support, temporary login, remote access, troubleshooting, single-use link
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give your support team safe, temporary access to your site with a single-use login link, and show them the exact spot that's broken.

== Description ==

Something's wrong with your site and you need help. Usually that means a long email thread: sharing a password, sending screenshots, answering "which page?" and "which browser?".

Fixpass does it in a minute.

* **Guided setup.** A short walkthrough: choose how to start, point at the problem, give access, copy the details. No settings to hunt for.
* **Automatic context capture.** Click "Spotlight a problem" in the toolbar, then click the broken part of the page or drag a box around it. Add a note. Fixpass captures the page, your screen size, your browser, and the JavaScript errors and failed requests on that page. Nothing on your screen is recorded.
* **Temporary support access.** One click creates a temporary administrator account for your support team. Choose 1 day to 1 week. There is no password: support logs in with a link.
* **Single-use links.** The login link works once. If support needs to come back, make a new link and the old one stops working. Email scanners that open links can't use it up: it is only used when support presses the login button.
* **Copy support details.** One button copies the login link, your spots, site information, and your plugins and themes (with versions and available updates), ready to paste into an email or ticket.
* **Support dashboard.** After logging in, support lands on a Support session page with your spots (each one opens the page highlighted), the site information, and a button to end access.
* **Automatic cleanup.** When access ends, by time or because you or support ended it, the support account and its sessions are deleted. Optionally, Fixpass also deactivates and deletes itself, with everything it stored.

= Security =

* Login links are random 256-bit codes; only a hash is stored.
* Password login is switched off for support accounts.
* Support can't manage users, install or delete themes, or remove Fixpass.
* However access ends, the support account is deleted.
* You get an email when support logs in.
* Failed link attempts are rate-limited.

= Privacy =

Fixpass doesn't send data to any external service and doesn't load anything from outside your site. The details (login link, spots, site information, plugins and themes) leave your site only when you copy them and send them yourself.

A spot keeps the page address, your note, your screen size and browser, and the errors and failed requests on that page. Spots are deleted when access ends, or after 30 days if you never give access. Passwords, keys and email addresses are removed from the captured errors and requests.

== Installation ==

1. Install and activate Fixpass from Plugins → Add New, or upload the zip.
2. Fixpass opens its guided setup.
3. Choose "Show the problem, then give access" (recommended) or "Just give access", then copy the details and send them to your support team.

== Frequently Asked Questions ==

= Do I need an account anywhere? =

No. Fixpass runs entirely on your site.

= Who is "support"? =

Whoever helps you with your site: a developer, an agency, a plugin or theme vendor.

= What can support do? =

Support gets an administrator account, so they can fix problems. They cannot manage users, install or delete themes, or remove Fixpass.

= What if the link doesn't work for support? =

Links work once. Open Fixpass, choose "Copy support details with a new link", and send the new details.

= Will visitors notice anything? =

No. Spotlight is only shown to administrators.

= What does uninstalling remove? =

Any support account, and everything Fixpass stored: its settings, spots and scheduled tasks.

== Changelog ==

= 1.0.0 =
* First release.
