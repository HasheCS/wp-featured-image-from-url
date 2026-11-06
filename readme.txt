=== Featured Image From URL ===
Contributors: hashe
Tags: media, featured image, upload, url, import
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.1.0
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

== External services ==

When a user explicitly imports an image from a URL, this plugin retrieves that image through the WordPress secure HTTP API; the named third-party image host receives the request. No remote images are hotlinked on pages.

Independent releases check the Hashe approved-release feed for native WordPress updates without sending optional installation analytics. WordPress.org packages rely on WordPress.org updates. Following activation, site administrators can Skip optional reports. The Allow option remains unavailable until a separately approved, documented security/retention contract is deployed; any future Allow requires affirmative consent and supports revocation. See https://plugins.hashe.com/privacy.html.

== Changelog ==

= 1.1.0 =
* Preserve the current WordPress install basename and imported Media Library features.
* Prepare dedicated independent and WordPress.org packages and safe native update handling.
* Include a default-off post-activation consent client; no reporting activated.


= 1.1.0-rc.1 (testing candidate) =
* Add From URL to the general media picker and Media Library screen.
* Preserve one-click import and Featured Image assignment.
* Create and select real Media Library attachments, not remote hotlinks.
* Harden server-side import handling and capability checks.
* Remove obsolete demo video link from public documentation.

= 1.0.1 =
* Prior featured-image-only release.
