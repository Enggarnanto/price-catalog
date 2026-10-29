# Import JSON (2.4.0)

Buka **Pricelist → Import JSON**. Pilih file, tujuan import, lalu **Preview JSON**. File valid menampilkan nama, ID sumber, jenis/program, ringkasan isi, deskripsi, target dan peringatan. Centang item yang ingin diproses, lalu konfirmasi **Import pilihan ke Draft**.

## Format dan batas

- Mendukung JSON hasil **Export Data** (`format: lpm-export`, `version: 1`, resource_type price_table/price_catalog, resources array).
- Mendukung **Unduh Cadangan** satu resource yang berisi resource_id, metadata dan content. Backup tabel dikenali dari headers, katalog dari mode/items. Backup legacy yang ambigu harus menyertakan resource_type.
- UTF-8 dengan/tanpa BOM, maksimal 10 MiB per file dan 200 resource. Konten tiap resource tetap mengikuti schema plugin dan batas snapshot 1 MiB.
- ID sumber positif yang duplikat ditolak. File updater/manifest release bukan data tabel dan tidak dapat diimpor di sini.
- Jika struktur/konten salah, preview gagal tanpa menulis data. Kesalahan tujuan existing dilaporkan per item dan item tersebut tidak dapat dipilih.

## Pilih tujuan yang benar

**Buat item baru (ID baru)** adalah pilihan bawaan. ID sumber hanya informasi; situs memberikan ID baru. Artikel dengan shortcode ID lama tidak dialihkan otomatis. Gunakan mode ini untuk membuat salinan atau memindahkan data ke situs lain.

**Perbarui item berdasarkan ID di JSON** mengganti draft dan metadata pada ID tersebut. Jenis resource harus cocok, resource harus masih aktif dan versi belum berubah sejak preview. ID serta shortcode tetap dipakai. Nama tujuan ditampilkan; perbedaan nama diberi peringatan untuk menghindari salah tujuan saat file berasal dari situs lain. Draft tersimpan yang sudah ada akan diganti setelah konfirmasi.

Untuk file `PRICELIST_GALIH/tabel-price-semua-published-2026-10-29T08-37-07-642Z.json` yang dipakai pada situs asalnya, pilih **Perbarui item berdasarkan ID di JSON** jika tujuannya memperbarui deskripsi pada 18 tabel yang sama.

## Apa yang ikut diimpor

Nama/deskripsi internal, judul/deskripsi artikel, toggle tampil, semua baris/kolom harga, hyperlink, currency/mobile settings, styling per resource, footer/CTA dan metadata gambar dipertahankan. Tidak ada aktivasi judul/deskripsi otomatis bila file menonaktifkannya. Opsi styling global, riwayat versi dan berkas media bukan bagian file export dan tidak dibuat ulang.

Gambar katalog tidak diunduh. Attachment ID hanya dipertahankan jika gambar lokal tersedia dan URL-nya sesuai. Referensi yang tidak cocok dijadikan attachment_id 0, disertai peringatan untuk memilih ulang dari Media Library sebelum publish. URL/alt/caption/link/CTA tetap tersimpan sebagai metadata draft.

## Draft, retry, dan konflik

Import tidak memublikasikan harga. Production dan immutable history existing tetap sama; gunakan editor atau bulk Publish setelah pemeriksaan. Draft yang sebelumnya stale tetap perlu rekonsiliasi lewat editor sebelum diterbitkan.

Item diproses terpisah, sehingga sebagian dapat berhasil meski item lain gagal. Hasil menunjukkan ID tujuan serta error per item; item yang tidak dipilih tetap tidak diproses. Item berhasil dinonaktifkan pada batch tersebut.

Setiap operasi menyimpan draft dan receipt secara atomik pada transaksi InnoDB. Respons terputus dapat dicoba ulang pada batch yang sama tanpa membuat resource ganda, termasuk request bersamaan. Client menyimpan identitas batch kecil di sessionStorage berdasarkan file/mode. Selama sesi browser tersebut masih ada, preview ulang file/mode yang sama menggunakan identitas batch yang sama. Receipt yang sudah selesai mengonfirmasi hasil sebelumnya, bukan menerapkan ulang file di atas perubahan yang lebih baru.

Jika versi tujuan berubah, preview ulang untuk mengambil versi terbaru. Jika memang ingin mengimpor ulang file yang sama sebagai operasi baru, klik **Mulai batch baru** dan konfirmasi; mode baru dapat menghasilkan salinan tambahan. Jangan mulai batch baru hanya untuk mengatasi respons terputus. Setelah sesi browser hilang, periksa resource yang sudah dibuat sebelum mengulang mode Buat item baru.

Database schema 5 menambahkan tabel import_operations untuk receipt/retry. Snapshot schema tidak berubah. Import hanya dapat dilakukan administrator dengan nonce valid.
