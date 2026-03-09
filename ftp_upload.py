#!/usr/bin/env python3
"""FTP upload script for CRM deployment to Cafe24"""
import ftplib
import os
import sys

FTP_HOST = 'codelabsy.mycafe24.com'
FTP_USER = 'codelabsy'
FTP_PASS = 'Rhffla00!@'
LOCAL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'ftp')
REMOTE_DIR = '/www/jsh'

def ensure_remote_dir(ftp, path):
    """Recursively create remote directories"""
    dirs = path.split('/')
    current = ''
    for d in dirs:
        if not d:
            continue
        current += '/' + d
        try:
            ftp.cwd(current)
        except ftplib.error_perm:
            try:
                ftp.mkd(current)
                print(f'  Created dir: {current}')
            except ftplib.error_perm:
                pass

SKIP_FILES = {'crm.sqlite', 'crm.sqlite.bak'}

def upload_directory(ftp, local_path, remote_path):
    """Upload all files from local_path to remote_path"""
    uploaded = 0
    skipped = 0

    for root, dirs, files in os.walk(local_path):
        # Calculate relative path
        rel_path = os.path.relpath(root, local_path)
        if rel_path == '.':
            current_remote = remote_path
        else:
            current_remote = remote_path + '/' + rel_path.replace(os.sep, '/')

        # Ensure remote directory exists
        ensure_remote_dir(ftp, current_remote)

        for filename in files:
            if filename in SKIP_FILES:
                print(f'  SKIP: {filename} (DB 파일 제외)')
                skipped += 1
                continue
            local_file = os.path.join(root, filename)
            remote_file = current_remote + '/' + filename
            file_size = os.path.getsize(local_file)

            try:
                with open(local_file, 'rb') as f:
                    ftp.storbinary(f'STOR {remote_file}', f)
                uploaded += 1
                size_str = f'{file_size/1024:.1f}KB' if file_size < 1024*1024 else f'{file_size/1024/1024:.1f}MB'
                print(f'  [{uploaded}] {remote_file} ({size_str})')
            except Exception as e:
                print(f'  ERROR: {remote_file} - {e}')
                skipped += 1

    return uploaded, skipped

def main():
    print(f'Connecting to {FTP_HOST}...')
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print(f'Connected. Current dir: {ftp.pwd()}')

    # Count files to upload
    total_files = sum(len(files) for _, _, files in os.walk(LOCAL_DIR))
    print(f'\nUploading {total_files} files to {REMOTE_DIR}...\n')

    uploaded, skipped = upload_directory(ftp, LOCAL_DIR, REMOTE_DIR)

    print(f'\n=== Upload Complete ===')
    print(f'Uploaded: {uploaded} files')
    print(f'Skipped/Error: {skipped} files')

    # Verify
    print(f'\nVerifying remote directory...')
    ftp.cwd(REMOTE_DIR)
    items = []
    ftp.dir(items.append)
    print(f'Files/dirs in {REMOTE_DIR}:')
    for item in items:
        print(f'  {item}')

    ftp.quit()
    print('\nDone!')

if __name__ == '__main__':
    main()
