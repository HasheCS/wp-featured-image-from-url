#!/usr/bin/env python3
"""Standalone dual-channel and identity checks; never contacts installation sites."""
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools'))
from build_distributions import INSTALLED_FOLDER, MAIN, version, package_files

v=version()
expected=INSTALLED_FOLDER+'/'+MAIN
for channel in ('independent','wordpress-org'):
    files=package_files(channel)
    if expected not in files:
        raise RuntimeError('installed_basename_mismatch')
    header=files[expected].decode('utf-8')
    if 'Version: '+v not in header:
        raise RuntimeError('tag_version_header_mismatch')
    if 'Hashe\\FeaturedImage\\PluginTools\\V1\\ConsentClient' not in header:
        raise RuntimeError('consent_client_not_registered')
    if not any(name.endswith('/consent-client.php') for name in files):
        raise RuntimeError('consent_client_not_packaged')
    if channel=='independent':
        if 'Text Domain: fifu' not in header:
            raise RuntimeError('independent_gettext_identity_changed')
        if 'Update URI: https://plugins.hashe.com/wp-featured-image-from-url' not in header:
            raise RuntimeError('independent_update_uri_missing')
        if not any(name.endswith('/update-client.php') for name in files):
            raise RuntimeError('independent_updater_missing')
    else:
        if 'Text Domain: featured-image-from-url' not in header or "'fifu'" in header:
            raise RuntimeError('wporg_gettext_domain_mismatch')
        if 'Update URI:' in header or any(name.endswith('/update-client.php') for name in files):
            raise RuntimeError('wporg_external_updater_forbidden')
    print('PASS_CHANNEL_'+channel.upper().replace('-','_'))
print('PASS_INSTALLED_BASENAME='+expected)
print('DISTRIBUTION_QA=PASS')
