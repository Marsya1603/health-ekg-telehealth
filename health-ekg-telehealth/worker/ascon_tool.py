#!/usr/bin/env python3
"""
HealthEKG — Alat Uji Frame ASCON-128 (ascon_tool.py)
=====================================================

ALAT PENGUJIAN, bukan bagian dashboard. Digunakan untuk:
 1. Membangun frame MQTT valid (enkripsi ASCON-128) sesuai docs/PAYLOAD-FORMAT.md
    agar pengiriman end-to-end dapat diuji tanpa menunggu firmware ESP32.
 2. Verifikasi balik (dekripsi) sebuah frame.

Contoh membuat frame dari file CSV sampel (satu kolom nilai mV):
    python3 ascon_tool.py encrypt --key HEX32 --device DEV-TEST01 \
        --session "$(uuidgen)" --rate 250 --samples samples.csv

Kirim hasilnya ke broker:
    python3 ascon_tool.py encrypt ... --publish --host 127.0.0.1

PENTING: alat ini untuk pengujian pipeline. Aplikasi web tidak pernah
membuat data EKG buatan; semua data di dashboard berasal dari frame nyata.
"""

import argparse
import base64
import csv
import os
import struct
import sys
import time

try:
    import ascon
except ImportError:
    print("Paket pyascon belum terpasang. Jalankan: pip install pyascon")
    sys.exit(1)

MAGIC = b"HEK1"
FRAME_VERSION = 1
MSG_TYPE_ECG = 0x01
MSG_TYPE_SESSION_END = 0x02
NONCE_LEN = 16
SESSION_CODE_LEN = 36


def load_samples(path):
    values = []
    with open(path, newline="", encoding="utf-8") as f:
        for row in csv.reader(f):
            if not row:
                continue
            values.append(int(round(float(row[0]) * 100)))  # mV → skala x100
    if not values:
        print("File sampel kosong.", file=sys.stderr)
        sys.exit(1)
    return values


def build_frame(key, device_uid, session_code, packet_number, device_ts_ms,
                sampling_rate, samples, msg_type=MSG_TYPE_ECG, nonce=None):
    session_bytes = session_code.encode("ascii")
    if len(session_bytes) != SESSION_CODE_LEN:
        raise SystemExit("session_code harus UUID 36 karakter ASCII")

    device_bytes = device_uid.encode("utf-8")

    header = bytearray()
    header += MAGIC
    header += bytes([FRAME_VERSION, msg_type])
    header += struct.pack(">H", len(device_bytes))
    header += device_bytes
    header += session_bytes
    header += struct.pack(">I", packet_number)
    header += struct.pack(">Q", device_ts_ms)

    count = len(samples) if msg_type == MSG_TYPE_ECG else 0
    header += struct.pack(">H", sampling_rate)
    header += struct.pack(">H", count)

    if nonce is None:
        nonce = os.urandom(NONCE_LEN)

    plaintext = b"" if msg_type == MSG_TYPE_SESSION_END else struct.pack(f"<{count}h", *samples)

    aad = bytes(header)
    ciphertext = ascon.encrypt(key=key, nonce=nonce, plaintext=plaintext, aad=aad, variant="Ascon-128")

    return bytes(header) + nonce + ciphertext


def cmd_encrypt(args):
    key = bytes.fromhex(args.key)
    samples = load_samples(args.samples) if args.samples else list(range(-500, 500, 2))

    frame = build_frame(
        key=key,
        device_uid=args.device,
        session_code=args.session,
        packet_number=args.packet,
        device_ts_ms=int(time.time() * 1000),
        sampling_rate=args.rate,
        samples=samples,
    )

    if args.publish:
        try:
            import paho.mqtt.client as mqtt
        except ImportError:
            print("paho-mqtt belum terpasang: pip install paho-mqtt", file=sys.stderr)
            sys.exit(1)

        topic = f"{args.topic_prefix}/telemetry/{args.device}"
        client = mqtt.Client()
        if args.username:
            client.username_pw_set(args.username, args.password or "")
        client.connect(args.host, args.port, keepalive=30)
        client.loop_start()
        info = client.publish(topic, frame, qos=1)
        info.wait_for_publish()
        client.loop_stop()
        client.disconnect()
        print(f"Frame {len(frame)} byte terkirim ke {topic} ({len(samples)} sampel)")
    else:
        print(base64.b64encode(frame).decode())


def cmd_decrypt(args):
    key = bytes.fromhex(args.key)
    frame = base64.b64decode(args.frame_b64)

    offset = 6
    (dev_len,) = struct.unpack_from(">H", frame, offset)
    offset += 2 + dev_len
    session_code = frame[offset:offset + SESSION_CODE_LEN].decode()
    offset += SESSION_CODE_LEN
    (packet_number,) = struct.unpack_from(">I", frame, offset)
    offset += 4
    (ts,) = struct.unpack_from(">Q", frame, offset)
    offset += 8
    (rate,) = struct.unpack_from(">H", frame, offset)
    offset += 2
    (count,) = struct.unpack_from(">H", frame, offset)
    offset += 2
    nonce = frame[offset:offset + NONCE_LEN]
    offset += NONCE_LEN

    plaintext = ascon.decrypt(key=key, nonce=nonce, ciphertext=frame[offset:],
                              aad=frame[:offset - NONCE_LEN], variant="Ascon-128")

    values = [v / 100.0 for v in struct.unpack(f"<{count}h", plaintext)]
    print(json.dumps({
        "ok": True,
        "session": session_code,
        "packet": packet_number,
        "timestamp_ms": ts,
        "sampling_rate": rate,
        "samples": values[:10] + (["…"] if count > 10 else []),
        "total_samples": count,
    }, indent=2))


def main():
    parser = argparse.ArgumentParser(description="Alat uji frame ASCON-128 HealthEKG")
    sub = parser.add_subparsers(dest="cmd", required=True)

    enc = sub.add_parser("encrypt", help="Buat frame terenkripsi (output base64 atau publish langsung)")
    enc.add_argument("--key", required=True, help="Secret key 32 karakter hex (16 byte)")
    enc.add_argument("--device", required=True, help="Device ID terdaftar, mis. DEV-TEST01")
    enc.add_argument("--session", required=True, help="Session UUID (36 karakter, mis. hasil uuidgen)")
    enc.add_argument("--packet", type=int, default=1, help="Nomor paket")
    enc.add_argument("--rate", type=int, default=250, help="Sampling rate Hz")
    enc.add_argument("--samples", help="File CSV satu kolom berisi nilai mV")
    enc.add_argument("--publish", action="store_true", help="Langsung kirim ke broker MQTT")
    enc.add_argument("--host", default="127.0.0.1")
    enc.add_argument("--port", type=int, default=1883)
    enc.add_argument("--username", default=None)
    enc.add_argument("--password", default=None)
    enc.add_argument("--topic-prefix", default="healthekg")
    enc.set_defaults(func=cmd_encrypt)

    dec = sub.add_parser("decrypt", help="Verifikasi/dekripsi frame base64")
    dec.add_argument("--key", required=True)
    dec.add_argument("frame_b64")
    dec.set_defaults(func=cmd_decrypt)

    args = parser.parse_args()
    args.func(args)


if __name__ == "__main__":
    import json
    main()
