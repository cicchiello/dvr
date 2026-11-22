#!/usr/bin/env python3
"""
Rebuild broken library symlinks from CouchDB compressed-recordings data.

After NTFS -> ext4 migration, library symlinks have the target:
  "unsupported reparse tag 0xa000000c"

This script deletes those and recreates them as:
  library/<cleanDescription>.<record-start>.mp4 -> ../compressed/<doc_id>.mp4

Usage:
  python3 rebuild-library-symlinks.py -ini <ini-file> [-mode {prod|dev}] [-dry-run]
"""

import sys
import os
import json
import configparser
import urllib.request
import urllib.error

BROKEN_TARGET = "unsupported reparse tag 0xa000000c"


def usage():
    print("Usage: %s -ini <ini-file> [-mode {prod|dev}] [-dry-run]" % sys.argv[0])
    sys.exit(1)


def clean_description(d):
    legal = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-., '
    return ''.join(c if c in legal else 'X' for c in d)


def unique_basename(desc, ts):
    return "%s.%d.mp4" % (clean_description(desc), ts)


def couch_get(url, user=None, pswd=None):
    req = urllib.request.Request(url)
    if user:
        import base64
        creds = base64.b64encode(('%s:%s' % (user, pswd)).encode()).decode()
        req.add_header('Authorization', 'Basic ' + creds)
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read())


def main():
    ini_file = None
    mode = 'prod'
    dry_run = False

    args = sys.argv[1:]
    i = 0
    while i < len(args):
        if args[i] == '-ini' and i + 1 < len(args):
            ini_file = args[i + 1]; i += 2
        elif args[i] == '-mode' and i + 1 < len(args):
            mode = args[i + 1]; i += 2
        elif args[i] == '-dry-run':
            dry_run = True; i += 1
        else:
            usage()

    if not ini_file:
        usage()

    if not os.path.isfile(ini_file):
        print("ERROR: ini file not found: %s" % ini_file)
        sys.exit(1)

    config = configparser.ConfigParser()
    config.read(ini_file)

    if not config.has_section(mode):
        print("ERROR: no [%s] section in %s" % (mode, ini_file))
        sys.exit(1)

    db_base = config.get(mode, 'DbBase')
    db_name = config.get(mode, 'Db')
    db_user = config.get(mode, 'DbKey')
    db_pswd = config.get(mode, 'DbPswd')
    fs_root = config.get(mode, 'WebFsRoot')

    library_dir = os.path.join(fs_root, 'library')
    library_dir = os.path.realpath(library_dir)

    print("INFO: CouchDB: %s/%s" % (db_base, db_name))
    print("INFO: library: %s" % library_dir)
    if dry_run:
        print("INFO: dry-run mode — no changes will be made")

    # fetch all compressed recordings
    view_url = "%s/%s/_design/dvr/_view/compressed-recordings?include_docs=false" % (db_base, db_name)
    print("INFO: querying %s" % view_url)
    try:
        result = couch_get(view_url, db_user, db_pswd)
    except urllib.error.URLError as e:
        print("ERROR: CouchDB query failed: %s" % e)
        sys.exit(1)

    rows = result.get('rows', [])
    print("INFO: found %d compressed recordings" % len(rows))

    fixed = 0
    skipped = 0
    missing_file = 0

    for row in rows:
        doc = row['value']
        doc_id = row['id']
        description = doc.get('description', '')
        record_start = doc.get('record-start', 0)

        basename = unique_basename(description, record_start)
        symlink_path = os.path.join(library_dir, basename)
        target = "../compressed/%s.mp4" % doc_id
        compressed_path = os.path.join(library_dir, '..', 'compressed', '%s.mp4' % doc_id)
        compressed_path = os.path.realpath(compressed_path)

        if not os.path.isfile(compressed_path):
            print("WARN: compressed file missing, skipping: %s" % compressed_path)
            missing_file += 1
            continue

        # check current symlink state
        if os.path.islink(symlink_path):
            current_target = os.readlink(symlink_path)
            if current_target == BROKEN_TARGET:
                if not dry_run:
                    os.remove(symlink_path)
                    os.symlink(target, symlink_path)
                print("FIXED: %s -> %s" % (basename, target))
                fixed += 1
            elif current_target == target:
                skipped += 1  # already correct
            else:
                print("WARN: unexpected target, leaving alone: %s -> %s" % (basename, current_target))
                skipped += 1
        elif not os.path.exists(symlink_path):
            if not dry_run:
                os.symlink(target, symlink_path)
            print("CREATED: %s -> %s" % (basename, target))
            fixed += 1
        else:
            print("WARN: exists but is not a symlink, skipping: %s" % symlink_path)
            skipped += 1

    print("")
    print("DONE: fixed=%d  skipped=%d  missing_compressed_file=%d" % (fixed, skipped, missing_file))
    if dry_run:
        print("(dry-run — no changes were made)")


if __name__ == '__main__':
    main()
