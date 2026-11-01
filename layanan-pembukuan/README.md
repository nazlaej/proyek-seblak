# Layanan Pembukuan Warung Seblak Ngakak

**Sistem Layanan Pembukuan Warung Seblak Ngakak** adalah sebuah layanan backend berbasis Laravel yang berfungsi sebagai pencatat keuangan otomatis. Sama seperti Layanan Dapur, layanan ini tidak menyediakan REST API untuk klien, melainkan bertindak sebagai **consumer** yang mendengarkan event `order.created` dari RabbitMQ. Setiap pesanan yang diterbitkan oleh Layanan Pesanan akan diterjemahkan menjadi satu baris jurnal penjualan, sehingga pembukuan warung tercatat rapi tanpa perlu input manual.

## Anggota Kelompok dan Pembagian Tugas

| Nama | NIM | Tanggung Jawab |
|------|-----|----------------|
| Nadjlah Cahya Nerik Fadilah | 3224001 | Layanan Pesanan dan REST API: endpoint, validasi, perhitungan harga, perubahan status, serta respons API. |
| Nur Alifah Anggraeni | 1224021 | Integrasi RabbitMQ dan Outbox: kontrak event, publisher, retry manual melalui `outbox:publish`, serta pengujian kegagalan broker dan pengiriman ulang. |
| Diva Anastasya | 1224026 | Layanan Pembukuan dan pengujian consumer: jurnal, dashboard, duplikasi pesan, serta pesan rusak/DLQ. |
| Dira Andrianti Rahmah | 1224006 | Dokumen analisis dan alur Dapur: penyelarasan diagram, perkembangan Tugas 3, consumer Dapur, serta pencatatan hasil uji alur pesanan. |

## Latar Belakang dan Tujuan

Pencatatan penjualan secara manual rawan salah jumlah, terlewat, dan sulit ditelusuri kembali ketika pemilik ingin mengetahui pendapatan harian. Layanan Pembukuan hadir untuk mengotomatisasi pencatatan: begitu kasir berhasil membuat pesanan, catatan penjualan langsung tersimpan di jurnal keuangan tanpa campur tangan manusia. Karena pencatatan berjalan melalui jalur event yang terpisah, proses pemesanan di kasir tetap cepat dan tidak terganggu oleh pekerjaan pembukuan.

Layanan ini menggunakan pola komunikasi **publish–subscribe** melalui RabbitMQ. Layanan Pesanan bertindak sebagai publisher, sedangkan Layanan Pembukuan dan layanan lain (misalnya Dapur) masing-masing memiliki queue sendiri yang terikat pada exchange yang sama. Pesanan yang sama karena itu dapat dicatat oleh Pembukuan sekaligus dimasak oleh Dapur tanpa saling berebut pesan.

## Arsitektur dan Alur Kerja Sistem

Secara keseluruhan, alur yang melibatkan Layanan Pembukuan adalah sebagai berikut:

1. **Kasir membuat pesanan.** Layanan Pesanan menerima permintaan, memvalidasi menu, memeriksa stok, menyimpan pesanan, lalu menerbitkan event `order.created` ke RabbitMQ.
2. **Distribusi oleh broker.** Exchange `seblak.events` bertipe `topic` mengarahkan pesan ke setiap queue yang terikat dengan routing key `order.created`, termasuk queue `pembukuan.orders` milik Layanan Pembukuan.
3. **Penerimaan oleh consumer.** Perintah `pembukuan:konsumsi` menjalankan consumer yang menunggu pesan pada queue `pembukuan.orders`.
4. **Pencatatan ke jurnal.** Setiap pesanan divalidasi, lalu dicatat sebagai satu baris pada tabel `jurnal_penjualan`. Handler bersifat idempoten agar pesan yang dikirim ulang tidak tercatat dua kali.
5. **Pengakuan pesan.** Setelah pencatatan berhasil, pesan diakui dengan ACK. Bila pesan tidak valid, pesan ditolak dan dikirim ke dead letter queue.

## Teknologi yang Digunakan

