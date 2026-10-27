=== Fixpass ===
Contributors: mindanticipation
Tags: support, temporary login, remote access, troubleshooting, single-use link
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give your support team safe, temporary access to your site with a single-use login link, and everything they need to start fixing.

== Description ==

Something's wrong with your site and you need help. Usually that means a long email thread: sharing a password, then answering "which WordPress version?", "which plugins?" and "what PHP are you on?".

Fixpass does it in a minute.

* **Guided setup.** Three steps: get started, give access, copy the details. No settings to hunt for.
* **Temporary support access.** One click creates a temporary administrator account for your support team. Choose 1 day to 1 week. There is no password: support logs in with a link.
* **Single-use links.** The login link works once. If support needs to come back, make a new link and the old one stops working. Email scanners that open links can't use it up: it is only used when support presses the login button.
* **Automatic context capture.** Fixpass reads your site's details for you: WordPress, PHP and database versions, configuration, Site Health results, every plugin and theme with versions and waiting updates, must-use plugins and drop-ins. You just add a sentence about what's wrong.
* **Copy support details.** Copy the full message (login link, your description and the captured details, formatted for email and as plain text) or just the login link. You can open a preview of exactly what the full message contains before you send it.
* **Support dashboard.** After logging in, support lands on a Support session page with your description and the site details in sections that open and close, and a button to end access.
* **Automatic cleanup.** When access ends, by time or because you or support ended it, the support account and its sessions are deleted. Optionally, Fixpass also deactivates and deletes itself, with everything it stored.

= Multisite =

Fixpass works on multisite networks. Each site gives and ends its own support access, and the support account is an administrator of that one site only (never a super admin). Super admins get a Fixpass overview in the Network Admin: see every site with support access, end it with one click, and choose whether site administrators may give access. Deactivating or deleting Fixpass for the network ends access and cleans up on every site.

= Documentation =

Full documentation is built in: open Fixpass → Documentation in your admin. It covers the setup, automatic context capture, support access, single-use links, cleanup, security and privacy, multisite, troubleshooting and developer notes.

= Security =

* Login links are random 256-bit codes; only a hash is stored.
* Password login is switched off for support accounts.
* Support can't manage users, install or delete themes, or remove Fixpass.
* However access ends, the support account is deleted.
* You get an email when support logs in.
* Failed link attempts are rate-limited.

= Privacy =

Fixpass doesn't send data to any external service and doesn't load anything from outside your site. The details leave your site only when you copy them and send them yourself. Passwords, keys, users, content and customer data are never included.

Fixpass stores only the access itself and your description of the problem, and deletes both when access ends. It sends one email, to the site's administration email address, when support logs in; it includes the IP address support logged in from.

== Installation ==

1. Install and activate Fixpass from Plugins → Add New, or upload the zip.
2. Fixpass opens its guided setup. Click Start.
3. Describe the problem, choose how long access lasts, and click "Give access".
4. Click "Copy support details" and send them to your support team.

== Frequently Asked Questions ==

= Do I need an account anywhere? =

No. Fixpass runs entirely on your site.

= Who is "support"? =

Whoever helps you with your site: a developer, an agency, a plugin or theme vendor.

= What can support do? =

Support gets an administrator account, so they can fix problems. They cannot manage users, install or delete themes, or remove Fixpass.

= What if the link doesn't work for support? =

Links work once. Open Fixpass, choose "Copy support details with a new link", and send the new details.

= Does it work on multisite? =

Yes. Activate it for the network or for single sites. See the Multisite section above.

= Will visitors notice anything? =

No. Fixpass shows nothing to visitors.

= What does uninstalling remove? =

Any support account, and everything Fixpass stored: the access, your description, and its scheduled tasks. Fixpass creates no database tables.

== Changelog ==

= 1.0.0 =
* First release.
