# Layanan Dapur Warung Seblak Ngakak

**Sistem Layanan Dapur Warung Seblak Ngakak** adalah sebuah layanan backend berbasis Laravel yang berfungsi sebagai dapur digital pada Warung Seblak Ngakak. Layanan ini tidak menyediakan REST API untuk klien, melainkan bertindak sebagai **consumer** yang mendengarkan event `order.created` dari RabbitMQ. Setiap pesanan yang diterbitkan oleh Layanan Pesanan akan diterima, disimpan sebagai antrian masak, dan ditampilkan pada layar dapur. Dengan cara ini, dapur bekerja berdasarkan pesanan yang benar-benar telah dicatat oleh kasir, tanpa perlu menunggu permintaan HTTP satu per satu.

## Anggota Kelompok dan Pembagian Tugas

| Nama | NIM | Tanggung Jawab |
|------|-----|----------------|
| Diva Anastasya | 1224026 | Layanan pembukuan dan pengujian consumer |
| Dira Andrianti Rahmah | 1224006 | Dokumen analisis dan alur dapur |
| Nur Alifah Anggraeni | 1224021 | Integrasi RabbitMQ dan outbox |
| Nadjlah Cahya Nerik Fadilah | 3224001 | Layanan pesanan dan REST API |

## Latar Belakang dan Tujuan

Pada alur prasmanan Warung Seblak Ngakak, pelanggan memilih topping di depan kasir, kasir menghitung total dan memeriksa ketersediaan stok, lalu pesanan diteruskan ke dapur untuk dimasak. Tanpa sistem, pesanan yang sampai ke dapur bergantung pada teriakan atau catatan kertas yang mudah hilang atau terlewat. Layanan Dapur ini hadir untuk memastikan setiap pesanan yang sah dari kasir otomatis muncul di dapur sebagai antrian masak, sehingga tidak ada pesanan yang terlewat dan urutan masak dapat terjaga.

Layanan ini menggunakan pola komunikasi **publish–subscribe** melalui RabbitMQ. Layanan Pesanan bertindak sebagai publisher, sedangkan Layanan Dapur dan layanan lain (misalnya Pembukuan) masing-masing memiliki queue sendiri yang terikat pada exchange yang sama. Karena setiap layanan memiliki queue tersendiri, pesanan yang sama dapat diproses oleh Dapur dan Pembukuan secara bersamaan tanpa saling berebut pesan.

## Arsitektur dan Alur Kerja Sistem

Secara keseluruhan, alur yang melibatkan Layanan Dapur adalah sebagai berikut:

1. **Kasir membuat pesanan.** Layanan Pesanan menerima permintaan `POST /api/v1/pesanan`, memvalidasi menu, memeriksa ketersediaan stok, mengurangi stok, menyimpan pesanan, lalu menerbitkan event `order.created` ke RabbitMQ.
2. **Distribusi oleh broker.** Exchange `seblak.events` bertipe `topic` mengarahkan pesan ke setiap queue yang terikat dengan routing key `order.created`, termasuk queue `dapur.orders` milik Layanan Dapur.
3. **Penerimaan oleh consumer.** Perintah `dapur:consume-orders` menjalankan consumer yang menunggu pesan pada queue `dapur.orders`.
4. **Penyimpanan ke antrian masak.** Setelah pesan diterima, data pesanan disimpan ke tabel `antrian_dapur` dengan status awal `menunggu`, lalu ditampilkan pada layar dapur.
5. **Pengakuan pesan.** Setelah penyimpanan berhasil, pesan diakui dengan ACK. Bila terjadi kegagalan, pesan ditolak dengan NACK agar tidak hilang secara diam-diam.

Perlu ditegaskan bahwa **dapur tidak mengelola stok**. Stok sepenuhnya menjadi tanggung jawab kasir (Layanan Pesanan), karena pelanggan memilih topping dan mengetahui ketersediaannya di depan kasir. Dapur hanya memasak apa yang sudah dipilih dan lolos dari pemeriksaan stok.

## Teknologi yang Digunakan

| Teknologi | Fungsi dalam Sistem |
|-----------|---------------------|
| Laravel | Framework utama untuk command artisan, ORM Eloquent, dan struktur aplikasi. |
| Laravel Sail | Lingkungan pengembangan berbasis Docker agar PHP dan MySQL konsisten. |
| PHP | Bahasa pemrograman tempat seluruh logika aplikasi ditulis. |
| MySQL | Database relasional tempat tabel `antrian_dapur` disimpan. |
| RabbitMQ | Message broker yang mengantarkan event `order.created` dari Layanan Pesanan. |
| php-amqplib | Pustaka PHP untuk terhubung dan mengonsumsi pesan dari RabbitMQ. |
| Docker Desktop (WSL) | Menjalankan seluruh container layanan (Laravel dan MySQL). |

## Struktur Folder dan File Penting

```
layanan-dapur/
├── app/
│   ├── Console/Commands/DapurConsumer.php      # Perintah artisan consumer RabbitMQ
│   └── Models/AntrianDapur.php                 # Model antrian masak
├── config/rabbitmq.php                         # Konfigurasi koneksi RabbitMQ
├── database/
│   └── migrations/                             # Definisi skema tabel (antrian_dapur)
├── compose.yaml                                # Definisi layanan Docker Sail
└── README.md                                   # Dokumentasi proyek (berkas ini)
```

