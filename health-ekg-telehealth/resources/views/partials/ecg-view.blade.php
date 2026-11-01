@php
    $device = $session->device;
    // hideMetaCards=true: sembunyikan kartu metadata teknis (sampling rate,
    // sampel/paket diterima, paket terakhir) — dipakai dashboard pasien & dokter.
    // hideNote=true: sembunyikan kotak keterangan di bawah grafik — dipakai dashboard dokter.
    $hideMetaCards = $hideMetaCards ?? false;
    $hideNote = $hideNote ?? false;
@endphp
<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title"><x-icon name="activity"/> Grafik EKG — Sesi {{ $session->session_code }}</h2>
            <p class="card-sub">
                Sampel berasal langsung dari database (paket asli yang diterima perangkat melalui broker MQTT).
                Tidak ada data simulasi.
            </p>
        </div>
        @if($canEnd ?? false)
            <form method="POST" action="{{ $endUrl }}" data-confirm="Akhir sesi pemeriksaan ini? Tindakan akan dicatat pada audit log.">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm"><x-icon name="stop"/> Akhiri Sesi</button>
            </form>
        @endif
    </div>

    <div class="ecg-meta">
        <div class="meta-item">
            <div class="meta-label">Pasien</div>
            <div class="meta-value">{{ $session->patient?->name }} ({{ $session->patient?->code }})</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Session ID</div>
            <div class="meta-value mono small">{{ $session->session_code }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Device ID</div>
            <div class="meta-value mono">{{ $device?->device_uid ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Waktu Mulai</div>
            <div class="meta-value">{{ $session->started_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? '-' }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Detak Jantung (BPM)</div>
            <div class="meta-value"><span id="ecgBpm">—</span></div>
        </div>
        @if(!$hideMetaCards)
        <div class="meta-item">
            <div class="meta-label">Sampling Rate (metadata)</div>
            <div class="meta-value">{{ $session->sampling_rate ? $session->sampling_rate.' Hz' : 'Belum ada data' }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Sampel Diterima</div>
            <div class="meta-value" id="ecgSampleCount">{{ number_format($session->sample_count, 0, ',', '.') }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Paket Diterima</div>
            <div class="meta-value" id="ecgPacketCount">{{ number_format($session->packet_count, 0, ',', '.') }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Paket Terakhir</div>
            <div class="meta-value" id="ecgLastPacket">{{ $session->packet_count > 0 ? '#'.($device?->last_packet_number ?? '-') : '-' }}</div>
        </div>
        @endif
        <div class="meta-item">
            <div class="meta-label">Status Transmisi</div>
            <div class="meta-value"><span class="live-dot" id="ecgLiveDot"></span><span id="ecgStatus">Memuat…</span></div>
        </div>
    </div>

    @if($initialSamples->isEmpty())
        <x-empty-state
            title="Belum ada data EKG yang diterima untuk sesi ini"
            message="Grafik akan muncul otomatis ketika paket data EKG asli diterima oleh worker dan tersimpan di database. Tidak ada data simulasi yang ditampilkan."
            icon="activity"/>
        <div class="chart-box" style="display:none">
            <canvas id="ecgChart"></canvas>
        </div>
    @else
        <div class="chart-box tall">
            <canvas id="ecgChart"></canvas>
        </div>
    @endif

    @if(!$hideNote)
    <div class="method-note">
        <strong>Keterangan:</strong> sumbu-X menampilkan waktu sampling perangkat (timestamp asli per sampel);
        sumbu-Y menampilkan amplitudo dalam milivolt (mV) sesuai konversi perangkat.
        Nilai BPM adalah estimasi otomatis dari deteksi puncak-R pada data sampel asli perangkat
        (10 detik terakhir) — bukan diagnosis medis. Ketika koneksi terputus, grafik berhenti
        menambah data baru dan status transmisi berubah.
    </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
        (function () {
            const initial = @json($initialSamples);
            const pollUrl = '{{ $pollUrl }}';
            const hasInitial = initial.length > 0;

            const chartBox = document.querySelector('.chart-box');
            const chart = HEKG.initEcgChart('ecgChart', initial);

            const statusEl = document.getElementById('ecgStatus');
            const dotEl = document.getElementById('ecgLiveDot');
            const sampleEl = document.getElementById('ecgSampleCount');
            const packetEl = document.getElementById('ecgPacketCount');
            const lastPacketEl = document.getElementById('ecgLastPacket');
            const bpmEl = document.getElementById('ecgBpm');

            function setStatus(text, active) {
                if (statusEl) statusEl.textContent = text;
                if (dotEl) dotEl.classList.toggle('paused', !active);
            }

            // BPM dihitung dari data grafik (sampel asli perangkat), bukan angka acak.
            function updateBpm() {
                if (!bpmEl) return;
                const res = HEKG.estimateBpm(chart.data.datasets[0].data);
                if (res.status === 'ok') {
                    bpmEl.textContent = res.bpm + ' bpm';
                } else if (res.status === 'wait') {
                    bpmEl.textContent = chart.data.datasets[0].data.length ? 'Menghitung…' : 'Menunggu data…';
                } else {
                    bpmEl.textContent = 'Tidak terdeteksi';
                }
            }
            updateBpm();

            if (!hasInitial) {
                setStatus('Menunggu data perangkat…', true);
            }

            // Tampilkan grafik (sebelumnya tersembunyi bila kosong) saat data pertama masuk.
            let revealed = hasInitial;

            HEKG.startEcgPolling({
                pollUrl,
                chart,
                interval: 250, // realtime: poll tiap 250 ms (inkremental via after_id, payload kecil)
                onStatus: setStatus,
                onStats: (data) => {
                    if (!revealed && data.samples && data.samples.length) {
                        revealed = true;
                        if (chartBox) chartBox.style.display = '';
                    }
                    if (sampleEl && data.session) sampleEl.textContent = Number(data.session.sample_count || 0).toLocaleString('id-ID');
                    if (packetEl && data.session) packetEl.textContent = Number(data.session.packet_count || 0).toLocaleString('id-ID');
                    if (lastPacketEl && data.last_packet_number !== null && data.last_packet_number !== undefined) {
                        lastPacketEl.textContent = '#' + data.last_packet_number;
                    }
                    updateBpm();
                },
                onError: () => {},
            });
        })();
    </script>
    @endpush
@endonce
