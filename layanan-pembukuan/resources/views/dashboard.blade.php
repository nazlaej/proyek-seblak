<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Pembukuan</title>
    <style>
        :root {
            --bg: #0f172a; --panel: #ffffff; --ink: #1e293b; --muted: #64748b;
            --line: #e2e8f0; --brand: #0d9488; --brand-dark: #0f766e;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f1f5f9; color: var(--ink); }
        header.top { background: var(--bg); color: #fff; padding: 20px 24px; }
        header.top h1 { margin: 0; font-size: 20px; }
        header.top p { margin: 4px 0 0; color: #94a3b8; font-size: 13px; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; }
        .card .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .card .value { font-size: 24px; font-weight: 700; margin-top: 4px; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .num { text-align: right; }
        code { font-size: 12px; color: var(--brand-dark); }
        .muted { color: var(--muted); font-size: 13px; }
        header .flex { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        button { cursor: pointer; border: none; border-radius: 7px; padding: 8px 12px;
            background: var(--brand); color: #fff; font-size: 13px; font-weight: 600; }
        button:hover { background: var(--brand-dark); }
        .live { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #94a3b8; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }
        footer { max-width: 1000px; margin: 0 auto; padding: 0 24px 32px; color: var(--muted); font-size: 13px; line-height: 1.7; }
        @media (max-width: 820px) { .cards { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <header class="top">
        <div class="flex">
            <div>
                <h1>Layanan Pembukuan — Jurnal Penjualan</h1>
                <p>Consumer RabbitMQ · catatan otomatis dari event order.created</p>
            </div>
            <span class="live"><span class="dot"></span> <span id="jam">–</span></span>
        </div>
    </header>

    <div class="wrap">
        <div class="cards">
            <div class="card"><div class="label">Transaksi</div><div class="value" id="statTransaksi">–</div></div>
            <div class="card"><div class="label">Total Omzet</div><div class="value" id="statOmzet">–</div></div>
            <div class="card"><div class="label">Unit Terjual</div><div class="value" id="statUnit">–</div></div>
            <div class="card"><div class="label">Produk Unik</div><div class="value" id="statProduk">–</div></div>
        </div>

        <div class="panel">
            <div class="flex" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h2 style="margin:0;">Buku Jurnal</h2>
                <button id="refreshBtn" type="button">Muat ulang</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th><th>Menu</th><th class="num">Jumlah</th>
                        <th class="num">Total Pesanan</th><th>message_id</th>
                    </tr>
                </thead>
                <tbody id="jurnalBody"><tr><td colspan="5" class="muted">Memuat…</td></tr></tbody>
            </table>
        </div>
    </div>

    <footer>
        Data berasal dari consumer RabbitMQ. Jalankan di terminal:
        <code>cd ~/layanan-pembukuan &amp;&amp; ./vendor/bin/sail artisan pembukuan:konsumsi</code><br>
        Halaman ini menyegarkan otomatis tiap 5 detik. Terkait:
        <a href="http://localhost:15672" target="_blank">RabbitMQ UI (:15672)</a>
    </footer>

    <script>
        const rupiah = new Intl.NumberFormat('id-ID');
        const waktu = new Intl.DateTimeFormat('id-ID', { dateStyle: 'short', timeStyle: 'medium' });

        async function load() {
            try {
                const res = await fetch('/dashboard/jurnal', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                document.getElementById('statTransaksi').textContent = d.jumlah_transaksi;
                document.getElementById('statOmzet').textContent = 'Rp ' + rupiah.format(d.total_omzet);
                document.getElementById('statUnit').textContent = d.total_unit;
                document.getElementById('statProduk').textContent = d.produk_unik;

                const body = document.getElementById('jurnalBody');
                body.innerHTML = '';
                if (!d.baris.length) {
                    body.innerHTML = '<tr><td colspan="5" class="muted">Belum ada transaksi. Jalankan consumer lalu buat pesanan di Layanan Pesanan.</td></tr>';
                }
                d.baris.forEach(j => {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + waktu.format(new Date(j.terjadi_pada)) + '</td>' +
                        '<td><strong>' + j.nama_menu + '</strong></td>' +
                        '<td class="num">' + j.jumlah + '</td>' +
                        '<td class="num">Rp ' + rupiah.format(j.total) + '</td>' +
                        '<td><code>' + j.message_id + '</code></td>';
                    body.appendChild(tr);
                });
                document.getElementById('jam').textContent = 'diperbarui ' + new Date().toLocaleTimeString('id-ID');
            } catch (e) {
                document.getElementById('jurnalBody').innerHTML =
                    '<tr><td colspan="5" class="muted">Gagal memuat: ' + e.message + '</td></tr>';
            }
        }

        document.getElementById('refreshBtn').addEventListener('click', load);
        load();
        setInterval(load, 5000);
    </script>
</body>
</html>
