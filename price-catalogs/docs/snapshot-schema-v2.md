# Snapshot schema v2

Schema v1 tetap dibaca oleh LPM_Legacy_Content / LPM_Legacy_Renderer; adapter hanya membuat salinan untuk editor, tidak menulis ulang history.

## Field bersama

- title: string maksimal 200; boleh kosong.
- show_title: boolean.
- description: keterangan publik maksimal 4.000 karakter.
- style: {inherit: boolean, values: styling yang tervalidasi}.
- cta: {label, url, new_tab}; pasangan label/URL wajib konsisten.

Styling values: accent, header_bg, header_text, background, text, border, stripe, button_bg, button_text berupa hex enam digit; padding/radius 0-32 px; font_size 10-32 px; zebra boolean. Global styling tersimpan terpisah dan tidak dibekukan dalam snapshot inherit.

## Price table

- program: reguler atau eksklusif; adapter legacy boleh menghasilkan nilai kosong pada draft agar admin memilih sebelum publish.
- orientation: vertical atau horizontal.
- headers: array 1-20 string, masing-masing maksimal 200 karakter.
- rows: array maksimal 200 baris dengan jumlah sel sesuai headers.
- cell: {text: string maksimal 4.000, url: HTTP/HTTPS atau kosong, new_tab: boolean}.

Header + rows adalah satu matrix. Mode horizontal merender transpose matrix tersebut tanpa memodifikasi data sumber.

## Price catalog

- mode: single atau carousel.
- autoplay: boolean, default false.
- interval: integer 5-120 detik.
- items: array berurutan, maksimal 50.
- item: title, price, description, attachment_id, image_url, alt, caption, link, new_tab, cta.

title/price/description mempertahankan teks legacy dan bersifat opsional. link adalah hyperlink gambar, berbeda dari cta.url. Single membutuhkan tepat satu item saat publish; carousel minimal dua. Draft boleh belum lengkap, termasuk attachment_id=0. Publish/restore memvalidasi gambar wajib tersedia.

## Batas dan migrasi

Snapshot maksimal 1 MiB UTF-8. Semua struktur divalidasi, unknown field ditolak, markup HTML/CSS arbitrer tidak diterima. JSON object dikanonisasi berdasarkan key; urutan array tetap dipertahankan.

Adapter tabel v1 memetakan title/price/description/CTA menjadi empat kolom dan mempertahankan tautan CTA. Adapter katalog mempertahankan seluruh teks, gambar, caption dan CTA; mode ditentukan dari jumlah gambar. Name/Description internal berada di record resource dan tidak menggantikan keterangan publik.

Draft menyimpan JSON schema yang dapat diinfer dari show_title; snapshot selalu menyimpan nomor schema eksplisit. Restore menyalin snapshot dan schema sumber persis, termasuk versi v1. Nomor versi dan ID resource tidak berubah saat adapter editor dijalankan.
