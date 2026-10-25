# Layanan Pesanan Warung Seblak Ngakak

**Sistem Layanan Pesanan Warung Seblak Ngakak** adalah sebuah layanan backend berbasis Laravel yang berfungsi sebagai otak dari proses pemesanan di Warung Seblak Ngakak. Sistem ini menyediakan REST API untuk menampilkan daftar menu, menerima pesanan pelanggan, menyimpan pesanan beserta rincian itemnya ke dalam database, serta menerbitkan event ke RabbitMQ agar proses lanjutan seperti pencatatan atau pemrosesan pesanan dapat berjalan secara asinkron. Tujuan utama dibangunnya sistem ini adalah menyediakan satu sumber data pesanan yang terpusat dan dapat dipercaya, sekaligus memisahkan proses pencatatan pesanan (yang harus cepat merespons pelanggan) dari proses-proses lain yang tidak harus selesai saat itu juga. Dengan pemisahan ini, ketika pelanggan mengirim pesanan, API dapat langsung merespons dengan cepat, sementara pengolahan lanjutan dikirim melalui mekanisme event yang andal.

## Anggota Kelompok dan Pembagian Tugas

| Nama | NIM | Tanggung Jawab |
|------|-----|----------------|
| Diva Anastasya | 1224026 | Layanan Pembukuan (consumer, jurnal penjualan, dashboard) |
| Dira Andrianti Rahmah | 1224006 | Analisis proses bisnis & dokumen analisis |
| Nur Alifah Anggraeni | 1224021 | Layanan Dapur (consumer, antrian masak) |
| Nadjlah Cahya Nerik Fadilah | 3224001 | Layanan Pesanan (API, stok, publisher RabbitMQ), kontrak event RabbitMQ |

## Arsitektur Layanan

Proyek ini terdiri atas tiga layanan independen yang terhubung melalui REST (sinkron) dan RabbitMQ (asinkron):

| Layanan | Folder | Peran |
|---------|--------|-------|
| Layanan Pesanan (Kasir) | `layanan-pesanan` | REST API, mengelola menu **dan stok**, menerbitkan event `order.created` |
| Layanan Dapur | `layanan-dapur` | Consumer, menyimpan antrian masak |
| Layanan Pembukuan | `layanan-pembukuan` | Consumer, mencatat jurnal penjualan |


## Latar Belakang dan Tujuan

Warung Seblak Ngakak menjual menu yang sangat beragam, mulai dari kerupuk, bakso, aneka mi, sayuran, hingga topping pelengkap dengan harga yang bervariasi. Pada pengelolaan manual, pesanan dicatat satu per satu dan harga dihitung dengan tangan, sehingga rawan salah hitung dan sulit dilacak kembali. Karena itu, dikembangkan sistem layanan pesanan ini untuk mengotomatisasi validasi menu, menghitung total harga, menyimpan data transaksi secara rapi, serta menghasilkan catatan peristiwa (event) yang dapat dikonsumsi oleh layanan lain tanpa mengganggu jalannya pemesanan.

Pengembangan menggunakan **Laravel** karena menyediakan struktur MVC yang jelas, ORM Eloquent untuk mengelola relasi tabel, validasi request yang rapi, serta ekosistem pengujian yang matang. **Docker Sail** dipilih sebagai lingkungan pengembangan agar seluruh anggota kelompok bekerja di lingkungan yang identik tanpa perlu memasang PHP, MySQL, dan RabbitMQ secara manual di komputer masing-masing. **RabbitMQ** digunakan untuk pola komunikasi berbasis event (publish–subscribe), yaitu ketika pesanan berhasil dibuat, sistem menerbitkan event `order.created`. Pendekatan berbasis event dipilih agar pengirim pesanan tidak terikat (decoupled) dengan pihak yang memproses pesanan tersebut; penambahan konsumen baru di masa depan tidak menuntut perubahan pada kode pembuat pesanan.

## Arsitektur dan Alur Kerja Sistem

