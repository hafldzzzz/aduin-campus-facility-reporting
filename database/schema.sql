-- ADUIN: struktur saja, tanpa data atau hash akun.
-- Import hanya ke database BARU dan KOSONG bernama aduin_demo.
-- Tidak memilih database, tidak menghapus/mengganti tabel yang sudah ada.
CREATE TABLE users (
 id_user INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(100) NOT NULL, email VARCHAR(254) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, no_wa VARCHAR(30) DEFAULT NULL,
 role ENUM('admin','pengguna','petugas') NOT NULL DEFAULT 'pengguna',
 fakultas VARCHAR(150) DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE kategori (
 id_kategori INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE pengaduan (
 id_pengaduan INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 no_laporan VARCHAR(100) NOT NULL UNIQUE,
 id_user INT UNSIGNED NOT NULL, id_kategori INT UNSIGNED NOT NULL,
 id_petugas INT UNSIGNED DEFAULT NULL,
 lokasi VARCHAR(255) NOT NULL, deskripsi TEXT NOT NULL,
 foto VARCHAR(255) DEFAULT NULL,
 status ENUM('Menunggu','Diproses','Selesai') NOT NULL DEFAULT 'Menunggu',
 tanggal_lapor TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY report_month (tanggal_lapor, id_pengaduan),
 FOREIGN KEY (id_user) REFERENCES users(id_user),
 FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori),
 FOREIGN KEY (id_petugas) REFERENCES users(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE riwayat_status (
 id_riwayat INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_pengaduan INT UNSIGNED NOT NULL,
 status ENUM('Menunggu','Diproses','Selesai') NOT NULL,
 keterangan TEXT DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_pengaduan) REFERENCES pengaduan(id_pengaduan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE penanganan (
 id_penanganan INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_pengaduan INT UNSIGNED NOT NULL, id_petugas INT UNSIGNED NOT NULL,
 catatan TEXT DEFAULT NULL, foto_perbaikan VARCHAR(255) DEFAULT NULL,
 tanggal_penanganan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_pengaduan) REFERENCES pengaduan(id_pengaduan),
 FOREIGN KEY (id_petugas) REFERENCES users(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE complaint_submission_requests (
    user_id BIGINT UNSIGNED NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    pengaduan_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, request_hash),
    KEY submission_report (pengaduan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE whatsapp_admin_optins (
    id_admin BIGINT UNSIGNED NOT NULL,
    recipient VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    consent_reference VARCHAR(255) NOT NULL,
    consented_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_admin),
    KEY active_recipient (recipient, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengaduan_id BIGINT UNSIGNED NOT NULL,
    admin_id BIGINT UNSIGNED NOT NULL,
    recipient VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    template_name VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    template_lang VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload TEXT NOT NULL,
    status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_retry_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    provider_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    lock_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    locked_at DATETIME NULL,
    last_error VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY report_event_recipient (pengaduan_id, event, recipient),
    UNIQUE KEY worker_lock (lock_token),
    KEY ready_jobs (status, next_retry_at, id),
    KEY admin_jobs (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
