#!/usr/bin/env python3
"""
HealthEKG Telehealth — Worker Pemrosesan Payload EKG
=====================================================

Tanggung jawab worker (sesuai arsitektur sistem):
 1. Berlangganan pesan dari broker MQTT (healthekg/telemetry/+).
 2. Memvalidasi format frame perangkat (lihat docs/PAYLOAD-FORMAT.md).
 3. Mendekripsi dan memverifikasi ASCON-128 (authenticatED encryption).
    - Payload yang GAGAL verifikasi tag TIDAK disimpan sebagai sampel valid.
 4. Menyimpan paket + sampel EKG valid ke MySQL (tabel ekg_*).
 5. Mencatat transmission_logs (QoS) dan security_logs (keamanan).
 6. Mengirim heartbeat status ke tabel service_status.
 7. Membuat notifikasi event nyata (sesi baru, perangkat tak dikenal).

Kredensial broker & database dibaca dari worker/config.ini (atau environment).
Secret key ASCON-128 (32 karakter hex) TIDAK PERNAH ditampilkan/dilog.

Jalankan:
    pip install -r requirements.txt
    cp config.example.ini config.ini   # lalu isi konfigurasi
    python3 mqtt_worker.py
"""

import base64
import configparser
import json
import logging
import os
import signal
import socket
import struct
import sys
import threading
import time
import uuid
from datetime import datetime, timezone

try:
    import ascon  # pustaka pyascon — implementasi ASCON standar
    import MySQLdb  # mysqlclient; fallback pymysql di bawah
except ImportError:
    MySQLdb = None

try:
    import paho.mqtt.client as mqtt
except ImportError:
    print("Paket paho-mqtt belum terpasang. Jalankan: pip install -r requirements.txt")
    sys.exit(1)

if MySQLdb is None:
    try:
        import pymysql as MySQLdb
        pymysql_install_error = None
    except ImportError as e:
        pymysql_install_error = e
        MySQLdb = None

# ----------------------------------------------------------------------
# Konstanta frame (HARUS sama dengan docs/PAYLOAD-FORMAT.md dan firmware)
# ----------------------------------------------------------------------
MAGIC = b"HEK1"
FRAME_VERSION = 1
MSG_TYPE_ECG = 0x01
MSG_TYPE_SESSION_END = 0x02
NONCE_LEN = 16
TAG_LEN = 16
SESSION_CODE_LEN = 36
ASCON_KEY_LEN = 16  # 128-bit

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("healthekg-worker")


class Config:
    def __init__(self, path):
        cp = configparser.ConfigParser()
        read = cp.read([path], encoding="utf-8")
        if not read:
            log.error("File konfigurasi tidak ditemukan: %s", path)
            sys.exit(1)

        def get(section, key, default=None, env=None):
            # Environment variable menimpa nilai file konfigurasi.
            if env and os.environ.get(env):
                return os.environ[env]
            return cp.get(section, key, fallback=default)

        self.broker_host = get("mqtt", "host", "127.0.0.1")
        self.broker_port = int(get("mqtt", "port", "1883"))
        self.broker_user = get("mqtt", "username", "", env="MQTT_USERNAME") or None
        self.broker_pass = get("mqtt", "password", "", env="MQTT_PASSWORD") or None
        self.topic_prefix = get("mqtt", "topic_prefix", "healthekg")
        self.qos = int(get("mqtt", "qos", "1"))

        self.db_host = get("database", "host", "127.0.0.1")
        self.db_port = int(get("database", "port", "3306"))
        self.db_name = get("database", "database", "healthekg")
        self.db_user = get("database", "username", "healthekg")
        self.db_pass = get("database", "password", "")

        key_hex = get("security", "ascon_key_hex", "", env="ASCON_KEY_HEX")
        if len(key_hex) != ASCON_KEY_LEN * 2:
            log.error(
                "ascon_key_hex harus %d karakter hex (%d byte). Generate dengan: openssl rand -hex 16",
                ASCON_KEY_LEN * 2, ASCON_KEY_LEN,
            )
            sys.exit(1)
        self.ascon_key = bytes.fromhex(key_hex)

        self.worker_timeout = int(get("app", "worker_timeout", "120"))


