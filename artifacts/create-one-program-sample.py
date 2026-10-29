import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

source = Path('PRICELIST_GALIH/latiedu-tabel-price-semua-published-2026-10-29T11-11-52-643Z.json')
source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
document = json.loads(source.read_text(encoding='utf-8-sig'))
original = next(r for r in document['resources'] if r['metadata']['name'] == 'NAS_PCG_LATISEDU')
rows = [row for row in original['content']['rows'] if row[0]['text'].endswith(' SD')]
assert len(rows) == 3
content = copy.deepcopy(original['content'])
content['rows'] = copy.deepcopy(rows)
content['headers'] = ['Jenjang Kelas', 'Paket Pintar', 'Paket Cerdas', 'Paket Genius']
content['title'] = 'LATISEDU — SD — Nasional — PGC'
content['description'] = (
    'Pilihan paket belajar Latisedu untuk siswa SD dengan kurikulum nasional. '
    'Bandingkan biaya paket Pintar, Cerdas, dan Genius pada tabel berikut untuk memilih '
    'sesuai kebutuhan belajar.'
)
content['show_title'] = False
content['show_description'] = False
content['presentation']['theme'] = 'classic'
content['presentation']['footer_note'] = 'Kebutuhan belajar SD dengan kurikulum nasional?'
content['style']['inherit'] = False
content['style']['values'].update(header_bg='#ffffff', header_text='#111827', text='#111827', background='#ffffff', zebra=False)

record = {
    'resource_id': 0,
    'metadata': {
        'name': 'LATISEDU_SD_NASIONAL_PGC',
        'description': (
            'Lembaga: LATISEDU. Jenjang: SD (Kelas 1–2, Kelas 3–5, dan Kelas 6). Kurikulum: Nasional. '
            'Program: PGC (Pintar, Cerdas, Genius). Nominal mengikuti data sumber NAS_PCG_LATISEDU.'
        ),
    },
    'source': 'draft',
    'production_version_id': 0,
    'draft_revision': 0,
    'content': content,
}
result = {
    'format': 'lpm-export',
    'version': 1,
    'exported_at': datetime.now(timezone.utc).isoformat(),
    'resource_type': 'price_table',
    'resources': [record],
}
target = Path('PRICELIST_GALIH/LATISEDU/SD/NASIONAL/PGC.json')
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
verified = json.loads(target.read_text(encoding='utf-8'))
assert len(verified['resources']) == 1 and len(content['rows']) == 3
assert verified['resources'][0]['content']['rows'] == rows
assert content['table_options'] == original['content']['table_options']
assert len(content['headers']) == len(rows[0]) == len(content['presentation']['columns'])
assert record['resource_id'] == 0 and record['production_version_id'] == 0
assert hashlib.sha256(source.read_bytes()).hexdigest() == source_hash
print('Created:', target)
for row in rows:
    print(row[0]['text'] + ': ' + ', '.join(c['text'] for c in row[1:]))
print('Validated: 1 institution/level/curriculum/program group; source prices and file unchanged.')
