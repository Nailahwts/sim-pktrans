# PT Karya Trans (PTKT) - Sistem Manajemen Logistik

Sistem informasi web untuk manajemen operasional perusahaan logistik PT Karya Trans yang dibangun dengan PHP, MySQL, dan Bootstrap.

## 📋 Daftar Isi
- [Fitur Utama](#fitur-utama)
- [Struktur Proyek](#struktur-proyek)
- [Alur Aplikasi](#alur-aplikasi)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi Database](#konfigurasi-database)
- [Penggunaan](#penggunaan)
- [API Endpoints](#api-endpoints)
- [Teknologi](#teknologi)

## 🚀 Fitur Utama

### Master Data Management
- **Partner**: Kelola data rekanan dan internal perusahaan
- **Tanda Tangan**: Manajemen tanda tangan digital untuk dokumen
- **Kendaraan**: Data kendaraan operasional
- **Barang**: Katalog barang/jasa yang ditangani
- **Dermaga**: Informasi dermaga pelabuhan
- **Harga**: Sistem pricing untuk layanan

### Transaksi
- **Sales Order (SO)**: Pemesanan penjualan
- **Purchase Order (PO)**: Pemesanan pembelian
- **Surat Jalan**: Dokumen pengiriman barang
- **Realisasi**: Pelaporan realisasi pengiriman

### Laporan
- **Order Kerja**: Laporan pekerjaan harian
- **Invoice**: Faktur penagihan
- **Kwitansi**: Bukti pembayaran
- **Laporan Realisasi**: Ringkasan realisasi operasional

### Fitur Tambahan
- **Petroport**: Import dan analisis data dari Excel
- **API**: RESTful API untuk integrasi sistem
- **Multi-user**: Sistem login admin dengan role-based access

## 🏗️ Struktur Proyek

```
ptkaryatrans/
├── api/                    # API endpoints
│   ├── debug_kapal.php
│   ├── get_kapal_by_barang.php
│   ├── get_kapal_by_warehouse.php
│   ├── get_perusahaan.php
│   └── get_warehouse.php
├── assets/                 # Static files
│   ├── css/
│   ├── images/
│   └── js/
├── config/                 # Database configuration
│   └── database.php
├── laporan/                # Report modules
│   ├── cetak_invoice.php
│   ├── cetak_kwitansi.php
│   ├── cetak_order_kerja.php
│   ├── invoice.php
│   ├── kwitansi.php
│   ├── laporan_realisasi.php
│   └── order_kerja.php
├── master/                 # Master data management
│   ├── barang.php
│   ├── dermaga.php
│   ├── harga.php
│   ├── kapal_dermaga.php
│   ├── kendaraan.php
│   ├── partner.php
│   └── ttd.php
├── templates/              # HTML templates
│   ├── footer.php
│   ├── header.php
│   └── sidebar.php
├── transaksi/              # Transaction modules
│   ├── fpdf/              # PDF generation library
│   ├── get_total_tonase.php
│   ├── purchase_order.php
│   ├── realisasi.php
│   ├── sales_order.php
│   └── surat_jalan.php
├── vendor/                 # Composer dependencies
├── composer.json
├── composer.lock
├── create_user.php        # User registration
├── index.php              # Main dashboard
├── login.php              # Authentication
├── logout.php             # Session destroy
├── petroport.php          # Excel import tool
├── TODO.md                # Development tasks
└── README.md
```

## 🔄 Alur Aplikasi

### 1. **Authentication Flow**
```
Login Page → Session Validation → Dashboard
     ↓
Create User (Admin Registration)
```

### 2. **Main Dashboard Flow**
```
Dashboard → Navigation Menu
     ↓
├── Master Data Management
│   ├── Partner CRUD
│   ├── Vehicle Management
│   ├── Product Catalog
│   ├── Port Management
│   └── Pricing System
│
├── Transaction Processing
│   ├── Sales Order Creation
│   ├── Purchase Order Management
│   ├── Delivery Note Generation
│   └── Realization Reporting
│
└── Report Generation
    ├── Work Order Reports
    ├── Invoice Creation
    ├── Receipt Printing
    └── Realization Summary
```

### 3. **Data Flow Architecture**
```
User Input → PHP Processing → Database Operations
     ↓              ↓              ↓
Form Validation → Business Logic → CRUD Operations
     ↓              ↓              ↓
Session Management → API Calls → Data Persistence
```

### 4. **Report Generation Flow**
```
Transaction Data → Report Module → PDF Generation
     ↓                ↓                ↓
Data Retrieval → Template Processing → File Output
```

## 💻 Persyaratan Sistem

- **Web Server**: Apache/Nginx dengan mod_rewrite
- **PHP**: Version 7.4 atau lebih tinggi
- **Database**: MySQL 5.7+ atau MariaDB 10.0+
- **Composer**: Untuk dependency management
- **Browser**: Modern browser dengan JavaScript enabled

### PHP Extensions Required:
- mysqli
- mbstring
- gd (untuk image processing)
- fileinfo

## 📦 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/your-repo/ptkaryatrans.git
cd ptkaryatrans
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Setup Web Server
- Pastikan folder `ptkaryatrans` berada di document root web server
- Untuk XAMPP: Copy ke `htdocs/ptkaryatrans`
- Untuk Apache: Setup virtual host pointing to project directory

### 4. Database Setup
- Import file SQL database ke MySQL
- Update konfigurasi di `config/database.php`

## ⚙️ Konfigurasi Database

Edit file `config/database.php`:

```php
<?php
$host = "localhost";
$username = "your_db_user";
$password = "your_db_password";
$database = "pkt_database";
?>
```

### Tabel Database Utama:
- `user_admin`: Data admin sistem
- `partner`: Data rekanan perusahaan
- `barang`: Katalog produk
- `kendaraan`: Data armada
- `dermaga`: Informasi pelabuhan
- `sales_order`: Data pesanan penjualan
- `purchase_order`: Data pesanan pembelian
- `surat_jalan`: Dokumen pengiriman
- `invoice`: Data faktur
- `kwitansi`: Data pembayaran

## 🎯 Penggunaan

### Akses Sistem
1. Buka browser dan akses `http://localhost/ptkaryatrans`
2. Login dengan akun admin yang sudah terdaftar
3. Jika belum ada akun, buat melalui `create_user.php`

### Navigasi Menu
- **Dashboard**: Ringkasan dan navigasi utama
- **Master Data**: Kelola data referensi
- **Transaksi**: Proses bisnis harian
- **Laporan**: Generate dan cetak dokumen

### Fitur Petroport
- Upload file Excel untuk import data
- Filter data berdasarkan cargo, date, shift, warehouse, EMKL
- Tampilan tabel dengan format angka otomatis

## 🔗 API Endpoints

### Kapal Management
- `GET /api/get_kapal_by_barang.php`: Data kapal berdasarkan barang
- `GET /api/get_kapal_by_warehouse.php`: Data kapal berdasarkan warehouse
- `GET /api/debug_kapal.php`: Debug informasi kapal

### Perusahaan Data
- `GET /api/get_perusahaan.php`: Data perusahaan
- `GET /api/get_warehouse.php`: Data warehouse
- `GET /api/get_warehouse_by_kapal.php`: Warehouse berdasarkan kapal

## 🛠️ Teknologi

### Backend
- **PHP 7.4+**: Server-side scripting
- **MySQL**: Database management
- **Composer**: Dependency management

### Frontend
- **Bootstrap 5**: CSS framework
- **jQuery**: JavaScript library
- **DataTables**: Table management
- **FPDF**: PDF generation

### Libraries
- **PHPSpreadsheet**: Excel file processing
- **Bootstrap Bundle**: UI components

## 🔐 Keamanan

- Password hashing menggunakan `password_hash()`
- Prepared statements untuk mencegah SQL injection
- Session-based authentication
- Input validation dan sanitization
- CSRF protection pada form

## 📝 Catatan Pengembang

### TODO Items:
- [x] Modify sales_order.php to truncate qty to 3 decimals
- [x] Modify get_total_tonase.php to truncate total_tonase to 3 decimals

### Development Guidelines:
- Gunakan prepared statements untuk semua query database
- Validasi input di sisi server
- Gunakan htmlspecialchars() untuk output HTML
- Ikuti PSR-4 autoloading standards

## 📞 Support

Untuk pertanyaan atau dukungan teknis, hubungi tim development PT Karya Trans.

---

**PT Karya Trans** © 2024. Sistem Manajemen Logistik Terintegrasi.
