"""Release boundary and failure recovery checks using disposable fixtures."""
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import zipfile

spec = importlib.util.spec_from_file_location('release', Path(__file__).resolve().parents[1] / 'tools/package_release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        self.source = self.root / 'htdocs'
        self.source.mkdir()
        self.write('index.php', '<?php echo "Hello";')
        self.output = self.root / 'release.zip'
        for attribute, value in [('SOURCE', self.source), ('REQUIRED', {'index.php'})]:
            patcher = patch.object(release, attribute, value)
            patcher.start()
            self.addCleanup(patcher.stop)

    def write(self, name, content='fixture'):
        target = self.source / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')

    def test_excludes_private_files_hidden_directories_and_backups(self):
        for name in ['config/config.php', 'config/config.local.php', 'keys/ca.key', 'keys/privatekey.txt',
                     '.env', '.cache/debug.php', 'assets/.cache.php', 'backup.zip', 'index.php.bak', 'debug.log', 'install.php']:
            self.write(name)
        for name in ['config/.htaccess', 'config/config.sample.php', 'keys/.htaccess', 'assets/css/app.css']:
            self.write(name)
        self.assertEqual(release.build(self.output), 5)
        with zipfile.ZipFile(self.output) as archive:
            self.assertEqual(set(archive.namelist()), {'index.php', 'config/.htaccess', 'config/config.sample.php', 'keys/.htaccess', 'assets/css/app.css'})

    def test_rejects_output_inside_document_root(self):
        with self.assertRaises(ValueError):
            release.build(self.source / 'release.zip')

    def test_missing_files_preserve_previous_archive(self):
        self.output.write_bytes(b'previous release')
        with patch.object(release, 'REQUIRED', {'index.php', 'missing.php'}), self.assertRaises(RuntimeError):
            release.build(self.output)
        self.assertEqual(self.output.read_bytes(), b'previous release')

    def test_private_key_content_cannot_hide_in_public_assets(self):
        self.write('assets/key.php', '-----BEGIN PRIVATE KEY-----')
        with self.assertRaises(RuntimeError):
            release.build(self.output)
        self.assertFalse(self.output.exists())

    def test_failed_write_preserves_release_and_removes_temporary_archive(self):
        self.output.write_bytes(b'previous release')
        with patch.object(zipfile.ZipFile, 'write', side_effect=OSError('disk full')), self.assertRaises(OSError):
            release.build(self.output)
        self.assertEqual(self.output.read_bytes(), b'previous release')
        self.assertEqual(list(self.root.glob('*.tmp')), [])


if __name__ == '__main__':
    unittest.main()
