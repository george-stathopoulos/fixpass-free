# Fixpass

Temporary, single-use support access for WordPress, with automatic context capture. This is the
version released on WordPress.org (plugin slug `fixpass`); `readme.txt` is the WordPress.org readme.

## Features

- Guided setup
- Automatic cleanup
- Support dashboard
- Copy support details
- Automatic context capture (Spotlight)
- Single-use links
- Temporary support access
- Multisite support and full in-plugin documentation

## Layout

| Path | What it does |
|---|---|
| `fixpass.php` | Plugin header and loader |
| `includes/class-access.php` | Support account, single-use link, expiry, cleanup, capability limits |
| `includes/class-spotlight.php` | Spotlight: stores picked spots and the context captured with them |
| `includes/class-details.php` | Builds the support details (HTML and plain text) |
| `includes/class-admin.php` | Guided setup and the support dashboard |
| `includes/class-docs.php` | The in-plugin documentation (Fixpass → Documentation) |
| `includes/class-network.php` | Multisite: the Network Admin overview |
| `includes/class-plugin.php` | Bootstrap, activation redirect, self-removal |
| `includes/class-redactor.php` | Removes secrets and email addresses from captured text |
| `assets/` | Plain JavaScript and CSS. There is no build step: what you see is what ships. |
| `uninstall.php` | Deletes the support account and everything Fixpass stored |

## Release

Zip the plugin without development files (see `.distignore`) into a folder named `fixpass`, and
upload it to the WordPress.org SVN `trunk`.

```
rsync -a --exclude-from=.distignore ./ build/fixpass/ && (cd build && zip -r fixpass.zip fixpass)
```

License: GPL-2.0-or-later.
