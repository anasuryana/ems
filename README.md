# EMS - Electronic Manufacturing System

EMS adalah sistem backend untuk mendukung proses manufaktur elektronik. Sistem ini dirancang untuk mempermudah manajemen alur produksi, pemantauan status produksi, dan pengelolaan inventaris dalam proses pembuatan perangkat elektronik.

## Fitur Utama

- **Manajemen Produksi**: Memantau dan mengelola proses produksi perangkat elektronik.
- **Manajemen Inventaris**: Mengelola stok bahan baku dan komponen.
- **Autentikasi Pengguna**: Menggunakan autentikasi untuk memastikan akses yang aman.
- **Pengelolaan Data Produksi**: Menyimpan dan mengelola data terkait hasil produksi.
- **Control WIP Data Produksi**: Memantau bahan baku di area WIP per Perintah Kerja.

## Persyaratan Sistem

- PHP <= 8.0
- Composer (untuk manajemen dependensi)
- Database seperti MySQL atau MariaDB, MS SQL Server
- Laravel 8 atau lebih tinggi

## Cara Instalasi

1. **Clone repository ini**:
   ```bash
   git clone https://github.com/anasuryana/ems.git
   cd ems


2. **Install dependensi dengan Composer**:
    ```bash
    composer install

3. **Salin file .env.example menjadi .env**:
    ```bash
    cp .env.example .env
4. **Generate key untuk aplikasi**:
    ```bash
    php artisan key:generate
5. **Konfigurasi database: Edit file .env dan sesuaikan pengaturan database Anda.**

6. **Migrasi database**:
    ```bash
    php artisan migrate

