# Layanan Pesanan Warung Seblak Ngakak

**Sistem Layanan Pesanan Warung Seblak Ngakak** menyediakan REST API untuk menu dan pesanan. Pesanan, item, dan event Outbox ditulis dalam satu transaksi. Setelah transaksi berhasil, API mencoba mengirim event ke RabbitMQ; jika broker gagal, event tetap tertunda dan dapat dicoba ulang secara manual.

## Anggota Kelompok dan Pembagian Tugas

| Nama | NIM | Tanggung Jawab |
|------|-----|----------------|
| Nadjlah Cahya Nerik Fadilah | 3224001 | Layanan Pesanan dan REST API: endpoint, validasi, perhitungan harga, perubahan status, serta respons API. |
| Nur Alifah Anggraeni | 1224021 | Integrasi RabbitMQ dan Outbox: kontrak event, publisher, retry manual melalui `outbox:publish`, serta pengujian kegagalan broker dan pengiriman ulang. |
| Diva Anastasya | 1224026 | Layanan Pembukuan dan pengujian consumer: jurnal, dashboard, duplikasi pesan, serta pesan rusak/DLQ. |
| Dira Andrianti Rahmah | 1224006 | Dokumen analisis dan alur Dapur: penyelarasan diagram, perkembangan Tugas 3, consumer Dapur, serta pencatatan hasil uji alur pesanan. |

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
5. **Pencatatan outbox.** Di dalam transaksi database yang sama dengan pesanan dan itemnya, aplikasi menyimpan event `order.created` ke tabel `outbox_messages`. Event tetap tercatat walaupun RabbitMQ sedang tidak tersedia.
6. **Penerbitan dan retry.** Setelah transaksi berhasil, `OrderPublisher` mencoba mengirim event persisten ke exchange RabbitMQ memakai publisher confirms dan routing mandatory. Baris outbox ditandai terkirim hanya jika broker mengonfirmasi pesan yang memiliki tujuan queue. Jika gagal, baris tetap tertunda dan dapat dicoba ulang dengan `php artisan outbox:publish`.
7. **Distribusi oleh broker.** Setiap layanan penerima mengikat queue miliknya ke exchange dengan routing key `order.created`. Consumer contoh pada layanan pesanan menggunakan queue `seblak.orders`.
8. **Pemrosesan oleh consumer.** Perintah `rabbitmq:consume-orders` menjalankan consumer lokal. Consumer contoh ini menampilkan isi pesan dan memberi ACK. Consumer produksi perlu menyelesaikan pekerjaan bisnis sebelum ACK dan menangani duplikasi berdasarkan `event_id`.

Pesanan dan event outbox commit bersama sebelum publisher mencoba mengirim. API menunggu satu percobaan publish dan konfirmasi broker, jadi RabbitMQ yang lambat dapat menambah waktu respons. Jika RabbitMQ gagal, transaksi pesanan tetap tersimpan dan event dapat dikirim ulang tanpa membuat pesanan baru.

## Teknologi yang Digunakan

Sistem ini dibangun di atas beberapa teknologi utama yang saling melengkapi:

| Teknologi | Fungsi dalam Sistem |
|-----------|---------------------|
| Laravel | Framework utama untuk membangun REST API, routing, validasi, ORM, dan pengujian. |
| Laravel Sail | Lingkungan pengembangan berbasis Docker agar PHP, MySQL, dan RabbitMQ konsisten di semua anggota tim. |
| PHP | Bahasa pemrograman tempat seluruh logika aplikasi ditulis. |
| MySQL | Database relasional tempat tabel `menus`, `orders`, `order_items`, dan `outbox_messages` disimpan. |
| RabbitMQ | Message broker yang menerima event dari publisher dan mengantarkannya ke consumer. |
| Swagger (L5-Swagger) | Menyediakan antarmuka dokumentasi API yang interaktif. |
| Docker Desktop (WSL) | Menjalankan seluruh container layanan (Laravel, MySQL, RabbitMQ). |

## Struktur Folder dan File Penting

Berikut bagian-bagian penting dari struktur proyek beserta perannya, disusun sesuai kebutuhan pembaca agar mudah memahami letak logika utama:

