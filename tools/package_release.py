"""Build a complete shared-hosting upload without private configuration."""
from pathlib import Path
import argparse
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'htdocs'
REQUIRED = {
    'index.php', '.htaccess', 'config/.htaccess', 'config/config.sample.php',
    'includes/bootstrap.php', 'includes/database.php', 'includes/functions.php',
    'includes/auth.php', 'includes/workspace.php', 'includes/layout.php',
    'includes/components.php', 'includes/seo.php', 'includes/ads.php',
    'assets/css/app.css', 'assets/css/admin.css', 'assets/js/app.js',
    'assets/js/admin.js', 'assets/js/editor.js', 'assets/js/autosave.js',
    'assets/js/ads.js', 'pages/home.php', 'ad-frame.php', 'robots.php', 'sitemap.php',
    'manage-8f3ffdb330600372/index.php', 'manage-8f3ffdb330600372/login.php',
    'keys/.htaccess',
}


def build(output: Path) -> int:
    files = {}
    for source in SOURCE.rglob('*'):
        if source.is_symlink():
            raise RuntimeError('Release sources must not contain symlinks')
        if not source.is_file():
            continue
        name = source.relative_to(SOURCE).as_posix()
        if name.startswith('config/') and name not in {'config/.htaccess', 'config/config.sample.php'}:
            continue
        if name.startswith('keys/') and name != 'keys/.htaccess':
            continue
        if name.startswith('.well-known/') or name == 'r.html':
            continue
        if source.suffix.lower() in {'.key', '.pem', '.p12', '.pfx'}:
            continue
        if source.name == '.env' or source.name.startswith('.env.') or '.git' in source.parts:
            continue
        # Existing deployments should not regain the one-time installer.
        if name == 'install.php':
            continue
        files[name] = source
    missing = REQUIRED - files.keys()
    if missing:
        raise RuntimeError('Incomplete upload package: ' + ', '.join(sorted(missing)))
    for source in files.values():
        if re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----', source.read_bytes()):
            raise RuntimeError('Private key material cannot be included in a release')
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = output.with_suffix(output.suffix + '.tmp')
    with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as archive:
        for name, source in sorted(files.items()):
            archive.write(source, name)
    with zipfile.ZipFile(temporary) as archive:
        names = set(archive.namelist())
        assert REQUIRED <= names
        assert 'htdocs/index.php' not in names
        assert 'config/config.php' not in names and 'config/config.local.php' not in names
        assert archive.read('index.php') == (SOURCE / 'index.php').read_bytes()
        assert archive.testzip() is None
    temporary.replace(output)
    return len(files)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'answered-design-update.zip')
    options = parser.parse_args()
    count = build(options.output)
    print(f'Created a complete upload with {count} files and index.php at the archive root.')
    print('Private configuration, certificate material, and the one-time installer are excluded.')