Secara keseluruhan, alur sistem dapat dibayangkan sebagai sebuah rantai yang menghubungkan klien, API, database, message broker, dan consumer. Berikut penjelasan alurnya secara bertahap:

1. **Permintaan dari klien.** Pengguna mengirim permintaan melalui Swagger UI atau alat seperti cURL/Postman ke endpoint REST API. Klien memanggil, misalnya, `POST /api/v1/pesanan` dengan data pelanggan dan daftar item yang dipesan.
2. **Validasi request.** Controller menerima permintaan dan memvalidasi kelengkapan data (nama pelanggan wajib diisi, daftar item minimal berisi satu item, setiap item harus menyertakan kode menu dan jumlah minimal satu). Bila data tidak valid, API langsung mengembalikan respons galat tanpa menyentuh database.
3. **Perhitungan dan validasi menu.** Untuk setiap item, sistem mencari menu di database berdasarkan kode menu. Menu yang tidak ditemukan atau berstatus tidak tersedia akan menolak proses pemesanan. Bila valid, sistem menghitung subtotal (jumlah dikali harga satuan) serta menjumlahkan seluruh item menjadi total harga.
4. **Penyimpanan transaksi.** Seluruh proses penyimpanan dibungkus dalam satu transaksi database. Pesanan utama disimpan pada tabel `orders`, kemudian setiap item disimpan pada tabel `order_items` yang berelasi ke pesanan induk. Transaksi memastikan bahwa pesanan tidak akan tersimpan setengah jadi bila terjadi kegagalan di tengah proses.
5. **Penerbitan event.** Setelah pesanan dan itemnya berhasil tersimpan, `OrderPublisher` membuat pesan berformat JSON yang memuat nama event, data pesanan, dan waktu kejadian, lalu mempublikasikannya ke exchange RabbitMQ dengan routing key `order.created`.
6. **Distribusi oleh broker.** Exchange `seblak.events` bertipe `topic` mengarahkan pesan ke queue `seblak.orders` karena routing key-nya cocok. RabbitMQ menyimpan pesan tersebut secara persisten hingga ada konsumen yang mengambilnya.
7. **Pemrosesan oleh consumer.** Perintah `rabbitmq:consume-orders` menjalankan consumer yang menunggu pesan pada queue. Ketika pesan datang, consumer menampilkan isinya, lalu mengakui pesan tersebut dengan ACK. Bila terjadi kegagalan pemrosesan, pesan ditolak (NACK) agar tidak hilang secara diam-diam.

Dengan urutan ini, pengalaman pengguna tetap cepat—karena respons API hanya menunggu hingga penyimpanan selesai—sedangkan proses lanjutan dikerjakan melalui jalur event yang terpisah.

## Teknologi yang Digunakan

Sistem ini dibangun di atas beberapa teknologi utama yang saling melengkapi:

| Teknologi | Fungsi dalam Sistem |
|-----------|---------------------|
| Laravel | Framework utama untuk membangun REST API, routing, validasi, ORM, dan pengujian. |
| Laravel Sail | Lingkungan pengembangan berbasis Docker agar PHP, MySQL, dan RabbitMQ konsisten di semua anggota tim. |
| PHP | Bahasa pemrograman tempat seluruh logika aplikasi ditulis. |
| MySQL | Database relasional tempat tabel `menus`, `orders`, dan `order_items` disimpan. |
| RabbitMQ | Message broker yang menerima event dari publisher dan mengantarkannya ke consumer. |
| Swagger (L5-Swagger) | Menyediakan antarmuka dokumentasi API yang interaktif. |
| Docker Desktop (WSL) | Menjalankan seluruh container layanan (Laravel, MySQL, RabbitMQ). |

## Struktur Folder dan File Penting

Berikut bagian-bagian penting dari struktur proyek beserta perannya, disusun sesuai kebutuhan pembaca agar mudah memahami letak logika utama:

