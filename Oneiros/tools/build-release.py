"""Build the cPanel release ZIP from an explicit allowlist.

Configuration (includes/config.php, includes/config.local.php), user uploads, logs,
tests and development tools are never included, so the ZIP can be extracted over a
live site without touching its settings or its dreamers' files.

Usage: python tools/build-release.py
"""
import hashlib
import sys
import time
import zipfile
from pathlib import Path

VERSION = '2.1.0'
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'dist' / f'oneiros-v{VERSION}-cpanel.zip'

ROOT_FILES = [
    '.htaccess', 'api.php', 'index.php', 'manifest.json', 'sw.js', 'robots.txt', 'sitemap.php',
    'oneiros.php', 'oneiros.html', 'oneiros-admin.php', 'oneiros-admin.html',
    'oneiros-moderation.php', 'oneiros-moderation.html', 'README.md', 'DEPLOY-AFRIHOST.md',
]
TREES = {  # folder: allowed file suffixes
    'api': {'.php'},
    'assets': {'.css', '.js', '.svg', '.png', '.webp'},
    'includes': {'.php', '.htaccess'},
    'sql': {'.sql'},
}
EXTRA_FILES = ['tools/console.php', 'uploads/.htaccess']
EMPTY_DIRS = ['uploads/dreams/', 'uploads/audio/', 'uploads/paintings/']
NEVER = {'includes/config.php', 'includes/config.local.php', 'assets/dreamscape-master.png'}


def collect():
    files = [ROOT / name for name in ROOT_FILES + EXTRA_FILES]
    for folder, suffixes in TREES.items():
        for path in sorted((ROOT / folder).rglob('*')):
            if path.is_file() and (path.suffix in suffixes or path.name in suffixes):
                files.append(path)
    chosen = []
    for path in files:
        rel = path.relative_to(ROOT).as_posix()
        if rel in NEVER or path.name == 'error_log':
            continue
        if not path.is_file():
            sys.exit(f'Missing release file: {rel}')
        chosen.append((rel, path))
    return sorted(set(chosen))


def main():
    OUT.parent.mkdir(exist_ok=True)
    entries = collect()
    stamp = time.localtime()[:6]
    with zipfile.ZipFile(OUT, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for rel in EMPTY_DIRS:
            info = zipfile.ZipInfo(rel, stamp)
            info.external_attr = (0o40755 << 16) | 0x10
            zf.writestr(info, '')
        for rel, path in entries:
            info = zipfile.ZipInfo(rel, stamp)
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            zf.writestr(info, path.read_bytes())
    names = zipfile.ZipFile(OUT).namelist()
    leaked = [n for n in names if n in NEVER or n.endswith('error_log') or n.startswith(('tests/', 'dist/'))]
    if leaked:
        OUT.unlink()
        sys.exit(f'Refusing to ship private or development files: {leaked}')
    digest = hashlib.sha256(OUT.read_bytes()).hexdigest()
    print(f'{OUT.relative_to(ROOT)}  {len(names)} entries  {OUT.stat().st_size / 1024:.0f} KB')
    print(f'sha256 {digest}')


if __name__ == '__main__':
    main()
