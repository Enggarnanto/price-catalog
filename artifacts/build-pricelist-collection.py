import copy
import hashlib
import json
import re
import zipfile
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

root = Path('PRICELIST_GALIH')
sources = {
    'LATISEDU': root / 'latiedu-tabel-price-semua-published-2026-10-29T11-11-52-643Z.json',
    'TUTORINDO': root / 'tutorindo-tabel-price-semua-published-2026-10-29T11-15-07-249Z.json',
}
curricula = {'NAS': ('NASIONAL', 'Nasional'), 'NASPLUS': ('NASIONAL_PLUS', 'Nasional Plus'), 'INTER': ('INTERNASIONAL', 'Internasional')}
programs = {
    'PCG': ('PGC', ['Paket Pintar', 'Paket Cerdas', 'Paket Genius']),
    'EXCEL': ('EXCELLENT', ['Paket Excellent 1', 'Paket Excellent 2', 'Paket Excellent 3']),
    'BRILL': ('BRILLIANT', ['Paket Brilliant 1', 'Paket Brilliant 2', 'Paket Brilliant 3']),
}
levels = ['TK_MENGAJI', 'SD', 'SMP', 'SMA', 'UMUM', 'MAHASISWA']
labels = {'TK_MENGAJI': 'TK / Mengaji', 'SD': 'SD', 'SMP': 'SMP', 'SMA': 'SMA', 'UMUM': 'Umum', 'MAHASISWA': 'Mahasiswa'}
row_counts = {'TK_MENGAJI': 1, 'SD': 3, 'SMP': 2, 'SMA': 2, 'UMUM': 1, 'MAHASISWA': 1}
now = datetime.now(timezone.utc).isoformat()
hashes = {p: hashlib.sha256(p.read_bytes()).hexdigest() for p in sources.values()}
all_records = []
paths = []
notes = []
preserved = []

def group_of(text):
    upper = text.strip().upper()
    if upper == 'TK / MENGAJI':
        return 'TK_MENGAJI'
    if upper in ('UMUM', 'MAHASISWA'):
        return upper
    for group in ('SD', 'SMP', 'SMA'):
        if upper.endswith(' ' + group):
            return group
    raise ValueError('Jenjang tidak dikenali: ' + text)

def envelope(records):
    return {'format': 'lpm-export', 'version': 1, 'exported_at': now, 'resource_type': 'price_table', 'resources': records}

