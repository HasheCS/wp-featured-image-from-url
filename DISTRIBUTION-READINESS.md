# Featured Image v1.1.0 — dual-channel review candidate

**Not approved for merge, tag, publication, WordPress.org submission or telemetry activation.**

## Upgrade identity
Existing installation directory and main file: `featured-image-from-url/featured-image-from-url.php`.
Independent catalog slug: `wp-featured-image-from-url`.
The candidate ZIP must have the original **installed directory** even though the filename and Update URI use the catalog slug. Never silently create a second plugin during an upgrade.

## Required packages
* Independent: GitHub stable ZIP, preserved installed directory, first-party `Update URI`, approved-release feed, SHA256-bound native updater, and admin-only optional consent interface.
* WordPress.org: separately built ZIP, no independent update client / external executable updater, no third-party Update URI, and the same admin-only default-off consent choice (Skip without penalty, Allow only after reviewed service contract, Revoke).

Published v1.0.1 contains a PHP header version of 1.0.0. Never rewrite its published asset. Use a genuinely tested new v1.1.0 release with correct version/tag/readme/ZIP metadata.

Launch gates: actual WordPress GUI and update tests, current Plugin Check, explicit privacy and external-service disclosures, signed immutable release workflow and full website automatic mirroring. No telemetry by default.