# ----------------------------------------------------------------------
# Koneksi database (disimpan ulang otomatis bila koneksi putus)
# ----------------------------------------------------------------------
class Database:
    def __init__(self, cfg: Config):
        self.cfg = cfg
        self.conn = None

    def connect(self):
        if MySQLdb is None:
            log.error("Driver MySQL tidak tersedia (mysqlclient/pymysql belum terpasang).")
            sys.exit(1)

        while True:
            try:
                self.conn = MySQLdb.connect(
                    host=self.cfg.db_host,
                    port=self.cfg.db_port,
                    db=self.cfg.db_name,
                    user=self.cfg.db_user,
                    passwd=self.cfg.db_pass,
                    charset="utf8mb4",
                    autocommit=True,
                )
                log.info("Terhubung ke database %s@%s:%s", self.cfg.db_name, self.cfg.db_host, self.cfg.db_port)
                return
            except Exception as e:
                log.error("Gagal koneksi database: %s — coba ulang 5 detik…", e)
                time.sleep(5)

    def ensure(self):
        try:
            self.conn.ping(reconnect=True)
        except Exception:
            log.warning("Koneksi database putus, menghubungkan ulang…")
            self.connect()

    def query(self, sql, args=None, fetch=False):
        self.ensure()
        cur = self.conn.cursor()
        try:
            cur.execute(sql, args or ())
            if fetch:
                return cur.fetchall()
            return cur.lastrowid
        finally:
            cur.close()

    def execute(self, sql, args=None):
        return self.query(sql, args)

    def insert_many(self, sql, rows):
        self.ensure()
        cur = self.conn.cursor()
        try:
            cur.executemany(sql, rows)
        finally:
            cur.close()


# ----------------------------------------------------------------------
# Pemrosesan frame
# ----------------------------------------------------------------------
class FrameError(Exception):
    def __init__(self, code, message):
        super().__init__(message)
        self.code = code


def parse_frame(frame: bytes):
    """Parse header frame → dict metadata (tanpa dekripsi)."""
    if len(frame) < 4 or frame[:4] != MAGIC:
        raise FrameError("format_invalid", "magic bytes tidak dikenal")

    version = frame[4]
    if version != FRAME_VERSION:
        raise FrameError("format_invalid", f"versi frame {version} tidak didukung")

    msg_type = frame[5]
    if msg_type not in (MSG_TYPE_ECG, MSG_TYPE_SESSION_END):
        raise FrameError("format_invalid", f"tipe pesan {msg_type} tidak dikenal")

    offset = 6
    (dev_len,) = struct.unpack_from(">H", frame, offset)
    offset += 2
    if dev_len <= 0 or dev_len > 64 or len(frame) < offset + dev_len:
        raise FrameError("format_invalid", "panjang device id tidak valid")
    device_uid = frame[offset:offset + dev_len].decode("utf-8", "strict")
    offset += dev_len

    if len(frame) < offset + SESSION_CODE_LEN + 4 + 8 + 2 + 2 + NONCE_LEN:
        raise FrameError("format_invalid", "frame terlalu pendek")

    session_code = frame[offset:offset + SESSION_CODE_LEN].decode("ascii", "strict")
    offset += SESSION_CODE_LEN

    (packet_number,) = struct.unpack_from(">I", frame, offset)
    offset += 4
    (device_ts_ms,) = struct.unpack_from(">Q", frame, offset)
    offset += 8
    (sampling_rate,) = struct.unpack_from(">H", frame, offset)
    offset += 2
    (sample_count,) = struct.unpack_from(">H", frame, offset)
    offset += 2

    nonce = frame[offset:offset + NONCE_LEN]
    offset += NONCE_LEN

    ciphertext = frame[offset:]

    expected_min = sample_count * 2 + TAG_LEN
    if len(ciphertext) < expected_min:
        raise FrameError("format_invalid", "ukuran ciphertext tidak sesuai sample_count")

    return {
        "msg_type": msg_type,
        "device_uid": device_uid,
        "session_code": session_code,
        "packet_number": packet_number,
        "device_ts_ms": device_ts_ms,
        "sampling_rate": sampling_rate,
        "sample_count": sample_count,
        "nonce": nonce,
        "ciphertext": ciphertext,
        "aad": frame[:offset - NONCE_LEN],  # header hingga sebelum nonce
    }


def decrypt_payload(cfg: Config, parsed: dict) -> bytes:
    """Dekripsi ASCON-128 + verifikasi tag. Melempar Exception bila tag invalid."""
    plaintext = ascon.decrypt(
        key=cfg.ascon_key,
        nonce=parsed["nonce"],
        ciphertext=parsed["ciphertext"],
        aad=parsed["aad"],
        variant="Ascon-128",
    )
    return plaintext