def write(path, document):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(document, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    assert json.loads(path.read_text(encoding='utf-8')) == document

for institution, source in sources.items():
    original = json.loads(source.read_text(encoding='utf-8-sig'))
    assert len(original['resources']) == 9, institution
    seen = set()
    institution_records = []
    for resource in original['resources']:
        tokens = resource['metadata']['name'].split('_')
        curriculum_key, curriculum_label = curricula[tokens[0]]
        code = next(code for code in programs if code in tokens)
        program, headers = programs[code]
        assert (curriculum_key, program) not in seen
        seen.add((curriculum_key, program))
        grouped = {level: [] for level in levels}
        for row in resource['content']['rows']:
            assert len(row) == 4
            grouped[group_of(row[0]['text'])].append(row)
        assert Counter({key: len(rows) for key, rows in grouped.items()}) == Counter(row_counts)
        original_headers = resource['content']['headers'][1:]
        if len(set(h.casefold() for h in original_headers)) < 3:
            notes.append(f"{institution} / {resource['metadata']['name']}: header sumber berulang ({' | '.join(original_headers)}); keluaran memakai Excellent 1/2/3 sesuai urutan kolom. Nominal tidak diubah.")
        for level, rows in grouped.items():
            name = f'{institution}_{level}_{curriculum_key}_{program}'
            content = copy.deepcopy(resource['content'])
            content['rows'] = copy.deepcopy(rows)
            content['headers'] = ['Jenjang Kelas', *headers]
            content['title'] = f'{institution} — {labels[level]} — {curriculum_label} — {program}'
            choices = ', '.join(h.removeprefix('Paket ') for h in headers[:-1]) + ', dan ' + headers[-1].removeprefix('Paket ')
            content['description'] = (
                f'Pilihan paket belajar {institution.title()} untuk jenjang {labels[level]} '
                f'dengan kurikulum {curriculum_label.lower()}. '
                f'Bandingkan biaya paket {choices} pada tabel untuk memilih sesuai kebutuhan belajar.'
            )
            content['show_title'] = False
            content['show_description'] = False
            content['orientation'] = 'vertical'
            content['presentation']['theme'] = 'classic'
            content['presentation']['footer_note'] = f'Kebutuhan belajar {labels[level]} dengan kurikulum {curriculum_label.lower()}?'
            content['style']['inherit'] = False
            content['style']['values'].update(header_bg='#ffffff', header_text='#111827', text='#111827', background='#ffffff', zebra=False)
            options = content['table_options']
            assert options['currency'] == 'IDR' and options['number_format'] == 'id-ID'
            options.update(decimals=0, price_columns=[1, 2, 3], freeze_first_column=True)
            record = {
                'resource_id': 0,
                'metadata': {'name': name, 'description': f'Lembaga: {institution}. Jenjang: {labels[level]}. Kurikulum: {curriculum_label}. Program: {program} ({choices}). Sumber: {resource["metadata"]["name"]}.'},
                'source': 'draft', 'production_version_id': 0, 'draft_revision': 0, 'content': content,
            }
            path = root / institution / level / curriculum_key / (program + '.json')
            if path.exists():
                existing = json.loads(path.read_text(encoding='utf-8-sig'))
                assert len(existing['resources']) == 1
                record = existing['resources'][0]
                assert record['metadata']['name'] == name and record['resource_id'] == 0
                assert record['content']['headers'] == ['Jenjang Kelas', *headers]
                assert record['content']['rows'] == rows
                preserved.append(path)
            else:
                write(path, envelope([record]))
            assert record['content']['rows'] == rows
            assert len(record['content']['presentation']['columns']) == 4
            assert len(record['content']['description']) <= 4000 and len(record['metadata']['description']) <= 1000
            institution_records.append(record)
            all_records.append(record)
            paths.append(path)
    assert len(institution_records) == 54
    write(root / institution / f'SEMUA-PRICELIST-{institution}.json', envelope(institution_records))

assert len(all_records) == 108
assert len({r['metadata']['name'] for r in all_records}) == 108
assert sum(len(r['content']['rows']) for r in all_records) == 180
assert len(json.dumps(envelope(all_records), ensure_ascii=False).encode('utf-8')) < 10485760
combined = root / 'SEMUA-PRICELIST-LATISEDU-TUTORINDO.json'
write(combined, envelope(all_records))
for source, digest in hashes.items():
    assert hashlib.sha256(source.read_bytes()).hexdigest() == digest

guide = root / 'PANDUAN-PRICELIST.md'
guide.write_text(
    '# Pricelist per lembaga, jenjang, kurikulum dan program\n\n'
    'Total **108 tabel**: LATISEDU 54 dan TUTORINDO 54. Tiap lembaga memiliki 6 jenjang × 3 kurikulum × 3 kelompok program.\n\n'
    '- Jenjang: TK/Mengaji, SD, SMP, SMA, Umum, Mahasiswa.\n'
    '- Kurikulum: Nasional, Nasional Plus, Internasional.\n'
    '- Program: PGC (Pintar/Cerdas/Genius), Excellent 1/2/3, Brilliant 1/2/3.\n'
    '- Satu tabel SD berisi tiga baris kelas; SMP dua baris; SMA dua baris. Jenjang lainnya masing-masing satu baris.\n'
    '- Format: Jenjang Kelas + tiga kolom Paket. Gaya Standar, header putih, nominal IDR; judul/deskripsi artikel disimpan tetapi tidak ditampilkan.\n'
    '- Semua nominal, urutan baris dan link sel mengikuti sumber. Tidak ada penambahan klaim sesi, durasi, atau fasilitas.\n\n'
    '## Import\n\n'
    'Gunakan **Import JSON → Buat item baru**. File gabungan SEMUA-PRICELIST-LATISEDU-TUTORINDO.json memuat semua 108 tabel. '
    'File gabungan per lembaga memuat 54 tabel. File dalam folder jenjang/kurikulum masing-masing memuat satu tabel.\n\n'
    '**Pilih satu cara import:** gabungan semua, gabungan per lembaga, atau per tabel. Jangan mengimpor file gabungan dan file satuannya sekaligus karena berisi data yang sama. '
    'Semua resource_id 0 agar situs memberikan ID baru; gunakan shortcode baru setelah memeriksa dan publish. Tabel asal tidak ditimpa.\n\n'
    '## Catatan sumber\n\n' + '\n'.join('- ' + n for n in notes) + '\n',
    encoding='utf-8',
)
bundle = root / 'PRICELIST-LATISEDU-TUTORINDO.zip'
with zipfile.ZipFile(bundle, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(paths):
        archive.write(path, path.relative_to(root).as_posix())
    archive.write(guide, guide.name)
with zipfile.ZipFile(bundle) as archive:
    assert archive.testzip() is None and len(archive.namelist()) == 109
print('Created collection: 108 tables, 180 rows; 54 tables per institution.')
print(f'Preserved existing example files: {len(preserved)}. Source files unchanged.')
print('Combined JSON bytes:', combined.stat().st_size)
for note in notes:
    print('NOTE:', note)
