-- tests/skema_contoh.sql
-- SKEMA TEBAKAN dari pembacaan kode, hanya agar test bisa langsung dicoba.
-- Untuk pengujian sebenarnya, GANTI dengan hasil:  mysqldump --no-data digita_db master_pengguna dil kategorisasi_risiko pelunasan_ap2t
CREATE DATABASE IF NOT EXISTS digita_test CHARACTER SET utf8mb4;
USE digita_test;

DROP TABLE IF EXISTS kategorisasi_risiko;
DROP TABLE IF EXISTS pelunasan_ap2t;
DROP TABLE IF EXISTS dil;
DROP TABLE IF EXISTS master_pengguna;

CREATE TABLE master_pengguna (
    NamaAkun VARCHAR(50) PRIMARY KEY,
    UnitUp VARCHAR(10) NULL, UnitAp VARCHAR(10) NULL, UnitUpi VARCHAR(10) NULL
);
CREATE TABLE dil (
    Idpel VARCHAR(20) PRIMARY KEY,
    NamaPelanggan VARCHAR(100) NULL,
    IndexPrioritas TINYINT NULL
);
CREATE TABLE pelunasan_ap2t (
    id INT AUTO_INCREMENT PRIMARY KEY,
    IdPel VARCHAR(20) NOT NULL,
    ThBlRek CHAR(6) NOT NULL,
    RpTag DECIMAL(15,2) NULL,
    RpBK DECIMAL(15,2) NULL,
    TglBayar DATE NULL
);
CREATE TABLE kategorisasi_risiko (
    IdPel VARCHAR(20) NOT NULL,
    Periode YEAR NOT NULL,
    PosisiSR VARCHAR(1) NULL,
    LevelKepentingan VARCHAR(10) NULL,
    LevelKeterlambatan VARCHAR(10) NULL,
    LevelKemungkinan VARCHAR(30) NULL,
    LevelDampak VARCHAR(20) NULL,
    Kuadran TINYINT NULL,
    SkalaPrioritas TINYINT NULL,
    UnitUp VARCHAR(10) NULL, UnitAp VARCHAR(10) NULL, UnitUpi VARCHAR(10) NULL,
    PRIMARY KEY (IdPel, Periode),
    FOREIGN KEY (IdPel) REFERENCES dil (Idpel)
);
