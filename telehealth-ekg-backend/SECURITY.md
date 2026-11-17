# Keamanan repository

Sebelum push:
- Jangan commit `.env`, password, token Cloudflare, API key, private key, session cookie, atau kredensial.
- Jangan unggah database dump produksi.
- Jangan unggah data pasien atau rekaman EKG yang dapat mengidentifikasi pasien.
- Samarkan IP dan detail infrastruktur yang tidak perlu dipublikasikan.
- Periksa seluruh riwayat Git. Menghapus rahasia dari file terbaru tidak menghapusnya dari riwayat commit.
- Jika rahasia terlanjur terunggah, segera rotasi/ganti rahasia tersebut.
- Gunakan data uji untuk screenshot dashboard.
