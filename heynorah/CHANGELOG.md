# Changelog

## 2.0.3 - 2026-06-18

- Updated static analysis to use WordPress 7.0 stubs directly instead of the older phpstan-wordpress extension.
- Added PHPStan deprecation rules so deprecated WordPress API usage fails analysis.
- Marked the public distribution as tested with WordPress 7.0.
- Added WordPress and PHP requirement headers to the plugin entry file.
- Updated script enqueue calls to use the current WordPress `$args` array format.

## 2.0.2 - 2026-06-18

- Fixed WordPress update discovery for HeyNorah installs that use the `Update URI` plugin header.
- Added the WordPress `update_plugins_github.com` update hook so WordPress 5.8+ can discover public HeyNorah releases correctly.
- Bypassed stale manifest cache during update checks and plugin details requests.
- Added release notes to the WordPress version details modal before updating.

## 2.0.1 - 2026-06-18

- Published the first public distribution package with an installable ZIP and update manifest.