def decode_samples(plaintext: bytes, expected_count: int):
    """Plaintext = N × int16 little-endian (nilai mV × 100)."""
    count = len(plaintext) // 2
    if count != expected_count:
        raise FrameError("format_invalid", "jumlah sampel plaintext tidak sesuai header")
    values = struct.unpack(f"<{count}h", plaintext)
    return [round(v / 100.0, 3) for v in values]


# ----------------------------------------------------------------------
# Worker utama
# ----------------------------------------------------------------------
class Worker:
    def __init__(self, cfg: Config):
        self.cfg = cfg
        self.db = Database(cfg)
        self.mqtt = None
        self.running = True
        self.stats = {"received": 0, "valid": 0, "invalid": 0, "duplicate": 0}

    # ---------------- MQTT ----------------
    def start_mqtt(self):
        client = mqtt.Client(client_id=f"healthekg-worker-{uuid.uuid4().hex[:8]}", protocol=mqtt.MQTTv311)

        if self.cfg.broker_user:
            client.username_pw_set(self.cfg.broker_user, self.cfg.broker_pass)

        client.on_connect = self.on_connect
        client.on_disconnect = self.on_disconnect
        client.on_message = self.on_message
        client.reconnect_delay_set(min_delay=1, max_delay=30)

        self.mqtt = client

        topic = f"{self.cfg.topic_prefix}/telemetry/+"
        log.info("Menghubungkan broker MQTT %s:%s (topic %s)…", self.cfg.broker_host, self.cfg.broker_port, topic)

        try:
            client.connect(self.cfg.broker_host, self.cfg.broker_port, keepalive=60)
        except Exception as e:
            log.error("Tidak dapat menghubungi broker: %s", e)
            SystemEvent_log(self.db, "worker", "broker_connect_gagal", "error", None, str(e))

        client.loop_start()

        # Thread heartbeat service_status (berjalan meski tidak ada pesan).
        t = threading.Thread(target=self.heartbeat_loop, daemon=True)
        t.start()

    def on_connect(self, client, userdata, flags, rc, properties=None):
        if rc == 0:
            topic = f"{self.cfg.topic_prefix}/telemetry/+"
            client.subscribe(topic, qos=self.cfg.qos)
            log.info("Terhubung ke broker MQTT — subscribe %s (QoS %d)", topic, self.cfg.qos)
            SystemEvent_log(self.db, "worker", "broker_connect", "pulih", None,
                            f"worker subscribe {topic}")
        else:
            log.error("Koneksi broker ditolak (rc=%s). Periksa kredensial konfigurasi.", rc)
            SystemEvent_log(self.db, "worker", "broker_connect_gagal", "error", f"rc={rc}", None)

    def on_disconnect(self, client, userdata, rc, properties=None):
        log.warning("Terputus dari broker (rc=%s) — reconnect otomatis…", rc)
        SystemEvent_log(self.db, "worker", "broker_disconnect", "peringatan", f"rc={rc}", None)

    def heartbeat_loop(self):
        while self.running:
            try:
                self.db.execute(
                    "INSERT INTO service_status (service, status, detail, last_seen_at, created_at, updated_at) "
                    "VALUES (%s, %s, %s, NOW(), NOW(), NOW()) "
                    "ON DUPLICATE KEY UPDATE status=VALUES(status), detail=VALUES(detail), last_seen_at=NOW(), updated_at=NOW()",
                    ("mqtt_worker", "aktif", "worker pemrosesan EKG berjalan"),
                )
            except Exception as e:
                log.debug("Gagal mengirim heartbeat: %s", e)
            time.sleep(30)

    # ---------------- Pesan ----------------
    def on_message(self, client, userdata, msg):
        self.stats["received"] += 1
        received_dt = datetime.now(timezone.utc).replace(tzinfo=None)
        started = time.perf_counter()

        try:
            self.process_message(msg.payload, msg.topic, received_dt, started)
        except Exception as e:
            log.exception("Kesalahan tak terduga memproses pesan: %s", e)

        if self.stats["received"] % 50 == 0:
            log.info("Statistik: %s", json.dumps(self.stats))

    def process_message(self, payload: bytes, topic: str, received_dt, started):
        db = self.db
        device_uid = "?"
        session_code = "-"
        packet_number = None

        try:
            parsed = parse_frame(payload)
            device_uid = parsed["device_uid"]
            session_code = parsed["session_code"]
            packet_number = parsed["packet_number"]
        except FrameError as fe:
            self.record_transmission(
                device_uid=None, session_id=None, packet_number=None, topic=topic,
                frame_size=len(payload), payload_size=None, encrypted_size=None,
                status="invalid", validation=fe.code, delay=None,
                processing=ms_since(started), error=fe.code, received_dt=received_dt,
            )
            self.stats["invalid"] += 1
            log.warning("Frame tidak valid dari %s: %s", topic, fe)
            return

        # Cek perangkat terdaftar.
        device = db.query(
            "SELECT id, patient_id, is_registered FROM devices WHERE device_uid = %s",
            (device_uid,), fetch=True,
        )

        if not device or not device[0][2]:
            self.record_security(device_uid, None, packet_number, "perangkat_tak_dikenal", None,
                                 ms_since(started), None, len(payload))
            self.record_transmission(
                device_uid, None, packet_number, topic, len(payload), None, None,
                "gagal", "perangkat_tak_dikenal", None, ms_since(started), "perangkat_tak_dikenal",
                received_dt,
            )
            self.stats["invalid"] += 1
            log.warning("Perangkat tidak terdaftar/nonaktif: %s", device_uid)
            return

        device_id, patient_id, _ = device[0]

        # Cek session; buat bila baru pertama kali terlihat.
        session = db.query(
            "SELECT id, status FROM ekg_sessions WHERE session_code = %s", (session_code,), fetch=True,
        )

        is_new_session = False
        if not session:
            # Sesi baru — dibuat dari data nyata perangkat (bukan demo).
            started_at_ms = parsed["device_ts_ms"]
            started_at = datetime.fromtimestamp(started_at_ms / 1000.0, tz=timezone.utc).replace(tzinfo=None)

            doctor_id = db.query(
                "SELECT doctor_id FROM patients WHERE id = %s", (patient_id,), fetch=True,
            )
            doctor_id = doctor_id[0][0] if doctor_id else None

            session_id = db.execute(
                "INSERT INTO ekg_sessions (session_code, patient_id, doctor_id, device_id, sampling_rate, "
                "started_at, status, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, 'aktif', NOW(), NOW())",
                (session_code, patient_id, doctor_id, device_id, parsed["sampling_rate"] or None, started_at),
            )
            is_new_session = True
            self.notify_new_session(session_id, session_code, patient_id, doctor_id)
            log.info("Sesi baru %s (pasien #%s, perangkat %s)", session_code, patient_id, device_uid)
        else:
            session_id, session_status = session[0]
            if session_status != "aktif":
                # Sesi sudah ditutup; paket terlambat dicatat sebagai duplikat/invalid, tidak menyambung sesi.
                self.record_transmission(
                    device_uid, session_id, packet_number, topic, len(payload), None, None,
                    "invalid", "sesi_sudah_berakhir", None, ms_since(started), "sesi_sudah_berakhir",
                    received_dt,
                )
                self.stats["invalid"] += 1
                return

        # Dekripsi + verifikasi tag (ASCON-128).
        try:
            plaintext = decrypt_payload(self.cfg, parsed)
        except Exception:
            # Tag tidak valid ATAU kunci tidak cocok → tolak pesan.
            self.record_security(device_uid, session_id, packet_number, "tag_invalid", None,
                                 ms_since(started), None, len(parsed["ciphertext"]))
            self.record_transmission(
                device_uid, session_id, packet_number, topic, len(payload), None,
                len(parsed["ciphertext"]), "gagal", "tag_invalid", None, ms_since(started),
                "tag_invalid", received_dt,
            )
            self.stats["invalid"] += 1
            log.warning("Verifikasi tag gagal: perangkat %s paket #%s — data ditolak", device_uid, packet_number)
            return

        processing_ms = ms_since(started)

        # Pesan akhir sesi.
        if parsed["msg_type"] == MSG_TYPE_SESSION_END:
            db.execute(
                "UPDATE ekg_sessions SET ended_at = NOW(), status = 'selesai', updated_at = NOW() WHERE id = %s",
                (session_id,),
            )
            self.record_security(device_uid, session_id, packet_number, "dekripsi_ok", None,
                                 processing_ms, 0, len(parsed["ciphertext"]))
            self.record_transmission(
                device_uid, session_id, packet_number, topic, len(payload), 0,
                len(parsed["ciphertext"]), "valid", "session_end", None, None, processing_ms,
                received_dt,
            )
            self.stats["valid"] += 1
            log.info("Sesi %s ditandai selesai oleh perangkat.", session_code)
            return

        # Duplikat paket (urutan/paket terlambat dari reconnect).
        dup = db.query(
            "SELECT id FROM ekg_packets WHERE ekg_session_id = %s AND packet_number = %s",
            (session_id, packet_number), fetch=True,
        )
        if dup:
            self.record_transmission(
                device_uid, session_id, packet_number, topic, len(payload),
                parsed["sample_count"] * 2, len(parsed["ciphertext"]),
                "duplikat", "duplikat", None, None, processing_ms, received_dt,
            )
            self.stats["duplicate"] += 1
            return

        # Nonce reuse detection (jarang; nonce unik per paket harus dijaga perangkat).
        try:
            samples = decode_samples(plaintext, parsed["sample_count"])
        except FrameError as fe:
            self.record_security(device_uid, session_id, packet_number, "format_invalid", fe.code,
                                 processing_ms, len(plaintext), len(parsed["ciphertext"]))
            self.record_transmission(
                device_uid, session_id, packet_number, topic, len(payload), len(plaintext),
                len(parsed["ciphertext"]), "invalid", fe.code, None, None, processing_ms, fe.code,
                received_dt,
            )
            self.stats["invalid"] += 1
            return

        device_time = datetime.fromtimestamp(parsed["device_ts_ms"] / 1000.0, tz=timezone.utc).replace(tzinfo=None)
        delay_ms = round((received_dt - device_time).total_seconds() * 1000, 2)

        nonce_hex = parsed["nonce"].hex()
        try:
            packet_id = db.execute(
                "INSERT INTO ekg_packets (ekg_session_id, device_id, device_uid, packet_number, sample_count, "
                "sampling_rate, device_time, received_at, payload_size, encrypted_size, processing_ms, nonce, "
                "created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())",
                (session_id, device_id, device_uid, packet_number, parsed["sample_count"],
                 parsed["sampling_rate"] or None, device_time, received_dt, len(plaintext),
                 len(parsed["ciphertext"]), processing_ms, nonce_hex),
            )
        except Exception as e:
            # Duplikat nonce → IntegrityError pada unique key (device_uid, nonce).
            self.record_security(device_uid, session_id, packet_number, "nonce_error", "nonce_reuse",
                                 processing_ms, len(plaintext), len(parsed["ciphertext"]))
            self.record_transmission(
                device_uid, session_id, packet_number, topic, len(payload), len(plaintext),
                len(parsed["ciphertext"]), "gagal", "nonce_reuse", None, delay_ms, processing_ms,
                "nonce_reuse", received_dt,
            )
            self.stats["invalid"] += 1
            log.error("Deteksi pemakaian ulang nonce dari %s paket #%s — paket ditolak", device_uid, packet_number)
            return

        # Bulk insert sampel.
        rows = []
        for i, mv in enumerate(samples):
            sampled_at = datetime.fromtimestamp(
                (parsed["device_ts_ms"] + (1000.0 / (parsed["sampling_rate"] or 250)) * i) / 1000.0,
                tz=timezone.utc,
            ).replace(tzinfo=None)
            rows.append((session_id, packet_id, i, mv, sampled_at))

        db.insert_many(
            "INSERT INTO ekg_samples (ekg_session_id, ekg_packet_id, seq, value_mv, sampled_at, created_at) "
            "VALUES (%s, %s, %s, %s, %s, NOW())",
            rows,
        )

        db.execute(
            "UPDATE ekg_sessions SET sample_count = sample_count + %s, packet_count = packet_count + 1, "
            "updated_at = NOW() WHERE id = %s",
            (len(samples), session_id),
        )
        db.execute(
            "UPDATE devices SET last_message_at = NOW(), last_packet_number = %s WHERE id = %s",
            (packet_number, device_id),
        )

        self.record_security(device_uid, session_id, packet_number, "dekripsi_ok", None,
                             processing_ms, len(plaintext), len(parsed["ciphertext"]))
        self.record_transmission(
            device_uid, session_id, packet_number, topic, len(payload), len(plaintext),
            len(parsed["ciphertext"]), "valid", "format_ok", delay_ms, processing_ms, None,
            received_dt,
        )
        self.stats["valid"] += 1

    # ---------------- Helper pencatatan ----------------
    def record_transmission(self, device_uid, session_id, packet_number, topic, frame_size,
                            payload_size, encrypted_size, status, validation, delay, processing,
                            error, received_dt=None):
        try:
            self.db.execute(
                "INSERT INTO transmission_logs (device_uid, ekg_session_id, packet_number, message_type, topic, "
                "frame_size, payload_size, encrypted_size, status, validation_status, delay_ms, processing_ms, "
                "error_code, source, received_at, created_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'worker', %s, NOW())",
                (device_uid, session_id, packet_number, "ecg", topic, frame_size, payload_size,
                 encrypted_size, status, validation, delay, processing, error,
                 received_dt or datetime.now(timezone.utc).replace(tzinfo=None)),
            )
        except Exception as e:
            log.error("Gagal mencatat transmission log: %s", e)

    def record_security(self, device_uid, session_id, packet_number, event_type, error_code,
                        processing_ms, plain_size, cipher_size):
        try:
            self.db.execute(
                "INSERT INTO security_logs (device_uid, ekg_session_id, packet_number, event_type, error_code, "
                "processing_ms, payload_plain_size, payload_cipher_size, occurred_at, created_at) "
                "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(3), NOW())",
                (device_uid, session_id, packet_number, event_type, error_code, processing_ms,
                 plain_size, cipher_size),
            )
        except Exception as e:
            log.error("Gagal mencatat security log: %s", e)

    def notify_new_session(self, session_id, session_code, patient_id, doctor_id):
        try:
            link = f"/admin/sessions/{session_id}"
            # Admin Puskesmas
            admins = self.db.query(
                "SELECT id FROM users WHERE role = 'admin' AND is_active = 1", fetch=True,
            )
            for (admin_id,) in (admins or []):
                self.db.execute(
                    "INSERT INTO app_notifications (user_id, category, title, body, link, is_read, created_at) "
                    "VALUES (%s, 'sesi', %s, %s, %s, 0, NOW())",
                    (admin_id, "Sesi pemeriksaan baru dimulai",
                     f"Sesi {session_code[:16]}… diterima dari perangkat untuk pasien terdaftar.",
                     link),
                )
            # Dokter penanggung jawab (bila memiliki akun)
            if doctor_id:
                doc_users = self.db.query(
                    "SELECT user_id FROM doctors WHERE id = %s AND user_id IS NOT NULL", (doctor_id,), fetch=True,
                )
                for (uid,) in (doc_users or []):
                    self.db.execute(
                        "INSERT INTO app_notifications (user_id, category, title, body, link, is_read, created_at) "
                        "VALUES (%s, 'sesi', %s, %s, %s, 0, NOW())",
                        (uid, "Sesi pemeriksaan baru dimulai",
                         f"Sesi {session_code[:16]}… untuk pasien Anda sedang berjalan.",
                         f"/doctor/monitoring/{session_id}"),
                    )
        except Exception as e:
            log.error("Gagal membuat notifikasi sesi baru: %s", e)

    # ---------------- Stop ----------------
    def stop(self, *args):
        log.info("Menghentikan worker…")
        self.running = False
        if self.mqtt:
            try:
                self.mqtt.loop_stop()
                self.mqtt.disconnect()
            except Exception:
                pass


