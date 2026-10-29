# Latis Price Catalogs 2.3.0

Kelola tabel harga dan katalog gambar di satu tempat. Penulis menempel shortcode ke artikel; pembaruan berikutnya dilakukan pada resource yang sama tanpa mengedit artikel satu per satu.

## Instalasi / upgrade

1. Buka **Plugins > Add New > Upload Plugin**.
2. Unggah `price-catalogs.zip` (versi 2.3.0) dan pilih mengganti plugin lama jika WordPress menawarkannya. Folder plugin tetap `price-catalogs`; tidak perlu menghapus plugin atau membuat ulang tabel.
3. Aktifkan plugin bila belum aktif, lalu buka menu **Pricelist**.
4. Data versi lama tetap tersimpan. Buka resource lama, periksa hasil pemetaan, pilih Category Program untuk tabel, dan simpan draft sebelum publish format baru.
5. Jalankan **Rebuild indeks artikel** untuk mendata konten yang sudah ada sebelum upgrade.

Persyaratan: WordPress 6.2+, PHP 8.1+ dengan mysqli, MySQL 5.7+ atau MariaDB 10.3+ dengan fungsi JSON, serta InnoDB. Runtime yang diuji: WordPress 6.8.3, PHP 8.1.10, MySQL 8.0.30. MariaDB dan versi minimum lainnya belum diuji langsung.

Snapshot schema v1 tetap dapat dirender dan dipulihkan melalui plugin v2. Jangan downgrade berkas ke 1.0.0 setelah menerbitkan schema v2; renderer versi lama tidak mengenali schema baru. Gunakan Version History pada v2 untuk memulihkan konten.

## Updater plugin (2.3.0)

Buka **Pricelist → Updater** untuk mengatur sumber rilis, melihat versi terpasang/terbaru, menjalankan **Cek update sekarang**, dan membuka pemasangan update lewat WordPress.

- Updater default **nonaktif**, dengan sumber **GitHub Releases** dan repository **Enggarnanto/price-catalog** sudah terisi. Aktifkan dan cek update setelah ada release stabil beserta aset ZIP plugin di repository tersebut.
- GitHub memakai aset ZIP bernama sesuai versi (atau `price-catalogs.zip`), digest SHA-256 GitHub / file `.sha256`, dan opsional `price-catalogs-update.json`. Jangan gunakan arsip Source code otomatis GitHub. Repository private/prerelease belum didukung.
- Alternatif sumber URL manifest JSON HTTPS tetap tersedia; langkah penerbitan ada di [panduan updater](docs/updater.md). Paket rilis di `dist/` menyertakan manifest dan checksum untuk diunggah bersama ZIP.
- Pemeriksaan berkala mengikuti WordPress; hasil sukses dicache 6 jam, kegagalan 10 menit. Tombol cek manual mengambil ulang sumber. Versi kedaluwarsa/galat tidak dipakai untuk menawarkan pemasangan.
- Paket harus cocok checksum, folder, nama plugin dan nomor versi; rilis yang tidak kompatibel ditampilkan tanpa tombol pasang. Update tidak menghapus tabel harga/katalog/riwayat.
- Plugin tidak menyalakan auto-install. Pilihan pemasangan otomatis mengikuti halaman Plugins WordPress. Pada multisite, sumber berlaku untuk network dan diatur lewat Network Admin → Plugins → Updater.
- Untuk mulai menggunakan fitur ini, upload ZIP v2.3.0 secara manual sekali. Rilis berikutnya dapat diambil dari sumber update yang sudah dikonfigurasi.

## Editor bertab & preview responsif (2.2.0)

UI Tabel Price dan Katalog Price mengikuti pola workspace baru:

1. **Isi Data & Grid Matrix / Gambar & Urutan:** edit sel langsung, cari jenjang/paket, tambah baris/kolom, Paste Sheet, atur kolom unggulan lewat tombol bintang dan urutkan gambar katalog. Menu titik tiga pada header menyimpan kontrol pindah/hapus kolom.
2. **Pengaturan & Format:** nama paket, judul/deskripsi artikel, program/mode katalog, mata uang, orientasi desktop/mobile, badge, warna dan styling.
3. **Footer & Tombol WhatsApp / Footer & CTA:** teks footer dan URL/label tombol konsultasi. CTA per gambar tetap tersedia pada kartu gambar.
4. **Riwayat Versi (Log):** preview/restore versi, status cache dan opsi hapus/cadangan resource.

