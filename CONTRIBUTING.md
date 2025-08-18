# Contributing

Thanks for your interest in contributing!

## Development setup
- Standard WordPress plugin... no build step required.
- Main plugin file: `featured-image-from-url.php`
- JS file: `assets/js/featured-image-from-url.js`

## Branching
- `main` is stable.
- Feature work... `feat/<name>`; fixes... `fix/<name>`

## Releasing
- Update `Version:` header in `featured-image-from-url.php` and `readme.txt` stable tag.
- Tag a version: `git tag v1.0.1 && git push origin v1.0.1`
- GitHub Actions builds and attaches a ZIP to the release automatically.

## Code style
- PHP: match WP core standards (tabs, no short array syntax requirements).
- JS: small, dependency-free... keep compatibility with WP media views.

## Security
- Nonce check and capability check are required for AJAX endpoints.
- Avoid echoing unescaped values to HTML.
- Report issues privately if they may expose security concerns.
