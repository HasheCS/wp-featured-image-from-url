#!/usr/bin/env python3
"""Build a disposable future-version WordPress native-upgrade fixture.

Never writes to a release asset or a production distribution directory.
Only modifies the WordPress Version header inside an isolated ZIP.
"""
import argparse
import io
import zipfile
from pathlib import Path

ENTRY = 'featured-image-from-url/featured-image-from-url.php'
START = b' * Version: 1.1.0\n'
FUTURE = b' * Version: 1.1.1\n'

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('source_zip', type=Path)
    parser.add_argument('target_zip', type=Path)
    args = parser.parse_args()
    if args.target_zip.exists():
        raise RuntimeError('refuse_to_overwrite_fixture')
    if not args.source_zip.is_file():
        raise RuntimeError('source_zip_missing')
    with zipfile.ZipFile(args.source_zip) as source:
        if source.testzip() is not None:
            raise RuntimeError('invalid_original_package')
        members = {name: source.read(name) for name in source.namelist()}
    if ENTRY not in members or members[ENTRY].count(START) != 1:
        raise RuntimeError('expected_source_plugin_header_not_found_once')
    members[ENTRY] = members[ENTRY].replace(START, FUTURE, 1)
    args.target_zip.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(args.target_zip, 'x', zipfile.ZIP_DEFLATED) as target:
        for name, data in sorted(members.items()):
            if not name.startswith('featured-image-from-url/') or '..' in Path(name).parts:
                raise RuntimeError('unexpected_or_unsafe_plugin_path')
            target.writestr(name, data)
    with zipfile.ZipFile(args.target_zip) as result:
        if result.testzip() is not None or result.read(ENTRY).count(FUTURE) != 1:
            raise RuntimeError('synthetic_package_verification_failed')
    print('FEATURED_NATIVE_UPDATER_DISPOSABLE_FIXTURE=PASS')

if __name__ == '__main__':
    main()