```
layanan-pesanan/
├── app/
│   ├── Console/Commands/OrderConsumer.php      # Perintah artisan consumer RabbitMQ
│   ├── Events/OrderCreated.php                 # Representasi event order.created
│   ├── Http/Controllers/Api/
│   │   ├── MenuController.php                  # API menu versi lama
│   │   ├── OrderController.php                 # API pesanan versi lama
│   │   └── V1/
│   │       ├── MenuController.php              # API menu versi 1
│   │       └── PesananController.php           # API pesanan versi 1 (create, list, detail, ubah status)
│   ├── Models/
│   │   ├── Menu.php                            # Model menu
│   │   ├── Order.php                           # Model pesanan induk
│   │   └── OrderItem.php                       # Model rincian item pesanan
│   ├── OpenApi/
│   │   ├── OpenApiSpec.php                     # Metadata dokumentasi OpenAPI
│   │   └── PesananApi.php                      # Anotasi endpoint untuk Swagger
│   └── Services/OrderPublisher.php             # Pengirim event ke RabbitMQ
├── config/rabbitmq.php                         # Konfigurasi koneksi RabbitMQ
├── database/
│   ├── migrations/                             # Definisi skema tabel
│   └── seeders/MenuSeeder.php                  # Data awal menu warung
├── routes/
│   ├── api.php                                 # Definisi seluruh route API
│   └── openapi.yaml                            # Spesifikasi OpenAPI (design-first)
├── tests/Feature/PesananApiTest.php            # Pengujian fitur pembuatan pesanan
├── compose.yaml                                # Definisi layanan Docker Sail
└── README.md                                   # Dokumentasi proyek (berkas ini)
```

## Dokumentasi Endpoint API

