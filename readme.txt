=== Featured Image From URL ===
Contributors: hashe
Tags: media, featured image, upload, url, import
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Import an image URL into the real WordPress Media Library from Add Media, Featured Image or the Media Library screen.

== Description ==

Adds a From URL tab to WordPress's native media selection modal. Images are downloaded to the WordPress uploads directory, registered as normal attachments, and available for use throughout the website. No remote hotlinking.

Works with the standard Classic and Block editors, featured-image selection, and the Media Library screen. Restricts imports to logged-in users with upload permissions. Blocks unsafe URLs, limits downloads to 10 MB and validates actual image contents.

Supported formats: JPEG, PNG, GIF, WebP, and AVIF when supported by the WordPress installation.

== Installation ==

1. Back up the website and install the versioned WordPress plugin ZIP.
2. Activate Featured Image From URL under Plugins.
3. Open Add Media, Set featured image, or Media > Library > Import from URL.
4. Paste a direct image URL to import it into the Media Library.

== Changelog ==

= 1.1.0-rc.1 (testing candidate) =
* Add From URL to the general media picker and Media Library screen.
* Preserve one-click import and Featured Image assignment.
* Create and select real Media Library attachments, not remote hotlinks.
* Harden server-side import handling and capability checks.
* Remove obsolete demo video link from public documentation.

= 1.0.1 =
* Prior featured-image-only release.