Perpindahan tab mempertahankan isian yang belum disimpan. Gunakan tombol aksi di atas atau bar bawah untuk menyimpan draft, export, preview dan menerbitkan. Status **isian dicadangkan di browser** berbeda dari **Draft tersimpan** di server; tidak ada klaim autosave server.

Grid memakai baris ringkas. Kolom nominal menampilkan format mata uang saat tidak diedit; fokus pada sel untuk mengubah nilai aslinya. Tab/Shift+Tab berpindah sel, Enter berpindah ke baris berikutnya dan menambah baris bila sudah di baris terakhir, Shift+Enter menambah baris teks. Filter baris hanya memengaruhi editor; preview/export tetap menggunakan seluruh data.

**Live Output Preview** memakai renderer server yang sama dengan artikel, termasuk katalog/carousel, styling, footer, nominal dan seluruh baris. Perubahan tampil tanpa menyimpan/publish. Pilih **Desktop View** (viewport minimal 960px, diskalakan agar muat panel) atau **Mobile View** (viewport 390px). Preview panjang dapat digeser vertikal di dalam panel; tabel lebar tetap dapat digeser horizontal di dalam frame. Link preview tidak menavigasi halaman admin.

Jika ada input belum valid atau REST API gagal, preview lama tetap tersedia dengan pesan bahwa preview belum diperbarui. Input dan draft tidak dihapus. Tombol Publish tetap membuka review dan memerlukan **Konfirmasi Publish** sebelum harga publik berubah.

## Pilih item dan aksi massal (2.1.0)

Di **Tabel Price** atau **Katalog Price**, centang item yang ingin diproses. Checkbox di header memilih seluruh item pada halaman hasil saat ini (maksimal 20), sesuai filter. Pilihan direset saat berpindah halaman/filter.

- **Siapkan Draft:** salin versi terbit ke draft bila belum ada draft. Draft yang sudah ada tetap dipertahankan dan harga terbit tetap tampil. Item baru mendapatkan draft awal.
- **Unpublish:** tarik item dari artikel tanpa menghapus resource atau riwayat. Draft yang ada dipertahankan; jika belum ada draft, versi terbit disalin agar tetap dapat diedit dan dipublikasikan kembali.
- **Publish:** tampilkan preview seluruh draft terpilih, periksa, lalu klik **Konfirmasi Publish**. Aksi ini hanya memakai draft yang sudah tersimpan. Draft yang perlu diselaraskan dengan perubahan sesi lain harus dibuka lewat editor terlebih dahulu.

Klik **Terapkan ke pilihan** dan konfirmasi. Hasil dilaporkan per item; kegagalan satu item tidak membatalkan item lain. Jika koneksi terputus, muat ulang daftar untuk memeriksa status sebelum mencoba kembali. Semua aksi perubahan hanya tersedia bagi administrator. Unpublish memicu antrean invalidasi cache tersendiri; integrasi cache/CDN situs tetap diperlukan.

## Format nominal dan tabel mobile (2.1.0)

Buka editor Tabel Price, lalu bagian **Nominal mata uang & tampilan mobile**:

1. Pilih **IDR, USD, EUR, GBP, SGD, MYR, AUD atau JPY** dan jumlah desimal (0 atau 2).
2. Pilih format angka **Indonesia** (`150.000,50`) atau **Internasional** (`150,000.50`). Cara paling mudah memasukkan nominal adalah angka tanpa simbol/pemisah, misalnya `150000`.
3. Centang **Kolom nominal** yang berisi harga. Kolom lain dan teks seperti “Hubungi kami” tetap tampil sebagai teks. Pengaturan kolom mengikuti perpindahan/penghapusan kolom.
4. Pilih **Orientasi di mobile**: Ikuti desktop, Vertikal atau Horizontal. Pengaturan ini tidak mengubah orientasi desktop.
5. Aktifkan **Freeze kolom pertama saat tabel digeser** agar header dan sel pertama tetap terlihat. Tabel lebar digeser di dalam panel, bukan membuat halaman ikut melebar.
6. **Simpan Draft**, lalu preview dan publish untuk menerapkan ke artikel.

Contoh: nominal `150000` tampil sebagai `Rp 150.000` pada IDR/Indonesia atau `US$ 150,000.00` pada USD/Internasional dengan dua desimal. Ini format tampilan, bukan konversi kurs. Isi asli sel, tautan dan export CSV tetap dipertahankan; JSON menyertakan pengaturan baru.