| Teknologi | Fungsi dalam Sistem |
|-----------|---------------------|
| Laravel | Framework utama untuk command artisan, ORM Eloquent, dan struktur aplikasi. |
| Laravel Sail | Lingkungan pengembangan berbasis Docker agar PHP dan MySQL konsisten. |
| PHP | Bahasa pemrograman tempat seluruh logika aplikasi ditulis. |
| MySQL | Database relasional tempat tabel `jurnal_penjualan` disimpan. |
| RabbitMQ | Message broker yang mengantarkan event `order.created` dari Layanan Pesanan. |
| php-amqplib | Pustaka PHP untuk terhubung dan mengonsumsi pesan dari RabbitMQ. |
| Docker Desktop (WSL) | Menjalankan seluruh container layanan (Laravel dan MySQL). |

## Struktur Folder dan File Penting

```
layanan-pembukuan/
├── app/
│   ├── Console/Commands/KonsumsiPenjualan.php  # Perintah artisan consumer RabbitMQ
│   ├── Messaging/RabbitMQ.php                  # Koneksi, exchange, queue, dan DLQ
│   ├── Models/JurnalPenjualan.php              # Model jurnal penjualan
│   └── Pembukuan/
│       ├── CatatPenjualan.php                  # Logika pencatatan (idempoten)
│       └── PesanTidakValid.php                 # Pengecualian untuk pesan tidak valid
├── config/rabbitmq.php                         # Konfigurasi koneksi RabbitMQ
├── database/migrations/                        # Definisi skema tabel jurnal_penjualan
├── compose.yaml                                # Definisi layanan Docker Sail
└── README.md                                   # Dokumentasi proyek (berkas ini)
```

## Model Data Jurnal Penjualan

Setiap pesanan yang dicatat menghasilkan satu baris pada tabel `jurnal_penjualan`:

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint unsigned | Kunci utama. |
| `message_id` | string (unik) | Kunci idempoten: satu pesan = satu baris jurnal. |
| `nama_menu` | string(100) | Nama menu yang terjual. |
| `jumlah` | unsignedInteger | Jumlah item terjual. |
| `harga_satuan` | unsignedBigInteger | Harga per satuan. |
| `total` | unsignedBigInteger | Hasil kali jumlah dengan harga satuan. |
| `terjadi_pada` | timestamp | Waktu pesanan terjadi. |
| `created_at`, `updated_at` | timestamp | Waktu pembuatan dan pembaruan data. |

## Konfigurasi RabbitMQ

Semua pengaturan koneksi RabbitMQ diambil dari berkas `config/rabbitmq.php`. Layanan Pembukuan **tidak menjalankan RabbitMQ sendiri**; ia terhubung ke RabbitMQ milik Layanan Pesanan melalui jaringan Docker eksternal. Komponen yang digunakan adalah:

Contoh `.env` memakai `APP_PORT=8084`, `FORWARD_DB_PORT=3311`, dan `VITE_PORT=5177` agar tidak berbenturan dengan Pesanan atau Dapur. Fallback koneksi RabbitMQ di `config/rabbitmq.php` disamakan dengan broker Pesanan.

| Komponen | Nilai |
|----------|-------|
| Exchange | `seblak.events` (tipe `topic`, durable) |
| Queue | `pembukuan.orders` (durable, khusus Pembukuan) |
| Dead letter exchange | `seblak.dlx` (tipe `fanout`, durable) |
| Dead letter queue | `pembukuan.orders.dlq` |
| Routing key | `order.created` |
| Consumer | Perintah artisan `pembukuan:konsumsi` |

Queue `pembukuan.orders` dikonfigurasi dengan `x-dead-letter-exchange` menuju `seblak.dlx`. Pesan yang tidak valid dikumpulkan pada `pembukuan.orders.dlq` agar tidak hilang dan tetap dapat diperiksa.

Pesan yang dikonsumsi mengikuti format event yang tetap:

```json
{
  "event_id": "02745a26-52cf-4bda-94d2-76d594fd321e",
  "event": "order.created",
  "data": { },
  "occurred_at": "2026-10-21T02:24:00.081070Z"
}
```

## Pencatatan yang Idempoten

