"""Audit tree yang akan dipublikasikan, tanpa mencetak nilai sensitif."""
from pathlib import Path
import json
import re
import sys
import struct
import hashlib

root = Path(__file__).resolve().parents[1]
failures = []
files = sorted(p for p in root.rglob('*') if p.is_file() and '.git' not in p.relative_to(root).parts)
text_extensions = {'.php', '.js', '.css', '.md', '.txt', '.sql', '.py', '.json', '.sha256'}
patterns = {
    'private-windows-path': r'(?i)(?:[A-Z]:[\\/](?:Users|laragon|portofolio)[\\/])',
    'hosting-account-id': r'\bif0_[0-9]+\b',
    'meta-token': r'\bEAA[A-Za-z0-9]{30,}\b',
    'github-token': r'\b(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{30,})\b',
    'cloud-key': r'\b(?:AKIA|ASIA)[A-Z0-9]{16}\b',
    'private-key': r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',
    'stored-password-hash': r'\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}|\$argon2(?:id|i)\$',
    'jwt-token': r'\beyJ[A-Za-z0-9_-]{15,}\.[A-Za-z0-9_-]{15,}\.[A-Za-z0-9_-]{15,}\b',
    'credential-in-url': r'(?i)(?:ftp|sftp|mysql|https?)://[^\s/:]+:[^\s/@]+@',
    'literal-credential': r'''(?im)["'](?:DB_PASS|DB_PASSWORD|WA_ACCESS_TOKEN|FTP_PASSWORD|API_KEY)["']\s*=>\s*["'][^"'\r\n]+["']''',
}
references = 0
allowed_binary = {'.png', '.ttf', '.dat'}
for p in files:
    rel = p.relative_to(root).as_posix()
    if p.suffix.lower() == '.png':
        data = p.read_bytes()
        pos = 8
        while pos + 12 <= len(data):
            size = struct.unpack('>I', data[pos:pos+4])[0]
            kind = data[pos+4:pos+8]
            if kind in {b'caBX', b'tEXt', b'iTXt', b'zTXt', b'eXIf'}:
                failures.append([rel, 'unreviewed-image-metadata'])
            pos += 12 + size
    if p.is_symlink():
        failures.append([rel, 'symlink'])
    if any(part in {'_development', '_deployment', 'backups', 'logs', 'sessions', '.aws', '.codex', '.agents'} for part in p.relative_to(root).parts):
        failures.append([rel, 'excluded-directory'])
    if p.name.lower() == 'local.php' or p.suffix.lower() in {'.zip', '.7z', '.rar', '.log', '.bak', '.env', '.csv', '.pdf'}:
        failures.append([rel, 'private-or-runtime-file'])
    if rel.startswith(('uploads/', 'storage/')) and p.name not in {'.htaccess', '.gitkeep'}:
        failures.append([rel, 'runtime-data'])
    if p.suffix.lower() not in text_extensions and p.name not in {'.htaccess', '.gitignore', '.gitkeep', '.gitattributes'}:
        if p.suffix.lower() not in allowed_binary:
            failures.append([rel, 'unreviewed-binary'])
        continue
    try:
        content = p.read_text(encoding='utf-8-sig')
    except UnicodeDecodeError:
        failures.append([rel, 'text-encoding'])
        continue
    for label, pattern in patterns.items():
        for match in re.finditer(pattern, content):
            failures.append([rel, label, content.count('\n', 0, match.start()) + 1])
    if rel not in {'includes/pdf/tfpdf.php', 'includes/pdf/font/unifont/ttfonts.php'}:
        contacts = re.findall(r'[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}', content)
        if any(not contact.endswith('@example.invalid') for contact in contacts):
            failures.append([rel, 'non-demo-email'])
    if re.search(r'(?<![0-9])(?:\+?62|0)8[0-9 ()-]{7,16}(?![0-9])', content):
        failures.append([rel, 'phone-candidate'])
    if p.suffix.lower() == '.sql':
        if rel != 'database/schema.sql' or re.search(r'(?im)^\s*(INSERT|REPLACE|DROP|TRUNCATE|UPDATE|DELETE|USE)\b', content):
            failures.append([rel, 'sql-data-or-mutation'])
    # Existing PHP includes use these literal forms. Runtime-selected fonts are
    # checked below separately; no PHP expressions are executed by this audit.
    if p.suffix == '.php':
        for match in re.finditer(r'''\b(?:require|include)(?:_once)?\s*(?:\(\s*)?(?:(__DIR__|dirname\(__DIR__\))\s*\.\s*)?["']([^"'\r\n]+\.php)["']''', content):
            prefix, target = match.groups()
            base = p.parent.parent if prefix == 'dirname(__DIR__)' else p.parent
            candidate = base / target.lstrip('/') if prefix else base / target
            references += 1
            if not candidate.is_file():
                failures.append([rel, 'missing-include', target])
    # Literal local HTML and Markdown references; ignore dynamic PHP values.
    targets = re.findall(r'''(?:src|href|action)\s*=\s*["']([^"']+)["']''', content, flags=re.I) if p.suffix == '.php' else []
    if p.suffix == '.md':
        targets += re.findall(r'\]\(([^)]+)\)', content)
    for target in targets:
        target = target.strip()
        if not target or target.startswith(('#', '?', 'http:', 'https:', 'mailto:', 'data:', 'javascript:')) or any(x in target for x in ['<?', '?>', '$', '{']):
            continue
        target = target.split('?', 1)[0].split('#', 1)[0]
        if not target:
            continue
        candidate = (root / target.lstrip('/')) if target.startswith('/') else p.parent / target
        references += 1
        if not candidate.exists():
            failures.append([rel, 'missing-local-reference', target])