Mobile memakai breakpoint 700px. Jika orientasi berbeda, server menyiapkan dua tampilan dan CSS hanya menampilkan salah satunya. Tabel lama tidak berubah sebelum disimpan/publish. Pengaturan baru memakai [snapshot schema 5](docs/snapshot-schema-v5.md); jangan downgrade ke versi sebelum 2.1.0 setelah menyimpan snapshot ini.

## Nama paket sebagai tag shortcode (2.0.10)

Tag shortcode sekarang langsung mengikuti nama internal paket. Contoh nama **Harga Les Privat Nasional Pintar Cerdas Genius**, ID **6**:

```text
[harga-les-privat-nasional-pintar-cerdas-genius id="6"]
```

Format yang sama berlaku pada tabel dan katalog. Pilihan tampilkan judul/deskripsi tetap dapat ditambahkan. ID menentukan resource yang dirender; nama paket yang sama pada dua resource tetap dibedakan oleh ID.

Nama lama dipertahankan sebagai alias saat nama paket diganti melalui editor plugin, sehingga shortcode yang sudah dipasang tetap berjalan. Item diarsipkan menghasilkan output kosong. Shortcode lama `latis_price_table` / `latis_price_catalog`, dengan atau tanpa atribut `nama`, tetap didukung.

Jika nama paket sama dengan tag shortcode milik WordPress/plugin lain (misalnya `gallery`), tombol Salin/Sisipkan menggunakan format kompatibel `[latis_price_table nama="gallery" id="6"]` agar tidak menimpa fungsi plugin tersebut. Nama alias disimpan sebagai opsi plugin; tidak ada migrasi snapshot harga baru.

## Widget shortcode, judul dan deskripsi

- Di editor **Posts** dan **Pages**, buka widget **Pricelist — Shortcode**, lalu klik **Pilih & sisipkan shortcode**. Gutenberg juga memiliki panel **Pricelist** di pengaturan dokumen; editor klasik tetap menyediakan tombol di toolbar media.
- Cari nama paket atau ID, pilih Tabel Price/Katalog Price, lalu sisipkan atau salin. Pemilih hanya menampilkan resource yang sudah terbit.
- Shortcode baru menyertakan nama paket dan ID: `[paket-reguler id="12"]` atau `[katalog-reguler id="15"]`. Nama diambil dari nama internal dan dibuat menjadi slug. ID tetap menjadi acuan; nama lama pada shortcode tidak menghalangi render setelah paket diubah namanya. Shortcode lama yang hanya memiliki ID tetap bekerja.
- Di editor resource, atur **Tampilkan judul di artikel** dan **Tampilkan deskripsi di artikel** secara terpisah, lalu simpan/publish untuk mengubah tampilan bawaan paket. Judul/deskripsi yang dikosongkan tidak ditampilkan.
- Di pemilih shortcode, pilih **Ikuti pengaturan paket**, **Tampilkan**, atau **Sembunyikan** untuk judul dan deskripsi. Pilihan per artikel menghasilkan atribut `show_title="1"` / `show_description="0"` dan tidak mengubah resource sumber. Contoh: `[paket-reguler id="12" show_title="0" show_description="1"]`.
- Versi plugin dan nama pembuat **Galih Enggarnanto** tampil di navigasi Pricelist, widget dan footer pemilih shortcode.
- Pengaturan deskripsi baru disimpan memakai [snapshot schema 4](docs/snapshot-schema-v4.md). Jangan downgrade ke versi sebelum 2.0.9 setelah menyimpan snapshot ini. Snapshot lama tetap dapat dirender dan dipulihkan; deskripsi lama tetap tampil secara bawaan.

## Pintasan untuk SEO writer

- **Editor blok (Gutenberg):** buka pengaturan artikel, lalu panel **Pricelist ? Sisipkan Pricelist**. Cari nama, ID, atau deskripsi, pilih tabel/katalog, lalu klik **Sisipkan**. Plugin menambah blok Shortcode di posisi editor.
- **Editor klasik:** klik **Sisipkan Pricelist** di toolbar media. Shortcode disisipkan di posisi kursor, baik mode Visual maupun Teks.
- **Bar atas WordPress:** gunakan **Pricelist** untuk membuka pustaka. Submenu **Dokumentasi** membuka panduan.
- Pilihan **Salin shortcode** tersedia bila writer ingin menempel sendiri. Pencarian mendukung halaman hasil, tabel harga, dan katalog gambar.
- Pemilih hanya menampilkan item yang sudah terbit. Hak writer tidak berubah: pintasan tidak memberi izin mengedit harga, melihat draft, atau menghapus resource.