class SystemEventLog:
    @staticmethod
    def log(db, component, event_type, status, error_code, message):
        try:
            db.execute(
                "INSERT INTO system_events (component, event_type, status, error_code, message, occurred_at, created_at) "
                "VALUES (%s, %s, %s, %s, %s, NOW(3), NOW())",
                (component, event_type, status, error_code, message),
            )
        except Exception:
            pass


def SystemEvent_log(db, *args, **kwargs):
    SystemEventLog.log(db, *args, **kwargs)


def ms_since(started):
    return round((time.perf_counter() - started) * 1000, 2)


def main():
    config_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.ini")
    if len(sys.argv) > 1:
        config_path = sys.argv[1]

    cfg = Config(config_path)
    worker = Worker(cfg)

    signal.signal(signal.SIGINT, worker.stop)
    signal.signal(signal.SIGTERM, worker.stop)

    worker.db.connect()
    SystemEvent_log(worker.db, "worker", "worker_mulai", "info", None,
                    "Worker pemrosesan EKG dimulai")
    worker.start_mqtt()

    try:
        while worker.running:
            time.sleep(1)
    finally:
        SystemEvent_log(worker.db, "worker", "worker_berhenti", "peringatan", None,
                        "Worker dihentikan")
        log.info("Worker berhenti.")


if __name__ == "__main__":
    main()
