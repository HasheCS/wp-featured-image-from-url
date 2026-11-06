# Featured Image from URL: first adoption of native WordPress updates

**Release scope:** independent Hashe/GitHub **v1.0.1 → v1.1.0**, after the new stable release has passed approval and been publicly published. This document does **not** approve or publish that release.

## Why a one-time manual upgrade is required

The currently distributed v1.0.1 independent plugin ZIP predates the new Hashe native WordPress update-client bootstrap. An old installation **cannot be relied upon** to discover its very first upgrade automatically. Once the verified v1.1.0 independent edition has been installed in the original location, future approved stable versions can be offered through WordPress's built-in update interface, using the Hashe metadata feed and SHA-256-verified immutable GitHub assets. Optional Hashe installation reporting is never needed for updates.

## Controlled first adoption (WP administrator)

1. Check that a new **stable** v1.1.0 release and asset `wp-featured-image-from-url-v1.1.0.zip` actually exist at https://github.com/HasheCS/wp-featured-image-from-url/releases. Do **not** use GitHub's automatically generated Source code ZIP, a PR build, a development ZIP, or a WordPress.org-specific ZIP.
2. Verify the published asset's SHA-256 against its release checksum file and the independent update metadata from https://plugins.hashe.com/. A matching version name alone is insufficient; abort if the two sources disagree.
3. Take and independently verify a WordPress files **and database** backup, with a tested restoration path. First perform this upgrade on staging and document current functional behavior. Check WordPress 5.8+, PHP 7.4+.
4. WordPress Admin > Plugins > Add New Plugin > Upload Plugin: upload `wp-featured-image-from-url-v1.1.0.zip`. When WordPress identifies the already installed plugin, choose **Replace current with uploaded** (available on supported current WordPress installations). Preserve the original folder and main-file identity: `featured-image-from-url/featured-image-from-url.php`. Do not uninstall the prior plugin first merely to upgrade, or move it to a different slug.
5. Verify the plugin is active and displays version **v1.1.0**, existing settings/content remain intact, and there are no new PHP/WordPress errors. Existing historical GitHub release v1.0.1 has a known archive/header discrepancy: its package header reports v1.0.0. Do not modify the historical ZIP or infer that it can self-update. Upload the reviewed v1.1.0 independent package once, preserving the existing `featured-image-from-url/` folder and `featured-image-from-url.php` main filename. Check current featured thumbnails and media attachments before and after. The WordPress.org channel is a separate package and updater provider.
6. Confirm that native update checks are served through the **independent Hashe channel** for later approved versions (WordPress's normal Update now workflow), and that installation-reporting consent remains off unless independently and explicitly granted.
7. Reconcile the before/after baseline and record the actual installed ZIP SHA-256, time, site, result and recovery reference. Do not infer successful adoption from a website handoff/redirect alone.

## Abort and rollback

If the ZIP hash, folder identity, compatibility, install/update result or essential functionality fails, **stop**. Restore the exact prior verified plugin folder, configuration and database state from the tested site backup as appropriate; never replace the historic published ZIP in GitHub. Do not automatically revert unrelated site changes made after the backup. Verify WordPress admin, active plugins and the affected page features after recovery, and report the incident without hidden telemetry.

**Channel invariant:** Featured Image from URL independent updates originate from the published approved Hashe/GitHub release and the Hashe update feed, not from an unreviewed branch or WordPress.org ZIP. The first-adoption procedure does not publish or approve anything.
