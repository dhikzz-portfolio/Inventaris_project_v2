import re
import glob

# Seeder account map: username -> email (from DemoAccountSeeder)
EMAIL_MAP = {
    'wakasek': 'wakasek@gmail.com',
    'ketua.laboran': 'ketualaboran@gmail.com',
    'laboran.rpl': 'laboran.rpl@gmail.com',
    'laboran.dkv': 'laboran.dkv@gmail.com',
    'laboran.kln': 'laboran.kln@gmail.com',
    'laboran.tp': 'laboran.tp@gmail.com',
    'laboran.tkp': 'laboran.tkp@gmail.com',
    'guru.budi': 'guru.budi@gmail.com',
    'guru.sari': 'guru.sari@gmail.com',
    'siswa.raka': 'siswa.raka@gmail.com',
    'siswa.nadia': 'siswa.nadia@gmail.com',
    'staf.tata': 'staf.tata@gmail.com',
    'staf.perpus': 'staf.perpus@gmail.com',
}

files = glob.glob('tests/**/*.php', recursive=True)
for path in files:
    with open(path, encoding='utf-8') as f:
        src = f.read()
    orig = src

    # 1) User::where('username', 'known') -> User::where('email', 'mapped')
    def repl_where(m):
        uname = m.group(1)
        email = EMAIL_MAP.get(uname, f'{uname}@gmail.com')
        return f"User::where('email', '{email}')"

    src = re.sub(r"User::where\('username', '([a-z0-9._]+)'\)", repl_where, src)

    # 2) Login payloads: 'username' => 'known.user',  -> 'email' => ... (only inside post /login blocks handled manually later; do generic mapping here for known users)
    # Replace 'username' => 'X' where X is a known seeder user AND line contains only that
    for uname, email in EMAIL_MAP.items():
        src = src.replace(f"'username' => '{uname}',", f"'email' => '{email}',")
        src = src.replace(f"'username' => '{uname}', ", f"'email' => '{email}', ")

    if src != orig:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(src)
        print('updated', path)