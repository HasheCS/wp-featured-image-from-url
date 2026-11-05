#!/usr/bin/env python3
"""Build deterministic independent and WordPress.org channel ZIPs.
Preserve existing installed basename featured-image-from-url/featured-image-from-url.php.
Run ONLY from reviewed candidate source; no GitHub publication here.
"""
import argparse
import hashlib
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INSTALLED_FOLDER = 'featured-image-from-url'
MAIN = 'featured-image-from-url.php'
CATALOG_SLUG = 'wp-featured-image-from-url'
UPDATE_URI = ' * Update URI: https://plugins.hashe.com/wp-featured-image-from-url\n'
ZIP_DATE = (2026, 11, 5, 0, 0, 0)

def version():
    code = (ROOT / MAIN).read_text(encoding='utf-8')
    match = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$', code, re.M)
    if not match:
        raise RuntimeError('main_header_not_stable_semver')
    current = match.group(1)
    if not re.search(r'^Stable tag: ' + re.escape(current) + r'$', (ROOT/'readme.txt').read_text(encoding='utf-8'), re.M):
        raise RuntimeError('readme_stable_version_mismatch')
    if 'Update URI:' in code:
        raise RuntimeError('source_header_must_be_channel_neutral')
    return current

def package_files(channel):
    if channel not in ('independent', 'wordpress-org'):
        raise RuntimeError('invalid_channel')
    main = (ROOT/MAIN).read_text(encoding='utf-8')
    if channel == 'independent':
        main = main.replace(' * Text Domain:', UPDATE_URI + ' * Text Domain:', 1)
        if UPDATE_URI not in main:
            raise RuntimeError('independent_update_uri_not_inserted')
    else:
        # WordPress.org requires gettext's domain to match its directory slug.
        # Keep the established 'fifu' domain untouched in the independent
        # edition: a channel-specific build must not rename users' plugin.
        if main.count(' * Text Domain: fifu') != 1 or main.count("'fifu'") < 1:
            raise RuntimeError('unexpected_existing_gettext_identity')
        main = main.replace(' * Text Domain: fifu', ' * Text Domain: featured-image-from-url', 1)
        main = main.replace("'fifu'", "'featured-image-from-url'")
        if "'fifu'" in main:
            raise RuntimeError('unconverted_wordpress_org_translation_domain')
    files = {INSTALLED_FOLDER+'/'+MAIN: main.encode('utf-8')}
    common = [
        'readme.txt','LICENSE.txt','assets/js/featured-image-from-url.js',
        'includes/hashe-core/v1/consent-client.php',
    ]
    if channel == 'independent':
        common.append('includes/hashe-core/v1/update-client.php')
    for path in common:
        files[INSTALLED_FOLDER+'/'+path] = (ROOT/path).read_bytes()
    return files

def make_zip(target, members):
    target.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(str(target), 'w', zipfile.ZIP_DEFLATED) as archive:
        for name, content in sorted(members.items()):
            info = zipfile.ZipInfo(name, ZIP_DATE)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, content)
    with zipfile.ZipFile(str(target)) as archive:
        if archive.testzip() is not None:
            raise RuntimeError('zip_integrity_failure')
        if not all(x.startswith(INSTALLED_FOLDER+'/') for x in archive.namelist()):
            raise RuntimeError('plugin_folder_mismatch')
    return hashlib.sha256(target.read_bytes()).hexdigest()

def build(output):
    version_text = version()
    folder = Path(output)
    independent = folder/(CATALOG_SLUG+'-v'+version_text+'.zip')
    wporg = folder/(INSTALLED_FOLDER+'-v'+version_text+'-wporg.zip')
    independent_sha = make_zip(independent,package_files('independent'))
    wporg_sha = make_zip(wporg,package_files('wordpress-org'))
    (folder/'INDEPENDENT-SHA256SUMS.txt').write_text(independent_sha+'  '+independent.name+'\n',encoding='ascii')
    (folder/'WPORG-SHA256SUMS.txt').write_text(wporg_sha+'  '+wporg.name+'\n',encoding='ascii')
    print('VERSION='+version_text)
    print('INDEPENDENT_SHA256='+independent_sha)
    print('WPORG_SHA256='+wporg_sha)
    print('INSTALLED_BASENAME='+INSTALLED_FOLDER+'/'+MAIN)
    return independent_sha, wporg_sha

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--output', default='dist')
    build(parser.parse_args().output)
