# Catatan Verifikasi Checkpoint 1

Tanggal pemeriksaan: 1 November 2026
Branch pada pemeriksaan awal: `perbaikan-checkpoint-1`
Branch integrasi: `integrasi-checkpoint-1` (berbasis `origin/main` `e0da0b5`)

## Hasil

- Kapasitas disk (`df -h / /mnt/c /mnt/d`): filesystem virtual Ubuntu `/` berukuran 1007G dengan 950G tersedia; drive Windows C memiliki 16G tersedia dari 135G; drive Windows D memiliki 27G tersedia dari 103G. Angka `/` bukan kapasitas aktual drive D.
- Image Docker yang sudah tersedia: `sail-8.5/app`, MySQL 8.4, RabbitMQ 4.3 Management, dan image Spectral. Layanan tidak dibangun ulang.
- Dapur dan Pembukuan tidak memiliki `.env` saat pemeriksaan awal. File lokal keduanya dibuat dari `.env.example`; `.env` Pesanan dipertahankan. Port Dapur disetel ke 8083/3310/5176 dan Pembukuan ke 8084/3311/5177. Koneksi database dan RabbitMQ diselaraskan tanpa mencatat nilai rahasia di dokumen ini.
- Template `.env.example` ketiga layanan dilengkapi dengan konfigurasi MySQL/port lokal. `.env` Pesanan tidak diubah.
- Dependensi Dapur dan Pembukuan dipasang dengan `composer install --no-interaction --prefer-dist`: masing-masing 115 dan 114 paket, 0 update, 0 penghapusan. `composer.lock` tidak diperbarui.
- Feature test memakai SQLite dalam memori terpisah dari database layanan:
  - Hasil awal pada 1 November: Pesanan 9 tes/45 assertion, Dapur 5 tes/8 assertion, Pembukuan 8 tes/14 assertion.
- Layanan Dapur awalnya belum memiliki tabel/volume lama. Migrasi biasa membuat tabel dan indeks unik `order_id`.
- Database Pembukuan lama memiliki volume `layanan-pembukuan_sail-mysql`, 0 baris jurnal, dan tabel dasar dengan kolom `sku`. Container aplikasi sebelumnya terpasang ke folder saudara di luar repository. Container aplikasi diganti tanpa menghapus volume, lalu migrasi kolom `sku` ke `nama_menu` dijalankan. Nama file migrasi dasar diselaraskan dengan nama yang sudah tercatat pada tabel `migrations`. Pemeriksaan kompatibilitas tambahan memakai SQLite in-memory: migrasi baru mengadopsi tabel lama dan mempertahankan satu baris uji melewati `up()`/`down()`; pengujian suite juga membuat skema dari nol di SQLite in-memory. `down()` baseline sengaja tidak menghapus jurnal agar migrasi yang mengadopsi data lama aman saat rollback.
- Spectral dijalankan dari image lokal dengan repository read-only dan jaringan dimatikan. Temuan awal `info.contact` duplikat diperbaiki. Lint akhir: **0 error Spectral**.
- Swagger UI `/api/documentation` menjawab HTTP 200 dan menunjuk ke `/docs?api-docs.yaml`. URL tersebut menjawab HTTP 200 (`application/yaml`), berisi 4 path, 5 operasi, dan media type `application/problem+json`.
- HTTP langsung ke API aktif: GET detail pesanan yang tidak ada menghasilkan 404 `application/problem+json`; POST kosong menghasilkan 422 dengan media type yang sama.
- Queue RabbitMQ dan binding dicek sebelum pesanan demo. `dapur.orders` dan `pembukuan.orders` durable, masing-masing memiliki satu consumer, dan terikat ke `seblak.events` dengan routing key `order.created`.
- Pesanan demo bertanda `DEMO-CP1-20261101-70888b`, ID 1: 2 Air Mineral × Rp8.000 = **Rp16.000**. Log consumer Dapur menunjukkan pesanan masuk. Pembukuan mencatat satu jurnal dengan jumlah 2 dan total 16000.
- Payload event asli ditangkap melalui queue audit sementara, lalu dipublikasikan ulang apa adanya. Broker mengonfirmasi `routed=true`; consumer Dapur menampilkan “duplikat dilewati”, Pembukuan menampilkan “Dilewati”. Query setelah replay menunjukkan satu baris Dapur dan satu baris jurnal Pembukuan.
- RabbitMQ awalnya tidak memiliki volume pada Compose. Pemeriksaan vhost `/` saat itu tidak menemukan queue/binding. Volume bernama `sail-rabbitmq` ditambahkan; broker sehat dan volume terpasang. Pesan durable/persistent bertahan selama volume tidak dihapus.
- Tidak ada database atau volume yang dihapus, tidak ada `migrate:fresh`, dan tidak ada push/merge.
- Sebuah file SQLite lokal `layanan-pembukuan/testing` tercipta saat percobaan tes sebelum koneksi PHPUnit dipaksa ke SQLite dalam memori. Pemeriksaan read-only menemukan tabel kerangka Laravel dan `jurnal_penjualan`, semuanya tanpa baris data (jurnal 0 baris). File dibiarkan utuh dan dicantumkan di `.gitignore`; pengujian akhir memakai `:memory:`.