Pembuat: **Galih Enggarnanto** ? [Website](https://www.mtalkblog.com/) ? [Dokumentasi](https://www.mtalkblog.com/p/plugin-price-catalogs.html)

## Tabel Price

- **All Tables:** ID/nama, program, deskripsi, status, shortcode, daftar artikel pemakai, waktu update dan publish.
- Cari nama, ID atau deskripsi. Filter program Reguler/Eksklusif, status publikasi, dan draft.
- **Add New:** beri nama internal, lalu isi grid dengan header dan sel yang dapat diedit langsung.
- Tambahkan, hapus, atau pindahkan baris/kolom langsung tanpa reload halaman atau mengambil ulang history. Form metadata dan posisi scroll tetap dipertahankan.
- Tab / Shift+Tab berpindah sel, Enter berpindah ke baris berikutnya, Shift+Enter menambah baris teks dalam sel. Baris/kolom baru langsung mendapat fokus.
- Tampilan admin memakai seluruh lebar area konten WordPress. Filter dan form menyesuaikan layar kecil; tabel lebar dapat digeser di dalam panel.
- Paste rentang dari Excel/Sheets, termasuk sel quoted/multiline. Konfirmasi diperlukan jika grid harus diperluas.
- Setiap sel dapat diberi tautan melalui **Tambahkan tautan**.
- **Vertikal:** paket berada di baris. **Horizontal:** matrix ditransposisi sehingga paket berada di kolom. Preview ringkas langsung mengikuti orientasi.
- Maksimal 200 baris data, 20 kolom, dan snapshot 1 MiB. Harga merupakan teks tampilan; tidak ada formula/pembayaran.

## Gaya paket harga dan footer konsultasi

1. Buka tabel, pilih orientasi **Vertikal**, lalu **Gaya tabel ? Paket harga - seperti referensi**. Tabel baru menggunakan pilihan ini; tabel lama tetap Standar sampai dipilih ulang.
2. Isi nama paket pada header grid. Buka **Subjudul & badge tiap kolom** untuk menambah teks seperti ?Paket hemat (4 sesi)? dan badge ?Starter?, ?Populer?, atau ?Mastery?.
3. Pilih **Kolom unggulan** untuk warna navy yang lebih gelap dan aksen kuning. Pilih Tanpa kolom unggulan untuk mematikannya.
4. Atur **Footer konsultasi**: teks kecil, judul, label CTA, URL dan pilihan tab baru. Untuk WhatsApp isi URL `https://wa.me/62?` dengan nomor tujuan yang benar. Nomor tidak diisi otomatis. Tombol hanya muncul setelah label dan URL tersedia.
5. Klik **Publish / Perbarui Harga**, periksa preview lengkap, lalu konfirmasi. Shortcode artikel tetap sama.

Gaya paket memakai palet navy, kuning, dan hijau sesuai referensi. Harga numerik mendapat awalan Rp secara visual tanpa mengubah isi sel; harga dengan teks tambahan tetap ditampilkan sebagai teks. Subjudul, badge dan highlight mengikuti perpindahan kolom. Footer mengikuti lebar layar kecil; tabel dapat digeser dalam kartu.

Gaya ini berlaku untuk orientasi vertikal. Orientasi horizontal memakai tabel standar. Data harga lama dan snapshot terbit tidak diganti otomatis. Tabel dengan pengaturan tampilan memakai [snapshot schema 3](docs/snapshot-schema-v3.md); jangan downgrade ke versi sebelum 2.0.7 setelah menyimpan tabel ini.

## Simpan dan publish

1. Isi tabel, lalu klik **Simpan Draft**. Draft tidak mengubah harga yang sedang terbit.
2. Untuk menerbitkan, klik **Publish / Perbarui Harga**, periksa preview, lalu **Konfirmasi Publish**. Catatan perubahan opsional.
3. Saat berpindah menu, perubahan disimpan lebih dahulu. Jika penyimpanan gagal, editor tetap terbuka beserta isiannya.

Tidak perlu mengunduh input atau mengambil ulang versi server untuk setiap perubahan. Server mengunci resource, memeriksa nomor versi, memvalidasi konten, dan memverifikasi commit melalui koneksi database terpisah. Respons sukses membawa data yang telah diverifikasi; editor tidak lagi membatalkan sukses hanya karena pembacaan HTTP berikutnya menerima respons lama. URL GET dibuat unik untuk menghindari penggunaan ulang respons cache.

Perubahan dari sesi lain digabungkan otomatis jika tidak bertabrakan. Array baris/kolom dan galeri diperlakukan sebagai satu bagian agar perpindahan urutan tidak mencampur sel atau gambar yang berbeda. Jika bagian yang sama berubah di kedua sesi, pengguna memilih **Gunakan isian saya** atau **Batal**; tidak ada penimpaan otomatis. Pilihan mengganti draft juga mencadangkan draft server sebelumnya di browser.

Isian belum tersimpan dicadangkan otomatis ke sessionStorage dan localStorage jika browser mengizinkan, lalu dipulihkan saat editor dibuka kembali. Cadangan lama yang tidak memiliki informasi versi dasar meminta konfirmasi sebelum mengganti draft yang berbeda. Cadangan browser bukan penyimpanan server dan dapat hilang jika data browser dihapus. **Opsi lainnya ? Unduh cadangan** hanya pilihan tambahan, bukan syarat menyimpan.

Riwayat versi mendukung preview dan pemulihan. Pemulihan menambah versi baru dan mempertahankan draft. Penyelarasan draft terhadap versi terbit dilakukan dalam alur preview; konfirmasi publikasi tetap diperlukan. Jika respons publish terputus, **Coba lagi** memakai identitas operasi yang sama untuk menghindari versi ganda.

## Hapus tabel atau katalog

Klik **Hapus** di daftar, atau **Opsi lainnya ? Hapus tabel/katalog** di editor. Konfirmasi menjelaskan bahwa item hilang dari daftar dan shortcode tidak lagi menampilkan harga. Berkas gambar tidak dihapus. Data dan riwayat tetap ada sebagai arsip di database; versi ini belum menyediakan layar pemulihan item terhapus. Setelah memakai Hapus, jangan downgrade ke versi sebelum 2.0.3 karena versi lama belum mengenali item yang diarsipkan. Penghapusan memerlukan hak administrator serta versi yang sesuai, memakai transaksi dan verifikasi commit. Invalidasi cache memakai antrean yang dapat dicoba ulang.

## Export Tabel Price dan Katalog Price

- Klik **Export Data** di daftar untuk mengunduh semua hasil filter yang sudah diterapkan, termasuk halaman berikutnya. Tombol **Export** pada baris hanya mengunduh item tersebut.
- Di editor, klik **Export Data** untuk mengunduh isian di layar, termasuk perubahan yang belum disimpan. Export tidak menyimpan atau menerbitkan perubahan.
- Pilih **CSV** untuk Excel/Google Sheets atau **JSON** untuk struktur konten lengkap, link, CTA dan pengaturan tampilan per resource.
- Pilih draft terbaru/isian layar atau **Versi terbit saja**. Export massal versi terbit melewati item yang belum dipublikasikan; export satu item yang belum terbit menampilkan pesan kesalahan.
- CSV tabel mencantumkan ID, metadata, header dan baris data dalam urutan grid asli (orientasi dicatat terpisah). CSV katalog memuat urutan gambar, URL, alt, caption, harga, hyperlink dan CTA. Semua CSV memakai UTF-8 BOM dan mengamankan teks yang dapat dibaca sebagai formula spreadsheet dengan awalan apostrof.
- JSON tidak menyertakan riwayat versi, opsi styling global atau berkas gambar asli. URL dan ID gambar tetap disertakan. Import JSON belum tersedia; CSV export memakai metadata sehingga bukan format untuk langsung diimpor ulang lewat Import CSV.
- Export hanya tersedia untuk administrator. Proses membaca item satu per satu; perubahan dari sesi lain selama export tidak membentuk snapshot serentak seluruh database.

## Import CSV

- Dari menu **Import CSV**, pilih tabel baru atau isi ID tabel existing.
- Atau gunakan **Import CSV** pada editor tabel yang sedang dibuka.
- File UTF-8, dengan/tanpa BOM, maksimal 2 MiB; delimiter koma atau titik koma.
- Pilih apakah baris pertama merupakan header, lalu klik **Preview CSV**.
- Periksa ukuran dan sampel, lalu **Terapkan & Simpan Draft**. Metadata/styling tidak diganti; production tidak terpengaruh.
- CSV malformed, markup, kolom tidak konsisten, atau hasil melewati batas grid/snapshot akan ditolak.

## Katalog Price

Pilih **Single Image** (tepat satu gambar) atau **Carousel** (2-50 gambar). Gunakan Media Library, atur urutan, alt/caption, hyperlink gambar, serta CTA per gambar. Link gambar dan CTA dapat menuju URL berbeda. Teks judul/harga/deskripsi tambahan tetap tersedia untuk kompatibilitas dan kebutuhan opsional.

Gambar memakai tinggi otomatis dan tidak dipaksa crop, sehingga artwork harga tetap terbaca. Carousel memiliki navigasi, indikator, swipe, keyboard, serta opsi autoplay dengan interval 5-120 detik. Autoplay default mati, berhenti pada interaksi/hover/focus, dan menghormati reduced motion. Tanpa JavaScript, semua slide tetap tersedia sebagai galeri berurutan.

## Shortcode untuk penulis

```text
[latis_price_table id="12"]
[latis_price_catalog id="15"]
```

ID adalah ID resource, bukan versi/gambar. Gunakan **Salin Shortcode** pada daftar atau editor. Tempel ke blok **Shortcode** Gutenberg atau tab **Teks** Classic Editor.

Penulis dengan `edit_posts` memiliki pustaka resource published dan preview. Hanya pengelola dengan `manage_options` dapat mengubah pricing. Draft, riwayat privat, dan aksi publish tidak tersedia untuk penulis.

Resource belum published/tidak ditemukan menghasilkan output publik kosong. Parameter versi tambahan tidak dapat membuka draft atau arsip. Shortcode yang sama tetap dipakai setelah publish, perubahan orientasi, atau restore.

## Styling dan penggunaan artikel

**Styling** mengatur warna header, teks/background, border, zebra, padding, link, CTA, radius dan ukuran teks. Default resource mengikuti global; nonaktifkan **Ikuti styling global** untuk override individual.

Perubahan global berlaku pada resource inherit tanpa republish setiap resource. Restore harga tidak mengembalikan styling global. Preview arsip memakai styling global saat ini. Nilai CSS dibatasi dan seluruh CSS di-scope ke container plugin.

Tombol **artikel / halaman** menampilkan penggunaan shortcode pada post/page, termasuk synced blocks. Indeks diperbarui lewat hook save/trash/restore/delete. Rebuild berjalan per batch; tanda `?` berarti indeks belum lengkap. Penulis tidak dapat melihat artikel privat di luar izinnya.

Metadata page builder, widget, dan template PHP belum dideteksi. Referensi synced block putus/siklik ditandai untuk admin. Jumlah penggunaan adalah jumlah post unik, bukan jumlah kemunculan shortcode.

## Cache dan data

Publish/restore memakai transaksi InnoDB, row lock, revision/base check, snapshot append-only, dan idempotency key. Tidak ada cache pointer/HTML internal lintas request. Media pada snapshot berupa referensi, bukan salinan backup berkas; gambar yang hilang menghasilkan placeholder.

Page cache/CDN memerlukan integrasi situs melalui hook:

```php
add_action('lpm_invalidate_resource_cache', function ($resource_id, $resource_type, $version_id, $event_id) {
    // Purge halaman yang memakai resource ini melalui provider cache situs.
    // Wajib idempotent. Throw exception jika gagal agar event dicoba ulang.
}, 10, 4);
```

Hook berjalan setelah commit. Kegagalan purge tidak membatalkan publikasi; event tetap tersimpan dan dicoba ulang. Worker cache memproses maksimal 20 event/batch dengan WP-Cron hourly atau tombol retry. Situs tanpa trafik cukup memerlukan system cron untuk menjalankan WP-Cron. Tidak ada provider CDN/cache pihak ketiga yang otomatis diklaim terintegrasi.

Deaktivasi/uninstall mempertahankan resource, draft, dan history. Tidak ada fitur penghapusan permanen data dalam rilis ini. Jangan mengubah langsung tabel versi melalui SQL.

## Verifikasi

Lihat `VALIDATION.md` pada proyek sumber untuk hasil tes dan batas pemeriksaan. Pengujian mencakup WordPress HTTP dengan login/cookie/nonce asli; pemeriksaan visual browser v2 belum berhasil karena koneksi otomasi browser tidak merespons. Tetap uji tampilan pada tema/plugin situs tujuan sebelum mengandalkannya untuk harga publik.

License: GPL-2.0-or-later. Tidak ada build npm/Composer yang diperlukan untuk memasang plugin.