required = ['.htaccess','config/.htaccess','config/database.php','config/example.local.php',
    'includes/.htaccess','includes/bootstrap.php','includes/security.php','includes/navbar.php',
    'includes/monthly_report.php','services/monthly_report_pdf.php',
    'includes/pdf/tfpdf.php','includes/pdf/COPYING.LESSER-2.1.txt',
    'includes/pdf/font/unifont/DejaVu_LICENSE.txt','includes/pdf/font/unifont/ttfonts.php',
    'database/schema.sql','bin/seed-demo.php','README.md','GITHUB_PUBLICATION_AUDIT.md',
    'uploads/.htaccess','storage/.htaccess']
required += [f'includes/pdf/font/unifont/{font}' for font in ['DejaVuSansCondensed.ttf','DejaVuSansCondensed-Bold.ttf',
    'dejavusanscondensed.mtx.php','dejavusanscondensed.cw.dat','dejavusanscondensed.cw127.php',
    'dejavusanscondensed-bold.mtx.php','dejavusanscondensed-bold.cw.dat','dejavusanscondensed-bold.cw127.php']]
required += [f'{role}/{file}.php' for role, names in {
    'admin':['dashboard','pengaduan','detail-pengaduan','pengguna','petugas','laporan-bulanan','unduh-laporan-bulanan'],
    'user':['dashboard','buat-pengaduan','riwayat'],
    'petugas':['dashboard','tugas','detail-tugas','riwayat','profil']}.items() for file in names]
required += [file+'.php' for file in ['index','login','logout','pengaduan','tracking','media']]
for rel in required:
    if not (root / rel).is_file():
        failures.append([rel, 'missing-core-file'])
manifest = root / 'PUBLICATION_MANIFEST.sha256'
if manifest.is_file():
    listed = set()
    for line in manifest.read_text(encoding='utf-8').splitlines():
        expected, rel = line.split('  ', 1)
        listed.add(rel)
        target = root / rel
        if not target.is_file() or hashlib.sha256(target.read_bytes()).hexdigest() != expected:
            failures.append([rel, 'manifest-hash-mismatch'])
    actual = {p.relative_to(root).as_posix() for p in files if p != manifest}
    if listed != actual:
        failures.append(['PUBLICATION_MANIFEST.sha256', 'manifest-file-list-mismatch'])
print(json.dumps({'files': len(files), 'literal_references_checked': references,
                  'required_files_checked': len(required), 'findings': failures}, ensure_ascii=False, indent=2))
sys.exit(bool(failures))