## Risiko dan batasan

- Jika volume RabbitMQ `layanan-pesanan_sail-rabbitmq` dihapus, queue dan event yang masih mengantre ikut hilang.
- Outbox tersedia pada branch integrasi: order dan event dicatat satu transaksi. Retry saat ini manual melalui `outbox:publish`; belum dijadwalkan otomatis.
- Consumer Dapur melakukan NACK tanpa requeue dan belum memiliki DLQ. Kegagalan pemrosesan dicatat, tetapi pesan gagal tidak ditahan di DLQ.
- Compose Pembukuan melaporkan container RabbitMQ lama sebagai orphan; container dan volumenya dibiarkan utuh. Compose Pembukuan terkini tidak menjalankan broker sendiri.
- Laporan final pilihan pengguna adalah `docs/analisis/Laporan-Dokumen_Analisis_Warung-Seblak.pdf` (13 halaman). DOCX dengan nama dasar yang sama tidak ditemukan; DOCX lama bukan pasangan final. Pertanyaan fakta mitra yang belum dikonfirmasi dicantumkan di laporan final.

## Verifikasi integrasi dari `origin/main`

- Perubahan lokal diamankan di Git stash `pre-integration checkpoint 1 local work 2026-11-01` dan di cadangan permanen `/home/nur/cadangan-proyek-seblak/seblak-local-backup-20261101-195327`. `.env` disalin terpisah dengan izin terbatas dan tidak dilacak.
- Problem Details lokal, kontrak OpenAPI/Swagger, konfigurasi tes SQLite, dan volume RabbitMQ dipertahankan. `OrderOutbox` dipakai oleh API V1 dan endpoint lama `POST /api/orders` agar cocok dengan tipe `OrderPublisher` baru.
- Pembatalan mengunci pesanan, memeriksa status, mengembalikan stok, dan memperbarui status dalam satu transaksi. Pembatalan ganda mendapat 409 dan tidak menambah stok lagi. Tidak ada event pembatalan lintas layanan; antrean Dapur dan jurnal Pembukuan tidak berubah otomatis.
- Tes terbaru dengan database SQLite `:memory:`: Pesanan **12 tes/69 assertion**, Dapur **7 tes/21 assertion**, Pembukuan **9 tes/18 assertion**; seluruhnya lulus.
- Uji Outbox menggunakan database SQLite `:memory:` dan broker sementara tanpa volume. Publish saat port broker tidak tersedia meninggalkan event pending; setelah broker pulih, perintah manual `outbox:publish` mengirim tepat satu pesan pada queue uji durable. Payload `event_id` sama dengan properti AMQP `message_id`.
- Tes callback Dapur menerima envelope event lengkap, meng-ACK pesan baru dan duplikat, serta mempertahankan satu antrean. Tes Pembukuan mengirim ulang event dengan `event_id` yang sama dan mempertahankan satu jurnal.
- Sebelum migrasi Dapur, ledger menunjukkan `2026_11_01_000000_add_unique_order_id_to_antrian_dapur` sudah Ran dan indeks unik `antrian_dapur_order_id_unique` sudah ada. Migrasi remote `2026_10_31_000001_add_unique_to_antrian_dapur` dijalankan biasa dengan pengecekan indeks, tercatat Ran tanpa membuat indeks kedua. Setelahnya tetap ada satu indeks unik `order_id`.
- Pemeriksaan migrasi Pembukuan sebelum demo: ledger menunjukkan `2026_10_12_082633_create_jurnal_penjualan_table` Pending, sedangkan migrasi 19 dan 21 Oktober Ran. Tabel aktual sudah memakai `nama_menu`, memiliki indeks unik `message_id`, serta satu jurnal senilai Rp16.000. Migrasi 12 Oktober semula membuat tabel tanpa guard; diperbaiki agar mengadopsi skema yang dikenal tanpa menulis ulang jurnal, dan `down()` tidak menjatuhkan tabel yang mungkin diadopsi. Uji SQLite terpisah membuktikan adopsi skema `sku` dan `nama_menu`, pelestarian baris melewati `up()`/`down()`, serta rangkaian instalasi baru.
- Migrasi biasa Pembukuan melalui Sail kemudian mencatat migrasi 12 Oktober sebagai Ran (batch 3). Setelahnya tabel tetap memiliki satu jurnal, total Rp16.000, ID 1, dan fingerprint data yang sama sebelum/sesudah (`1468887275`). Migrasi 19 dan 21 Oktober tetap Ran. Tidak ada penghapusan atau perubahan ledger manual.
- Pemeriksaan Dapur setelahnya menunjukkan kedua migrasi indeks (`2026_10_31_000001` dan `2026_11_01_000000`) berstatus Ran, tetapi hanya ada satu indeks unik untuk `order_id` (`antrian_dapur_order_id_unique`). Tabel memiliki satu baris dengan satu `order_id` unik. Tidak ada rollback atau penghapusan indeks.
- Migrasi Outbox Pesanan berstatus Pending dan tabel belum ada; migrasi biasa membuat `outbox_messages`. Tidak ada `migrate:fresh`, penghapusan database/volume, atau perubahan data pesanan lama.
- Spectral: tidak ada error. Swagger UI menjawab HTTP 200, kontrak YAML HTTP 200, dan file yang disajikan sama dengan `storage/api-docs/api-docs.yaml`.
- RabbitMQ uji dihentikan setelah pemeriksaan; container dibuat tanpa volume. Broker dan volume aplikasi tetap dipertahankan.

