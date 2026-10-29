# Perbaikan penyimpanan 2.0.1

Laporan: tabel ID #3 Latiseducation Nasional menampilkan pesan draft/publish sukses, tetapi setelah reload isi kembali kosong, production belum ada, dan history kosong. Nama dan ID masih tersedia.

## Temuan yang dibuktikan lokal

Versi 2.0.0 memeriksa draft di dalam transaksi, menjalankan COMMIT, kemudian langsung mengembalikan sukses. Tidak ada pemeriksaan independen setelah commit. Transaksi menggunakan koneksi wpdb yang juga digunakan komponen WordPress lain; pembacaan editor melalui wpdb dapat melalui filter/drop-in berbeda dari SQL native penulisan.

Simulasi rollback sebelum commit pada source 2.0.0 menghasilkan respons V1 berhasil dengan production null dan history kosong. Simulasi ini membuktikan celah pengakuan sukses; tidak membuktikan penyebab rollback atau konfigurasi salah di hosting pengguna. Penyebab spesifik situs tutorindonesia.co.id belum dapat diperiksa langsung.

SQL native juga melewati filter penghapusan placeholder persen milik wpdb::query. Versi ini menghapus placeholder secara eksplisit agar teks seperti `diskon 10%` tersimpan utuh.

## Perubahan

1. Koneksi khusus ke database yang ditentukan DB_HOST menangani transaksi dan pembacaan resource/snapshot/history. Tidak ada reconnect atau pengulangan query otomatis di tengah transaksi.
2. Setelah commit, koneksi baru memeriksa draft/revision/metadata atau snapshot/operation key/production/nomor versi/cache event sebelum respons `persisted: true` diberikan. Ketidakcocokan menghasilkan HTTP 503 `lpm_persistence_unverified`.
3. Editor membaca semua nilai form sebelum save, lalu GET terpisah memeriksa ulang revision dan konten. Cadangan lokal tidak dihapus bila hasil reload tidak cocok.
4. Publish menyimpan identitas operasi sampai hasil reload membuktikan production tersedia. Retry menggunakan identitas sama untuk menghindari duplikasi versi. Respons yang belum terverifikasi tidak mengganti isi lokal dengan data kosong.

Tidak ada perubahan skema database pada hotfix ini. ID resource, shortcode, dan snapshot lama dipertahankan. Perbaikan tidak merekonstruksi harga yang sebelumnya tidak tersimpan; fixture pengujian memakai nilai sintetis, bukan harga asli pengguna.

## Validasi dan batasnya

Fixture 10 jenjang dengan tiga harga per jenjang diuji melalui service/database, editor JavaScript dengan DOM minimal, dan HTTP WordPress normal memakai login cookie/nonce. Draft, judul publik, production V1, dan history bertahan pada pembacaan berikutnya. Regresi versioning, konflik dua proses, restore, cache retry, serta fitur v2 juga dijalankan ulang; hasil ada pada VALIDATION.md di proyek sumber.

Runtime pengujian: WordPress 6.8.3, PHP 8.1.10 mysqli/mysqlnd, MySQL 8.0.30/InnoDB. Routing database khusus melalui drop-in, multisite, MariaDB, dan tampilan visual situs pengguna belum diuji. Koneksi baru memakai konstanta database WordPress; lingkungan dengan routing khusus perlu diuji pada konfigurasinya.

Paket ini belum dipasang atau diverifikasi pada tutorindonesia.co.id. Koneksi otomasi browser tidak tersedia saat pengerjaan. Unggah ZIP 2.0.1 untuk mengganti folder plugin yang sama, buka kembali ID #3, lalu simpan dan publish pada resource tersebut; shortcode artikel tetap sama.