```
layanan-pesanan/
├── app/
│   ├── Console/Commands/OrderConsumer.php      # Consumer demonstrasi RabbitMQ
│   ├── Console/Commands/PublishOutboxMessages.php # Retry event tertunda
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
│   │   ├── OrderItem.php                       # Model rincian item pesanan
│   │   └── OutboxMessage.php                   # Model event outbox
│   ├── OpenApi/
│   │   ├── OpenApiSpec.php                     # Metadata dokumentasi OpenAPI
│   │   └── PesananApi.php                      # Anotasi endpoint untuk Swagger
│   └── Services/OrderPublisher.php             # Pengirim outbox ke RabbitMQ
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

Kontrak design-first ada di `routes/openapi.yaml`. Swagger UI L5-Swagger menyajikan `storage/api-docs/api-docs.yaml`; setelah mengubah kontrak, salin ulang berkas sumber ke salinan yang disajikan lalu jalankan lint Spectral. Route lama `GET /api/menus` dan `POST /api/orders` tetap tersedia untuk kompatibilitas.

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
      "tersedia": true,
      "stok": 20
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
        "order_id": 17,
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

Bila data tidak valid atau stok tidak cukup, API mengembalikan `application/problem+json` sesuai Problem Details. Validasi menyertakan ekstensi `errors` per field. Detail pesanan yang tidak ditemukan menghasilkan 404 Problem Details; transisi status yang tidak diizinkan menghasilkan 409 dengan `status_sekarang` dan `status_diminta`.

Contoh respons validasi:

```json
{
  "type": "about:blank",
  "title": "Data tidak valid",
  "status": 422,
  "detail": "Nama pelanggan wajib diisi.",
  "instance": "/api/v1/pesanan",
  "errors": {"nama_pelanggan": ["Nama pelanggan wajib diisi."]}
}
```

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

Membutuhkan parameter path `nomor` berupa ID pesanan. Bila pesanan tidak ditemukan, API mengembalikan HTTP 404 `application/problem+json`.

**5. Mengubah status pesanan** — `POST /api/v1/pesanan/{nomor}/perubahan-status`

Request body memuat `status` dengan nilai `dimasak`, `siap`, `selesai`, atau `dibatalkan`. Perubahan status pesanan diperiksa dan dikunci dalam transaksi. Pembatalan mengembalikan stok satu kali dalam transaksi yang sama; pengulangan pembatalan ditolak dengan HTTP 409. Saat ini pembatalan belum menerbitkan event, sehingga antrean Dapur dan jurnal Pembukuan tidak diberi tahu dan tidak berubah otomatis.

## Proses Pembuatan Pesanan Secara Rinci

Pembuatan pesanan merupakan inti dari sistem ini dan menjalankan beberapa langkah yang saling bergantung. Pertama, controller `PesananController::store` memvalidasi isi request melalui mekanisme validasi Laravel. Jika lolos, seluruh proses penyimpanan dijalankan di dalam `DB::transaction`, sehingga perubahan bersifat atomik. Di dalam transaksi tersebut, setiap item dipetakan ke menu pada tabel `menus` berdasarkan `kode` dan status ketersediaan. Menu yang tidak valid langsung menstrukturkan galat validasi. Untuk setiap item yang valid, sistem menghitung `subtotal` dari jumlah dikalikan harga satuan dan mengakumulasikannya ke `total_harga`.

Selanjutnya, `Order::create` menyimpan pesanan induk dan setiap item disimpan melalui relasi `items()` ke tabel `order_items`. Dalam transaksi database yang sama, aplikasi membuat baris `outbox_messages` berisi `event_id`, nama event, payload JSON, dan waktu kejadian. Setelah commit, `OrderPublisher::publish` mencoba mengirim payload tersebut ke RabbitMQ. Bila pengiriman gagal, API tetap dapat merespons pesanan yang berhasil tersimpan dan event tetap berstatus tertunda untuk retry.

## Konfigurasi RabbitMQ

Semua pengaturan koneksi RabbitMQ diambil dari berkas `config/rabbitmq.php` yang membaca nilai dari environment. Host default diarahkan ke nama service Docker `rabbitmq`, dengan port `5672`, dan kredensial disimpan pada berkas `.env` yang **tidak dicantumkan** dalam dokumentasi ini karena bersifat rahasia. Komponen RabbitMQ yang digunakan pada proyek ini saling terhubung sebagai berikut:

| Komponen | Nilai |
|----------|-------|
| Exchange | `seblak.events` (tipe `topic`, durable) |
| Routing key | `order.created` |
| Queue demo Pesanan | `seblak.orders` (durable, opsional) |
| Queue Dapur | `dapur.orders` (durable, milik layanan Dapur) |
| Queue Pembukuan | `pembukuan.orders` (durable, milik layanan Pembukuan) |
| Outbox | Tabel `outbox_messages` pada database layanan pesanan |
| Publisher | `App\Services\OrderPublisher` |

Compose Pesanan memasang volume `sail-rabbitmq:/var/lib/rabbitmq`. Pesan yang masih antre bertahan saat container dibuat ulang selama volume dipertahankan; menghapus volume akan menghapus data broker yang tersimpan.

Target arsitektur memakai **queue sendiri untuk setiap layanan** yang terikat pada exchange yang sama (*publish–subscribe*). Satu event dapat diterima oleh Kasir, Dapur, dan Pembukuan secara independen. Dalam repo layanan pesanan ini, consumer demonstrasi hanya mendeklarasikan queue `seblak.orders`; queue dan binding Dapur serta Pembukuan harus tersedia di repo layanan masing-masing.

Publisher mendeklarasikan exchange secara idempoten, mengaktifkan **publisher confirms**, dan mengirim pesan `persistent` dengan routing mandatory. Event dicatat di outbox bersama transaksi pesanan. Penerimaan broker ditandai pada `published_at`; kegagalan koneksi, konfirmasi, atau routing dicatat pada `last_error` dan dapat dicoba ulang. Penerima perlu menggunakan `event_id` sebagai kunci deduplikasi karena retry dapat mengirim event lebih dari sekali.

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
| ID pesan | `event_id` pada payload dan properti AMQP `message_id` |
| Properti AMQP `type` | `order.created` |

Pesan yang dipublikasikan mengikuti format event yang tetap:

```json
{
  "event_id": "02745a26-52cf-4bda-94d2-76d594fd321e",
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
| `event_id` | string (UUID) | Identitas event yang stabil untuk deduplikasi |
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

`event_id` tetap sama pada setiap retry. Setiap consumer yang menyimpan efek bisnis perlu mencatat ID event yang sudah diproses agar pengiriman ulang tidak menggandakan efek tersebut.

### Pola Outbox dan pemulihan pesan

Tabel `orders`, `order_items`, dan `outbox_messages` ditulis dalam satu transaksi database. Setelah transaksi commit, aplikasi mencoba mengirim event ke RabbitMQ. Bila koneksi gagal, pesanan tetap tersimpan dan baris event tidak dihapus atau ditandai terkirim. Jalankan perintah ini untuk mengirim ulang event tertunda:

```bash
./vendor/bin/sail artisan outbox:publish
```

Retry saat ini dijalankan **manual** dengan perintah tersebut; belum ada scheduler otomatis. Perintah menerima opsi `--limit=100`. Jika proses mati setelah RabbitMQ menerima pesan tetapi sebelum database menyimpan `published_at`, retry dapat menghasilkan duplikasi. Karena itu, consumer harus idempoten. Outbox memastikan event tercatat bersama transaksi dan dapat dikirim ulang, tetapi tidak menjamin pemrosesan tepat-satu-kali.

## Setup, Consumer, Tes, dan Retry

> Jalankan Pesanan lebih dahulu karena RabbitMQ berada pada Compose Pesanan dan menjadi jaringan bersama Dapur serta Pembukuan. Perintah berikut memakai `composer install` berdasarkan `composer.lock`; jangan gunakan `composer update`. Salin `.env.example` hanya bila `.env` belum ada, lalu isi konfigurasi lokal secara manual tanpa menimpa file yang sudah dipakai.

### 1. Layanan Pesanan dan RabbitMQ

```bash
cd /path/ke/proyek-seblak/layanan-pesanan
composer install --no-interaction --prefer-dist
test -f .env || cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

Untuk database Pesanan yang benar-benar baru dan kosong, isi menu awal satu kali dengan `./vendor/bin/sail artisan db:seed --class=MenuSeeder`. Jangan jalankan seeder ulang pada database yang sudah berisi menu.

### 2. Layanan Dapur dan Pembukuan

Pada terminal baru untuk setiap layanan, jalankan perintah dari folder layanannya. Contoh Dapur:

```bash
cd /path/ke/proyek-seblak/layanan-dapur
composer install --no-interaction --prefer-dist
test -f .env || cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

Ulangi dengan `layanan-pembukuan` untuk Pembukuan. Kedua layanan tersambung ke jaringan RabbitMQ eksternal milik Pesanan; jalankan RabbitMQ Pesanan lebih dahulu.

Jalankan satu consumer per layanan pada terminal terpisah:

```bash
# dari folder layanan-dapur
./vendor/bin/sail artisan dapur:consume-orders

# dari folder layanan-pembukuan
./vendor/bin/sail artisan pembukuan:konsumsi
```

Consumer mendeklarasikan queue durable dan binding `order.created` saat mulai. `rabbitmq:consume-orders` pada Layanan Pesanan adalah consumer observasi opsional di queue `seblak.orders`; consumer bisnis yang menyimpan antrean dan jurnal tetap dijalankan dari folder Dapur dan Pembukuan.

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

1. Pastikan consumer `dapur:consume-orders` dan `pembukuan:konsumsi` sudah berjalan pada terminal masing-masing.
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
6. Periksa terminal consumer Dapur dan Pembukuan; masing-masing harus menerima event `order.created` yang sama melalui queue berbeda.

Bila event tertunda karena broker gagal, retry Outbox dilakukan **manual** dari folder Pesanan:

```bash
./vendor/bin/sail artisan outbox:publish
```

Perintah ini mengirim ulang event tertunda dengan `event_id` yang sama; tidak membuat pesanan baru. Belum ada scheduler retry otomatis. Jalankan satu consumer per queue saat demo agar hasilnya mudah diamati.

## Pengujian Otomatis

Feature test memeriksa respons Problem Details, stok, transaksi, pencatatan Outbox, kompatibilitas endpoint lama, pembatalan idempoten, serta kondisi broker tidak tersedia. Sebagian besar tes memakai SQLite dalam memori dan memock publisher; uji pemulihan melalui broker dicatat di `docs/STATUS-CHECKPOINT-1.md`.

Feature test setiap layanan memakai basis data uji SQLite dalam memori. Jalankan perintah berikut dari masing-masing folder layanan:

```bash
# dari layanan-pesanan
./vendor/bin/sail artisan test
# ulangi dari layanan-dapur dan layanan-pembukuan
```

Lint kontrak OpenAPI dengan Spectral:

```bash
docker run --rm --network none -v "$PWD:/workspace:ro" -w /workspace stoplight/spectral:latest lint routes/openapi.yaml --ruleset .spectral.yaml
```

Perlu dipahami bahwa pengujian fitur di atas **berbeda** dengan pengujian integrasi RabbitMQ. Pengujian fitur memverifikasi logika API dan penyimpanan database tanpa broker, sedangkan verifikasi jalur event secara nyata dilakukan melalui pengujian manual seperti dijelaskan pada bagian panduan Swagger, yaitu dengan mengamati pesan yang diterima consumer.

## Analisis dan persiapan Checkpoint 1

Laporan analisis final untuk Checkpoint 1 tersedia sebagai [PDF 13 halaman](../docs/analisis/Laporan-Dokumen_Analisis_Warung-Seblak.pdf). DOCX final dengan nama dasar yang sama belum tersedia; dokumen Word lama bukan pasangan final PDF ini.

### Urutan demo empat menit

1. Tunjukkan diagram arsitektur dan jelaskan jalur REST serta event RabbitMQ.
2. Kirim satu request sukses `POST /api/v1/pesanan` dan tunjukkan respons `201`.
3. Kirim request tidak valid (misalnya `items` kosong) dan tunjukkan `422 application/problem+json`.
4. Jalankan `./vendor/bin/sail artisan test` dan tunjukkan hasilnya.
5. Tunjukkan event yang diterima consumer. Bila tertunda, jalankan `./vendor/bin/sail artisan outbox:publish`.
6. Setiap anggota menjelaskan kontribusi dan file yang dikerjakannya; siapkan riwayat commit kelompok.

Sebelum checkpoint, jalankan `./vendor/bin/sail up -d`, pastikan RabbitMQ sehat, dan siapkan Swagger/curl serta consumer di terminal terpisah. Tarik image Docker sebelum hari pemeriksaan.

## Penutup

Sistem Layanan Pesanan Warung Seblak Ngakak menyatukan proses pemesanan yang cepat dengan pemrosesan lanjutan yang andal melalui pola komunikasi berbasis event. API menyediakan satu jalur masuk yang tervalidasi untuk setiap pesanan, database menyimpannya secara transaksional dan konsisten, sementara RabbitMQ memastikan peristiwa pesanan dapat dikonsumsi oleh layanan lain tanpa membebani proses pemesanan itu sendiri. Dengan lingkungan pengembangan berbasis Docker Sail, dokumentasi API yang interaktif, serta pengujian otomatis yang tersedia, proyek ini menjadi fondasi yang rapi untuk pengembangan layanan pesanan maupun perluasan integrasi ke sistem lain di masa mendatang.