## Perintah utama yang dijalankan

```bash
composer install --no-interaction --prefer-dist
./vendor/bin/phpunit
./vendor/bin/sail artisan migrate --no-interaction
```

Spectral dijalankan dari image Docker lokal. Queue, volume, migration status, dan isi data demo diperiksa dengan query baca-saja setelah perubahan.

## Replay pesanan lama sebelum demo — 1 November 2026

- Target: order **ID 2**, pelanggan **Nurr**, **2 Es Cokelat**, total **Rp16.000**; `event_id=d982435a-ee26-488d-8536-b30a43b45514`.
- Pemeriksaan awal baca-saja menemukan satu baris Outbox `order.created` dengan payload yang sesuai. `published_at=2026-11-01 15:07:08`, `attempts=1`, `last_error=null`. Tabel orders berisi 2 pesanan; stok `MIN015` bernilai 18.
- Sebelum publish, `dapur.orders` dan `pembukuan.orders` adalah queue durable, masing-masing memiliki satu consumer aktif, dan keduanya terikat pada `seblak.events` dengan routing key `order.created`. Kedua queue kosong. Binding masih ada pada pemeriksaan akhir.
- Payload Outbox diterbitkan ulang tepat satu kali secara langsung ke exchange, memakai `event_id` dan properti AMQP `message_id` yang sama. Publisher confirm diterima dan pesan tidak dikembalikan oleh routing. Perintah ini tidak memanggil publisher Outbox dan tidak menulis ke tabel Outbox.
- Hasil baca-saja sesudah replay: antrean Dapur untuk `order_id=2` tetap **1** (status `menunggu`); jurnal Pembukuan untuk `message_id` tersebut tetap **1** (jumlah 2, total 16000); stok `MIN015` tetap **18**; jumlah orders tetap **2** dan order ID 2 tetap satu.
- Outbox tetap memiliki `published_at=2026-11-01 15:07:08`, `attempts=1`, dan `last_error=null`. Setelah consumer menerima replay, kedua queue kembali menunjukkan 0 pesan dengan satu consumer aktif masing-masing. Tidak ada pesanan baru atau perubahan status yang dilakukan.

## Pemeriksaan akhir dokumentasi dan konfigurasi tes — 2 November 2026

- Laporan final yang dipilih adalah `docs/analisis/Laporan-Dokumen_Analisis_Warung-Seblak.pdf`, judul metadata “Dokumen Analisis Proyek Akhir - Integrasi Layanan Pesanan Warung Seblak Ngakak”, 13 halaman, 742.145 byte. PDF valid dan memiliki teks yang dapat diekstrak. DOCX dengan nama dasar yang sama tidak ditemukan.
- Dokumen lama `makalah-analisis-checkpoint-1.docx`, PDF lama, dan generatornya dipindahkan ke `/home/nur/cadangan-proyek-seblak/arsip-makalah-lama-20261102`. Tidak ada skrip pembuat laporan yang dijalankan ulang. `docs/analisis/__pycache__` yang kosong dihapus; PDF final pilihan tetap di repositori.
- P03 - Kelompok Itera.pdf tidak ditemukan saat pencarian pada `/home/nur` dan arsip permanen yang tersedia. Ini hanya menyatakan hasil pencarian di lokasi tersebut.
- `.env.testing` Pesanan dibersihkan dari nilai yang cocok dengan `.env` operasional. Konfigurasinya kini memakai APP_ENV testing, SQLite `:memory:`, serta kredensial tes; `.env` operasional tidak diubah. PHPUnit memaksa SQLite `:memory:` dan hasil tes setelah perubahan **12 tes/69 assertion lulus**.
