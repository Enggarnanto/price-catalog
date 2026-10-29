import copy
import json
from pathlib import Path

path = Path('PRICELIST_GALIH/tabel-price-semua-published-2026-10-29T08-37-07-642Z.json')
backup = path.with_name(path.stem + ' - Copy.json')
original = json.loads(path.read_text(encoding='utf-8-sig'))
updated = copy.deepcopy(original)
curricula = {'NAS': 'nasional', 'NASPLUS': 'nasional plus', 'INTER': 'internasional'}
brands = {'TUTORINDO': 'Tutorindo', 'LATISEDU': 'Latisedu'}
groups = {'PCG': 'Pintar, Cerdas, dan Genius', 'EXCEL': 'Excelent', 'BRILL': 'Brilliant'}
levels = 'TK/Mengaji, SD, SMP, SMA, umum, dan mahasiswa'

for resource in updated['resources']:
    parts = resource['metadata']['name'].split('_')
    curriculum = curricula[parts[0]]
    brand = next(label for code, label in brands.items() if code in parts)
    group = next(code for code in groups if code in parts)
    assert not resource['metadata']['description'] and not resource['content']['description'], 'Deskripsi sudah diisi; periksa sebelum mengganti.'
    resource['metadata']['description'] = (
        f'Tabel harga program reguler {brand} untuk kurikulum {curriculum}, '
        f'kelompok paket {groups[group]}. Memuat biaya berdasarkan jenjang {levels}.'
    )
    if group == 'PCG':
        description = (
            f'Program les privat {brand} untuk kebutuhan belajar dengan kurikulum {curriculum}. '
            f'Tersedia pilihan paket Pintar, Cerdas, dan Genius dengan biaya berdasarkan jenjang {levels}. '
            'Bandingkan harga setiap paket pada tabel untuk memilih sesuai jenjang dan kebutuhan belajar.'
        )
    elif group == 'EXCEL':
        description = (
            f'Daftar biaya program les privat {brand} kelompok paket Excelent untuk kurikulum {curriculum}. '
            f'Harga disajikan berdasarkan jenjang {levels}. '
            'Gunakan rincian pada setiap kolom untuk membandingkan pilihan paket Excelent sesuai jenjang belajar.'
        )
    else:
        description = (
            f'Program les privat {brand} kelompok paket Brilliant untuk kebutuhan belajar kurikulum {curriculum}. '
            f'Tabel memuat pilihan Brilliant 1, Brilliant 2, dan Brilliant 3 dengan biaya berdasarkan jenjang {levels}. '
            'Pilih jenjang yang sesuai untuk membandingkan biaya ketiga pilihan paket.'
        )
    resource['content']['description'] = description
    assert len(description) <= 4000 and len(resource['metadata']['description']) <= 1000

assert len(updated['resources']) == 18
for before, after in zip(original['resources'], updated['resources']):
    rest = copy.deepcopy(after)
    rest['metadata']['description'] = before['metadata']['description']
    rest['content']['description'] = before['content']['description']
    assert rest == before, f"Field lain berubah pada ID {before['resource_id']}"
assert {k: v for k, v in updated.items() if k != 'resources'} == {k: v for k, v in original.items() if k != 'resources'}
assert json.loads(backup.read_text(encoding='utf-8-sig')) == original, 'Salinan awal berbeda; periksa backup.'
temporary = path.with_name(path.name + '.tmp')
temporary.write_text(json.dumps(updated, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
assert json.loads(temporary.read_text(encoding='utf-8')) == updated
temporary.replace(path)
print('Selesai: 18 program, 36 field deskripsi terisi. Field lainnya identik dengan salinan awal.')
print('Contoh:', updated['resources'][0]['content']['description'])
print('show_description tetap mengikuti data awal:', sorted({r['content']['show_description'] for r in updated['resources']}))