Seluruh endpoint tersedia di bawah prefiks `/api/v1`. Tabel berikut merangkum endpoint yang benar-benar terdaftar pada `routes/api.php` beserta fungsi dan contoh pemakaiannya.

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/menu` | Menampilkan seluruh daftar menu yang tersedia |
| POST | `/api/v1/pesanan` | Membuat pesanan baru |
| GET | `/api/v1/pesanan` | Menampilkan semua pesanan |
| GET | `/api/v1/pesanan/{nomor}` | Menampilkan detail satu pesanan |
| POST | `/api/v1/pesanan/{nomor}/perubahan-status` | Mengubah status pesanan |

**1. Menampilkan daftar menu** — `GET /api/v1/menu`

Tidak memerlukan parameter. Contoh respons:

```json
{
  "data": [
    {
      "kode": "MIN001",
      "nama_menu": "Air Mineral",
      "kategori": "minuman",
      "harga": "3000.00",
      "satuan": "botol",
      "tersedia": true
    }
  ]
}
```

**2. Membuat pesanan** — `POST /api/v1/pesanan`

Request body wajib memuat `nama_pelanggan` (wajib, maksimal 255 karakter), `items` (wajib, minimal satu item), serta opsional `metode_pembayaran`. Setiap item memerlukan `kode_menu` (wajib, harus ada di tabel menu) dan `jumlah` (wajib, bilangan bulat minimal 1). Contoh request:

```json
{
  "nama_pelanggan": "Nadya",
  "metode_pembayaran": "QRIS",
  "items": [
    { "kode_menu": "MIN001", "jumlah": 2 }
  ]
}
```

Contoh respons berhasil (HTTP 201):

```json
{
  "message": "Pesanan berhasil dibuat.",
  "data": {
    "id": 17,
    "nama_pelanggan": "Nadya",
    "nama_menu": "Air Mineral",
    "jumlah": 2,
    "harga": "6000.00",
    "total_harga": "6000.00",
    "metode_pembayaran": "QRIS",
    "status": "diterima",
    "items": [
      {
        "id": 1,
        "menu_id": 1,
        "nama_menu": "Air Mineral",
        "jumlah": 2,
        "harga_satuan": "3000.00",
        "subtotal": "6000.00"
      }
    ]
  }
}
```

Bila data tidak valid, API mengembalikan HTTP 422 dengan daftar kesalahan validasi. Menu yang tidak ditemukan atau sedang tidak tersedia akan menolak pemesanan dengan pesan yang sesuai.

**3. Menampilkan semua pesanan** — `GET /api/v1/pesanan`

Mengembalikan seluruh pesanan beserta itemnya, diurutkan dari yang terbaru. Contoh respons:

```json
{
  "data": [
    {
      "id": 17,
      "nama_pelanggan": "Nadya",
      "status": "diterima",
      "items": []
    }
  ]
}
```

**4. Menampilkan detail pesanan** — `GET /api/v1/pesanan/{nomor}`

Membutuhkan parameter path `nomor` berupa ID pesanan. Bila pesanan tidak ditemukan, API mengembalikan HTTP 404 dengan pesan `Pesanan tidak ditemukan.`

**5. Mengubah status pesanan** — `POST /api/v1/pesanan/{nomor}/perubahan-status`

Request body memuat `status` dengan nilai salah satu dari `dimasak`, `siap`, `selesai`, atau `dibatalkan`. Perubahan status mengikuti alur yang disepakati: `diterima` dapat berubah menjadi `dimasak` atau `dibatalkan`; `dimasak` menjadi `siap`; dan `siap` menjadi `selesai`. Bila perpindahan status tidak sesuai alur, API mengembalikan HTTP 409 beserta keterangan status saat ini dan status yang diminta.

## Proses Pembuatan Pesanan Secara Rinci

Pembuatan pesanan merupakan inti dari sistem ini dan menjalankan beberapa langkah yang saling bergantung. Pertama, controller `PesananController::store` memvalidasi isi request melalui mekanisme validasi Laravel. Jika lolos, seluruh proses penyimpanan dijalankan di dalam `DB::transaction`, sehingga perubahan bersifat atomik. Di dalam transaksi tersebut, setiap item dipetakan ke menu pada tabel `menus` berdasarkan `kode` dan status ketersediaan. Menu yang tidak valid langsung menstrukturkan galat validasi. Untuk setiap item yang valid, sistem menghitung `subtotal` dari jumlah dikalikan harga satuan dan mengakumulasikannya ke `total_harga`.

Selanjutnya, `Order::create` menyimpan pesanan induk dengan kolom seperti `nama_pelanggan`, gabungan nama menu, jumlah total, harga, `total_harga`, metode pembayaran, dan status awal `diterima`. Setelah pesanan induk tersimpan, setiap item disimpan melalui relasi `items()` ke tabel `order_items`. Baru setelah transaksi selesai dan data tersimpan, `OrderPublisher::publish` dipanggil dengan membawa objek `OrderCreated` yang berisi data pesanan. Dengan urutan ini, event hanya diterbitkan untuk pesanan yang benar-benar sudah tersimpan di database.

## Konfigurasi RabbitMQ

Semua pengaturan koneksi RabbitMQ diambil dari berkas `config/rabbitmq.php` yang membaca nilai dari environment. Host default diarahkan ke nama service Docker `rabbitmq`, dengan port `5672`, dan kredensial disimpan pada berkas `.env` yang **tidak dicantumkan** dalam dokumentasi ini karena bersifat rahasia. Komponen RabbitMQ yang digunakan pada proyek ini saling terhubung sebagai berikut:

| Komponen | Nilai |
|----------|-------|
| Exchange | `seblak.events` (tipe `topic`, durable) |
| Routing key | `order.created` |
| Queue Kasir | `seblak.orders` (durable) |
| Queue Dapur | `dapur.orders` (durable, milik layanan Dapur) |
| Queue Pembukuan | `pembukuan.orders` (durable, milik layanan Pembukuan) |
| Dead letter | `seblak.dlx` + `pembukuan.orders.dlq` |
| Publisher | `App\Services\OrderPublisher` |

Setiap layanan memiliki **queue sendiri** yang terikat pada exchange yang sama (pola *publish–subscribe*). Dengan begitu, satu pesanan dapat diproses oleh Kasir, Dapur, dan Pembukuan sekaligus tanpa saling berebut pesan.

Publisher mendeklarasikan exchange secara idempoten (aman dipanggil berulang), mengaktifkan **publisher confirms** agar aplikasi dapat mengetahui apakah broker benar-benar menerima pesan, lalu mempublikasikan pesan yang bersifat `persistent`. Consumer mendeklarasikan queue dan binding yang sama, menerapkan Quality of Service (QoS) satu pesan sekaligus, dan mengakui setiap pesan dengan ACK setelah berhasil diproses. Bila pemrosesan gagal, pesan ditolak dengan NACK dan kegagalannya dicatat pada log agar tidak hilang tanpa jejak.

## Kontrak Event

Jalur asinkron menggunakan satu event yang menghubungkan Layanan Pesanan (penerbit) dengan Layanan Dapur dan Layanan Pembukuan (penerima).

| Atribut | Nilai |
|---------|-------|
| Nama event | `order.created` (bentuk lampau) |
| Exchange | `seblak.events` (tipe `topic`, durable) |
| Routing key | `order.created` |
| Penerbit | Layanan Pesanan (`App\Services\OrderPublisher`) |
| Penerima | `dapur.orders` (Dapur), `pembukuan.orders` (Pembukuan) |
| Format isi | JSON, `content_type: application/json`, `delivery_mode: persistent` |

Pesan yang dipublikasikan mengikuti format event yang tetap:

```json
{
  "event": "order.created",
  "data": {
    "id": 36,
    "nama_pelanggan": "Budi",
    "nama_menu": "Ceker, Kerupuk Putih",
    "jumlah": 5,
    "harga": "8000.00",
    "total_harga": "8000.00",
    "metode_pembayaran": "tunai",
    "status": "diterima",
    "items": [
      {
        "id": 30,
        "order_id": 36,
        "menu_id": 16,
        "nama_menu": "Ceker",
        "jumlah": 3,
        "harga_satuan": "2000.00",
        "subtotal": "6000.00"
      }
    ]
  },
  "occurred_at": "2026-10-21T06:59:02.000000Z"
}
```

Field beserta tipenya:

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `event` | string | Nama event (`order.created`) |
| `data.id` | integer | ID pesanan |
| `data.nama_pelanggan` | string | Nama pelanggan |
| `data.nama_menu` | string | Gabungan nama menu |
| `data.jumlah` | integer | Total jumlah item |
| `data.harga` | string (decimal) | Total harga |
| `data.total_harga` | string (decimal) | Total harga |
| `data.metode_pembayaran` | string | Metode pembayaran |
| `data.status` | string | Status awal (`diterima`) |
| `data.items` | array | Rincian item pesanan |
| `data.items[].menu_id` | integer | ID menu |
| `data.items[].nama_menu` | string | Nama menu item |
| `data.items[].jumlah` | integer | Jumlah item |
| `data.items[].harga_satuan` | string (decimal) | Harga satuan |
| `data.items[].subtotal` | string (decimal) | Subtotal item |
| `occurred_at` | string (ISO-8601) | Waktu kejadian |

Penerima memakai `data.id` sebagai **kunci idempoten** sehingga pesan yang dikirim ulang broker tidak dicatat dua kali.

## Instalasi dan Menjalankan Proyek

Untuk menjalankan proyek dari awal, pastikan **Docker Desktop** dan **WSL** sudah terpasang di komputer. Langkah-langkahnya adalah sebagai berikut:

```bash
# 1. Masuk ke folder proyek
cd ~/layanan-pesanan