## Model Data Antrian Dapur

Setiap pesanan yang masuk disimpan pada tabel `antrian_dapur`. Struktur kolomnya adalah sebagai berikut:

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | bigint unsigned | Kunci utama. |
| `order_id` | integer | ID pesanan asal dari Layanan Pesanan. |
| `nama_pelanggan` | string | Nama pelanggan yang memesan. |
| `detail_item` | json | Daftar item pesanan (menu dan jumlah). |
| `status` | string | Status pengerjaan, diawali `menunggu`. |
| `created_at`, `updated_at` | timestamp | Waktu pembuatan dan pembaruan data. |

## Konfigurasi RabbitMQ

Semua pengaturan koneksi RabbitMQ diambil dari berkas `config/rabbitmq.php` yang membaca nilai dari environment. Layanan Dapur **tidak menjalankan RabbitMQ sendiri**; ia terhubung ke RabbitMQ milik Layanan Pesanan melalui jaringan Docker eksternal `layanan-pesanan_sail`. Nama host `rabbitmq` merujuk pada container RabbitMQ tersebut. Komponen yang digunakan adalah:

| Komponen | Nilai |
|----------|-------|
| Exchange | `seblak.events` (tipe `topic`, durable) |
| Queue | `dapur.orders` (durable, khusus Dapur) |
| Routing key | `order.created` |
| Consumer | Perintah artisan `dapur:consume-orders` |

Setiap layanan memakai queue tersendiri yang terikat pada exchange yang sama. Queue `dapur.orders` hanya milik Dapur sehingga pesanan yang sama tetap dapat diproses juga oleh Layanan Pesanan (`seblak.orders`) dan Layanan Pembukuan (`pembukuan.orders`) tanpa konflik.

Pesan yang dikonsumsi mengikuti format event yang tetap:

```json
{
  "event": "order.created",
  "data": { },
  "occurred_at": "2026-10-21T02:24:00.081070Z"
}
```

Consumer menerapkan Quality of Service (QoS) satu pesan sekaligus, dan mengakui setiap pesan dengan ACK hanya setelah penyimpanan ke database berhasil. Bila pemrosesan gagal, pesan ditolak dengan NACK dan kegagalannya dicatat pada log.

## Instalasi dan Menjalankan Proyek

Pastikan **Docker Desktop** dan **WSL** sudah terpasang, serta container Layanan Pesanan (termasuk RabbitMQ) sudah berjalan. Langkah menjalankan:

```bash
# 1. Masuk ke folder proyek
cd ~/layanan-dapur

# 2. Siapkan berkas environment
cp .env.example .env

# 3. Jalankan seluruh container (Laravel dan MySQL)
./vendor/bin/sail up -d

# 4. Jalankan migrasi database
./vendor/bin/sail artisan migrate
```

Untuk mulai menunggu pesanan, jalankan consumer pada terminal terpisah:

```bash
./vendor/bin/sail artisan dapur:consume-orders
```

Consumer akan menampilkan pesan bahwa dapur sedang menunggu, dan menampilkan setiap pesanan yang masuk.

## Panduan Pengujian Ujung ke Ujung

Untuk membuktikan bahwa pesanan benar-benar sampai ke dapur, ikuti langkah berikut:

1. Pastikan consumer Dapur sudah berjalan dengan `./vendor/bin/sail artisan dapur:consume-orders`.
2. Buat pesanan melalui Layanan Pesanan (Swagger UI di `http://localhost:8082/api/documentation` atau cURL) menggunakan kode menu yang tersedia.
3. Amati terminal consumer Dapur; harus muncul baris seperti `PESANAN MASUK KE DAPUR: pesanan #36 atas nama ... (1 item)`.
4. Pesanan juga tersimpan pada tabel `antrian_dapur`, dan akan tercatat pula oleh Layanan Pembukuan karena memakai queue yang berbeda.

Bila pesan tidak muncul, pastikan hanya ada **satu** consumer yang berjalan agar pesan tidak terbagi secara acak. Untuk menghentikan consumer dengan bersih:

```bash
./vendor/bin/sail exec laravel.test pkill -f dapur:consume-orders
```

## Cara Mengakses dan Memeriksa

| Bagian | Cara Memeriksa |
|--------|----------------|
| Consumer | Terminal tempat `dapur:consume-orders` berjalan |
| Tabel antrian | `docker exec layanan-dapur-mysql-1 mysql -usail -ppassword -e "SELECT * FROM antrian_dapur;" laravel` |
| RabbitMQ Management | http://localhost:15672 (lihat queue `dapur.orders`) |

Kredensial RabbitMQ Management tidak dicantumkan pada dokumentasi ini karena bersifat rahasia.

## Penutup

Layanan Dapur Warung Seblak Ngakak menunjukkan bagaimana sebuah layanan dapat bekerja murni berdasarkan event tanpa menyediakan API bagi klien. Dengan memanfaatkan queue tersendiri pada exchange yang sama, Dapur dapat memproses setiap pesanan yang diterbitkan kasir secara asinkron, terpisah, dan andal, sementara tanggung jawab stok tetap berada di tangan kasir. Pola ini membuat penambahan consumer baru di kemudian hari dapat dilakukan tanpa mengubah Layanan Pesanan.
