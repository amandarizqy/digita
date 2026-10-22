import serial
import time
import mysql.connector
from mysql.connector import Error

# ================= KONFIGURASI =================
COM_PORT = 'COM17'
BAUD_RATE = 115200 # Modem Maestro biasanya menggunakan 115200 atau 9600
DB_CONFIG = {
    'host': '10.96.100.123',
    'user': 'digita',
    'password': 'Niaga@123',
    'database': 'smsd'
}
# ===============================================

def connect_db():
    """Membuat koneksi ke database MariaDB."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Error as e:
        print(f"[ERROR DB] Gagal terhubung ke database: {e}")
        return None

def init_modem(ser):
    """Inisialisasi modem ke mode Text."""
    print("[INFO] Menginisialisasi modem...")
    ser.write(b'ATZ\r')
    time.sleep(1)
    ser.write(b'AT+CMGF=1\r') # Set mode SMS ke Text
    time.sleep(1)
    ser.write(b'AT+CNMI=2,2,0,0,0\r') # Rutekan SMS masuk langsung ke terminal
    time.sleep(1)
    ser.read_all()
    print("[INFO] Modem siap.")

def kirim_sms(ser, db_conn):
    """Mengecek tabel outbox dan mengirim SMS."""
    cursor = db_conn.cursor(dictionary=True)
    # Cari SMS yang berstatus 'Reserved' (belum terkirim)
    cursor.execute("SELECT ID, DestinationNumber, TextDecoded FROM outbox WHERE Status = 'Reserved'")
    pesan_antrean = cursor.fetchall()

    for pesan in pesan_antrean:
        id_pesan = pesan['ID']
        nomor = pesan['DestinationNumber']
        teks = pesan['TextDecoded']

        print(f"[INFO] Mengirim SMS ke {nomor}...")
        
        # Perintah AT untuk mengirim SMS
        ser.write(f'AT+CMGS="{nomor}"\r'.encode())
        time.sleep(1)
        ser.write(f'{teks}\x1A'.encode()) # \x1A adalah karakter Ctrl+Z untuk eksekusi
        time.sleep(3) # Tunggu respon pengiriman
        
        respon = ser.read_all().decode(errors='ignore')
        
        # Update status di database
        if "+CMGS:" in respon or "OK" in respon:
            print(f"[SUKSES] SMS ke {nomor} terkirim.")
            cursor.execute("UPDATE outbox SET Status = 'SendingOK' WHERE ID = %s", (id_pesan,))
        else:
            print(f"[GAGAL] Gagal mengirim SMS ke {nomor}.")
            cursor.execute("UPDATE outbox SET Status = 'SendingError' WHERE ID = %s", (id_pesan,))
        
        db_conn.commit()
    cursor.close()

def proses_pesan_masuk(baris_data, ser, db_conn):
    """Mem-parsing SMS masuk dan menyimpannya ke tabel inbox."""
    # Data masuk format +CMT: "+628xxx",,"tanggal,waktu"
    parts = baris_data.split(',')
    nomor_pengirim = parts[0].split('"')[1]
    
    # Baca baris berikutnya sebagai isi teks
    isi_pesan = ""
    while True:
        line = ser.readline().decode(errors='ignore').strip()
        if not line:
            break
        isi_pesan += line + " "

    isi_pesan = isi_pesan.strip()
    print(f"[TERIMA] SMS dari {nomor_pengirim}: {isi_pesan}")

    # Simpan ke tabel inbox
    cursor = db_conn.cursor()
    query = """
        INSERT INTO inbox (ReceivingDateTime, SenderNumber, TextDecoded, Processed) 
        VALUES (NOW(), %s, %s, 'false')
    """
    cursor.execute(query, (nomor_pengirim, isi_pesan))
    db_conn.commit()
    cursor.close()
    
    print("[INFO] Pesan berhasil disimpan ke database. Trigger sistem alat akan berjalan otomatis.")

def main():
    db_conn = connect_db()
    if not db_conn:
        return

    try:
        # Buka koneksi serial ke COM17
        ser = serial.Serial(COM_PORT, BAUD_RATE, timeout=1)
        init_modem(ser)

        print("[INFO] Service berjalan. Menunggu instruksi...")
        
        while True:
            # 1. Cek Pesan Masuk dari Modem
            if ser.in_waiting > 0:
                baris = ser.readline().decode(errors='ignore').strip()
                if baris.startswith("+CMT:"):
                    proses_pesan_masuk(baris, ser, db_conn)
            
            # 2. Cek Antrean Pesan Keluar dari Database
            db_conn.ping(reconnect=True, attempts=3, delay=2) # Pastikan DB tidak RTO
            kirim_sms(ser, db_conn)
            
            # Jeda agar loop tidak membebani CPU
            time.sleep(2)

    except serial.SerialException as e:
        print(f"[ERROR PORT] Port {COM_PORT} tidak dapat diakses: {e}")
    except KeyboardInterrupt:
        print("\n[INFO] Service dihentikan oleh pengguna.")
    finally:
        if 'ser' in locals() and ser.is_open:
            ser.close()
        if db_conn.is_connected():
            db_conn.close()

if __name__ == "__main__":
    main()