Pencatatan penjualan dirancang aman terhadap pengiriman ulang. Outbox mengirim `event_id` pada payload dan sebagai properti AMQP `message_id`; consumer memakai ID itu sebagai kunci jurnal. Jika menerima event lama tanpa ID, consumer memakai `order-{id}`. Kolom `message_id` unik dan `firstOrCreate` mencegah satu event tercatat dua kali. Pesan tidak valid diarahkan ke dead letter queue.

Pembatalan pesanan belum menerbitkan event kompensasi. Jurnal yang sudah dibuat tetap ada dan tidak berubah otomatis saat pesanan dibatalkan di Layanan Pesanan.

## Instalasi dan Menjalankan Proyek

Pastikan **Docker Desktop** dan **WSL** sudah terpasang, serta container Layanan Pesanan (termasuk RabbitMQ) sudah berjalan. Langkah menjalankan:

```bash
# 1. Masuk ke folder proyek
cd ~/layanan-pembukuan

# 2. Buat berkas environment hanya jika belum ada
test -f .env || cp .env.example .env

# 3. Pasang dependensi sesuai composer.lock
composer install --no-interaction --prefer-dist

# 4. Jalankan seluruh container (Laravel dan MySQL)
./vendor/bin/sail up -d

# 5. Jalankan migrasi database
./vendor/bin/sail artisan migrate
```

Untuk mulai mencatat penjualan, jalankan consumer pada terminal terpisah:

```bash
./vendor/bin/sail artisan pembukuan:konsumsi
```

Consumer akan menampilkan pesan bahwa pembukuan sedang menunggu, dan menampilkan setiap penjualan yang dicatat.

Saat consumer mulai, ia mendeklarasikan queue durable `pembukuan.orders` dan binding `order.created`. Jika event masih tertunda karena RabbitMQ sebelumnya tidak tersedia, jalankan retry **manual** dari folder `layanan-pesanan` dengan `./vendor/bin/sail artisan outbox:publish`. Retry otomatis terjadwal belum tersedia.

## Panduan Pengujian Ujung ke Ujung

Jalankan feature test dengan basis data SQLite dalam memori:

```bash
./vendor/bin/sail artisan test
```

Pengujian `tests/Feature/CatatPenjualanTest.php` memeriksa pencatatan idempoten tanpa RabbitMQ.

Untuk membuktikan bahwa penjualan benar-benar tercatat, ikuti langkah berikut:

1. Pastikan consumer Pembukuan sudah berjalan dengan `./vendor/bin/sail artisan pembukuan:konsumsi`.
2. Buat pesanan melalui Layanan Pesanan (Swagger UI di `http://localhost:8082/api/documentation` atau cURL).
3. Amati terminal consumer Pembukuan; harus muncul baris seperti `Dicatat : {...}`.
4. Periksa isi jurnal pada tabel `jurnal_penjualan`; satu pesanan harus menghasilkan satu baris baru.

Bila pesan tidak muncul, pastikan hanya ada **satu** consumer yang berjalan agar pesan tidak terbagi secara acak. Untuk menghentikan consumer dengan bersih:

```bash
./vendor/bin/sail exec laravel.test pkill -f pembukuan:konsumsi
```

## Cara Mengakses dan Memeriksa

| Bagian | Cara Memeriksa |
|--------|----------------|
| Consumer | Terminal tempat `pembukuan:konsumsi` berjalan |
| Tabel jurnal | `docker exec layanan-pembukuan-mysql-1 mysql -usail -ppassword -e "SELECT * FROM jurnal_penjualan;" laravel` |
| RabbitMQ Management | http://localhost:15672 (lihat queue `pembukuan.orders`) |

Kredensial RabbitMQ Management tidak dicantumkan pada dokumentasi ini karena bersifat rahasia.

## Penutup

Layanan Pembukuan Warung Seblak Ngakak menutup rantai pemesanan dengan mencatat setiap penjualan secara otomatis dan andal. Dengan memanfaatkan queue tersendiri beserta dead letter queue, layanan ini memastikan tidak ada penjualan yang terlewat maupun tercatat ganda, sementara proses pemesanan di kasir tetap berjalan cepat. Pola ini memperlihatkan bagaimana beberapa layanan independen dapat bekerja sama di atas satu aliran event yang sama.
