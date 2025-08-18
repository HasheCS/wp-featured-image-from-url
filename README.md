# Featured Image From URL

Adds a **From URL** tab to the WordPress media modal so editors can paste an image URL, fetch it, and set it as the featured image for the current post.

## Features
- New media modal tab titled **From URL**
- Fetches and sideloads any direct image URL into the Media Library
- Automatically sets the fetched image as the Featured Image
- Returns refreshed Featured Image metabox, no page reload

## Requirements
- WordPress 5.8+
- User capability `upload_files`

## Install
1. Download the ZIP from releases.
2. In wp-admin go to Plugins → Add New → Upload Plugin and upload the ZIP.
3. Activate the plugin.
4. Open any post or page and click **Set featured image**. You will see the **From URL** tab in the media modal.

## Development
- Main plugin file: `featured-image-from-url.php`
- Script: `assets/js/featured-image-from-url.js`

## Security
- Protected by a nonce and `upload_files` capability check.
- Uses core `media_sideload_image` to download and attach images.

## License
MIT