# 2. Siapkan berkas environment
cp .env.example .env

# 3. Jalankan seluruh container (Laravel, MySQL, RabbitMQ)
./vendor/bin/sail up -d

# 4. Jalankan migrasi database dan seeder menu
./vendor/bin/sail artisan migrate --seed
```

Setelah container berjalan, consumer RabbitMQ dijalankan pada terminal terpisah agar dapat terus menunggu pesan tanpa menghentikan proses lain:

```bash
./vendor/bin/sail artisan rabbitmq:consume-orders
```

Consumer akan menampilkan pesan bahwa sistem sedang menunggu pesanan, dan akan menampilkan isi pesan setiap kali ada event `order.created` yang masuk.

## Cara Mengakses Aplikasi

Setelah container berjalan, beberapa antarmuka berikut dapat dibuka melalui peramban:

| Layanan | Alamat | Keterangan |
|---------|--------|-----------|
| Swagger UI | http://localhost:8082/api/documentation | Dokumentasi API interaktif |
| RabbitMQ Management | http://localhost:15672 | Pemantauan exchange, queue, dan pesan |
| REST API | http://localhost:8082/api/v1/... | Endpoint utama layanan |

Kredensial RabbitMQ Management tidak dicantumkan pada dokumentasi ini karena bersifat rahasia.

## Panduan Pengujian melalui Swagger

Untuk membuktikan bahwa pesanan benar-benar diproses dari ujung ke ujung, ikuti langkah berikut:

1. Pastikan consumer sudah berjalan pada terminal terpisah dengan perintah `./vendor/bin/sail artisan rabbitmq:consume-orders`.
2. Buka Swagger UI di http://localhost:8082/api/documentation.
3. Pilih endpoint `POST /api/v1/pesanan`, lalu tekan **Try it out**.
4. Isi request body menggunakan data menu yang benar-benar tersedia, misalnya:

```json
{
  "nama_pelanggan": "Nadya",
  "metode_pembayaran": "QRIS",
  "items": [
    { "kode_menu": "MIN001", "jumlah": 2 }
  ]
}
```

5. Tekan **Execute** dan periksa bahwa API mengembalikan HTTP 201 beserta data pesanan.
6. Beralih ke terminal consumer, dan pastikan isi pesanan muncul pada layar. Kemunculan pesan ini menandakan bahwa event `order.created` berhasil melewati exchange, queue, hingga diterima consumer.

Bila pesan tidak muncul, pastikan hanya ada **satu** consumer yang berjalan agar pesan tidak terbagi ke beberapa consumer secara acak. Untuk menghentikan consumer dengan bersih, gunakan:

```bash
./vendor/bin/sail exec laravel.test pkill -f rabbitmq:consume-orders
```

## Pengujian Otomatis

Proyek menyediakan pengujian otomatis berbasis PHPUnit. Pengujian fitur pada `tests/Feature/PesananApiTest.php` memeriksa empat skenario: pembuatan pesanan yang valid harus menghasilkan HTTP 201 dan menyimpan data pada tabel `orders` serta `order_items`; pembuatan pesanan tanpa nama pelanggan harus menghasilkan HTTP 422; stok menu harus berkurang sesuai jumlah yang dipesan; dan pesanan harus ditolak dengan HTTP 422 bila stok tidak mencukupi (stok tidak berubah). Pada pengujian ini, `OrderPublisher` digantikan oleh objek tiruan (mock) sehingga pengujian tidak bergantung pada RabbitMQ.

Perintah untuk menjalankan seluruh pengujian:

```bash
./vendor/bin/sail artisan test
```

Perlu dipahami bahwa pengujian fitur di atas **berbeda** dengan pengujian integrasi RabbitMQ. Pengujian fitur memverifikasi logika API dan penyimpanan database tanpa broker, sedangkan verifikasi jalur event secara nyata dilakukan melalui pengujian manual seperti dijelaskan pada bagian panduan Swagger, yaitu dengan mengamati pesan yang diterima consumer.

## Penutup

Sistem Layanan Pesanan Warung Seblak Ngakak menyatukan proses pemesanan yang cepat dengan pemrosesan lanjutan yang andal melalui pola komunikasi berbasis event. API menyediakan satu jalur masuk yang tervalidasi untuk setiap pesanan, database menyimpannya secara transaksional dan konsisten, sementara RabbitMQ memastikan peristiwa pesanan dapat dikonsumsi oleh layanan lain tanpa membebani proses pemesanan itu sendiri. Dengan lingkungan pengembangan berbasis Docker Sail, dokumentasi API yang interaktif, serta pengujian otomatis yang tersedia, proyek ini menjadi fondasi yang rapi untuk pengembangan layanan pesanan maupun perluasan integrasi ke sistem lain di masa mendatang.
