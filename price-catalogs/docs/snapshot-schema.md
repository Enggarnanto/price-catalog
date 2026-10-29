# Snapshot schema v1 (legacy)

Referensi format 1.0.0 yang tetap didukung. Editor dan publikasi baru memakai [schema v2](snapshot-schema-v2.md); batas single/carousel dan grid mengikuti PRD v2.

Kontrak MVP bersama untuk `price_table` dan `price_catalog`. Ini mendefinisikan field bisnis minimum yang belum tercantum pada addendum PRD v1.1. Field tambahan/asing ditolak, sehingga tidak ada data yang diam-diam diterima tetapi hilang ketika render/restore.

## Struktur umum

```json
{
  "title": "Paket Belajar",
  "description": "Harga berlaku per sesi.",
  "style": { "accent": "#2563eb", "columns": 3 },
  "cta": { "label": "Hubungi kami", "url": "https://example.com/kontak", "new_tab": true },
  "items": [
    {
      "title": "Paket A",
      "price": "Mulai Rp150.000",
      "description": "Pendampingan privat",
      "cta": { "label": "Daftar", "url": "https://example.com/daftar", "new_tab": true }
    }
  ]
}
```

Catalog memakai struktur sama, dengan tambahan wajib pada setiap item:

```json
{
  "attachment_id": 123,
  "image_url": "https://example.com/wp-content/uploads/paket.jpg",
  "alt": "Ilustrasi paket belajar",
  "caption": "Paket A"
}
```

Urutan items wajib dipertahankan. Catalog MVP menggunakan satu gambar per item; galeri multi-gambar di dalam satu item berada di luar kontrak ini.

## Validasi

| Field | Batas / aturan |
| --- | --- |
| title | Teks UTF-8 maksimal 200 karakter; wajib nonkosong saat publish |
| description | Maksimal 4.000 karakter pada resource; 2.000 pada item |
| item price | Teks tampilan maksimal 100 karakter, boleh kosong |
| accent | Warna hex `#RRGGBB` |
| columns | Integer 1–4; dipakai renderer catalog |
| CTA label | Maksimal 100 karakter; harus berpasangan dengan URL |
| CTA URL | HTTP/HTTPS absolut, maksimal 2.048 karakter |
| CTA new_tab | Boolean |
| attachment_id | Integer positif; attachment gambar lokal wajib tersedia saat publish/restore |
| image_url | URL HTTP/HTTPS tersimpan saat memilih gambar; wajib nonkosong saat publish |
| alt | Teks maksimal 300 karakter |
| caption | Teks maksimal 500 karakter |
| items | Array berurutan, maksimal 200; minimal satu saat publish |

Seluruh teks menolak markup `<`/`>`. Renderer melakukan escaping ulang sesuai konteks. Draft boleh memiliki judul/item kosong selama struktur valid; draft item catalog tetap membutuhkan attachment ID positif, jadi pilih gambar sebelum menyimpan item catalog baru.

Preview dan frontend memakai renderer yang sama. JSON object dikanonisasi berdasarkan key, sedangkan array tidak diurutkan. Perbandingan menampilkan field/path, nilai sebelum, dan sesudah; perubahan posisi menghasilkan perbedaan pada index array terkait.

Snapshot v1 tidak bergantung pada resource lain. Bila referensi lintas resource ditambahkan kelak, referensi wajib terikat pada snapshot tertentu, sesuai addendum.

Schema registry MVP mendukung v1 saja. Schema asing ditolak dengan pesan jelas; setiap rilis masa depan wajib mempertahankan decoder/renderer untuk schema yang pernah diterbitkan.
