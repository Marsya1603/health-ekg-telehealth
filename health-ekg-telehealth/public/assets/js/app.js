/* ============================================================
   HealthEKG Telehealth — JavaScript aplikasi
   - Helper fetch JSON (CSRF)
   - Grafik EKG + polling sampel baru (real-time via polling)
   - Grafik QoS sederhana
   - UX: sidebar, konfirmasi form, flash
   Tidak ada data simulasi: polling hanya menampilkan sampel
   yang benar-benar tersimpan di database.
   ============================================================ */

(function () {
    'use strict';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    /* ---------- Warna tema untuk Chart.js ---------- */
    const COLORS = {
        cyan: '#0EA5C9',
        navy: '#0E2A57',
        green: '#16A34A',
        red: '#DC2626',
        amber: '#D97706',
        gray: '#94A3B8',
        grid: '#E4E9F2',
        text: '#5B6B84',
    };

    if (window.Chart) {
        Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';
        Chart.defaults.color = COLORS.text;
        Chart.defaults.animation = false;
        Chart.defaults.maintainAspectRatio = false;
    }

    /* ---------- Util ---------- */
    function fmtTime(ms) {
        if (!ms) return '-';
        const d = new Date(ms);
        const p = (n) => String(n).padStart(2, '0');
        return `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    function fmtDateTime(ms) {
        if (!ms) return '-';
        const d = new Date(ms);
        return d.toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'medium' });
    }

    async function getJSON(url) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    /* ---------- Grafik EKG ---------- */
    function initEcgChart(canvasId, initialSamples) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !window.Chart) return null;

        const data = (initialSamples || []).map((s) => ({ x: s.t, y: s.v }));

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'EKG (mV)',
                    data: data,
                    borderColor: COLORS.green,
                    borderWidth: 1.4,
                    pointRadius: 0,
                    tension: 0,
                    spanGaps: false,
                }],
            },
            options: {
                parsing: false,
                normalized: true,
                scales: {
                    x: {
                        type: 'linear',
                        grid: { color: COLORS.grid },
                        ticks: {
                            maxTicksLimit: 9,
                            callback: (v) => fmtTime(v),
                        },
                    },
                    y: {
                        grid: { color: COLORS.grid },
                        title: { display: true, text: 'Amplitudo (mV)' },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => fmtDateTime(items[0].parsed.x),
                            label: (item) => `${item.parsed.y.toFixed(3)} mV`,
                        },
                    },
                },
            },
        });

        chart.HEKG_lastId = initialSamples && initialSamples.length
            ? initialSamples[initialSamples.length - 1].id : 0;

        return chart;
    }

    function pushSamples(chart, samples, maxPoints = 6000) {
        if (!chart || !samples || !samples.length) return;

        const ds = chart.data.datasets[0];
        samples.forEach((s) => ds.data.push({ x: s.t, y: s.v }));

        while (ds.data.length > maxPoints) ds.data.shift();

        chart.HEKG_lastId = samples[samples.length - 1].id;
        chart.update('none');
    }

    /* ---------- Estimasi BPM dari data EKG asli ----------
       Pipeline ala Pan-Tompkins ringan pada jendela 10 detik terakhir:
       1) ambil jendela waktu berdasarkan timestamp asli per sampel,
       2) lowpass (~25 ms) -> derivatif -> kuadrat -> integrasi (150 ms):
          kompleks QRS yang tajam menjadi gugus dominan; derivatif sekaligus
          membuang baseline wander, gelombang T/P dan derau landai tertekan,
       4) puncak-R: pindai kiri-ke-kanan; saat lintas ambang adaptif
          (0,35 x maksimum), catat titik tertinggi gugus tersebut lalu
          lanjutkan setelah jeda refraktori 300 ms (batas atas ~200 bpm) —
          satu gugus QRS dihitung satu detak, gelombang T/P dan derau
          landai tidak melintasi ambang,
       5) BPM = 60000 / median interval R-R (RR di luar 300-2000 ms,
          outlier >40% dari median, dan pola RR acak dibuang sehingga
          sinyal derau dominan tidak menghasilkan angka palsu).
       Input: array {x: epoch ms, y: mV} terurut naik (data grafik).
       Output: { status: 'ok'|'wait'|'none', bpm?: number }
         'wait' = data belum cukup untuk dihitung,
         'none' = data cukup tetapi detak tidak terdeteksi.
       Ini estimasi otomatis, bukan diagnosis medis. */
    function movingAvg(src, w) {
        const n = src.length;
        const out = new Float64Array(n);
        const pre = new Float64Array(n + 1);
        for (let i = 0; i < n; i++) pre[i + 1] = pre[i] + src[i];
        for (let i = 0; i < n; i++) {
            const a = Math.max(0, i - w), b = Math.min(n, i + w + 1);
            out[i] = (pre[b] - pre[a]) / (b - a);
        }
        return out;
    }

    function estimateBpm(points) {
        if (!points || points.length < 50) return { status: 'wait' };

        // 1) Jendela 10 detik terakhir.
        const xEnd = points[points.length - 1].x;
        let start = points.length - 1;
        while (start > 0 && points[start - 1].x >= xEnd - 10000) start--;
        const win = points.slice(start);
        const span = win.length ? win[win.length - 1].x - win[0].x : 0;
        if (win.length < 50 || span < 4000) return { status: 'wait' };

        const n = win.length;
        const rate = n / (span / 1000); // sampel/detik efektif dari timestamp asli

        // 2) Lowpass -> derivatif -> kuadrat -> integrasi (Pan-Tompkins ringan).
        //    Derivatif otomatis membuang baseline wander (naik-turun lambat),
        //    jadi tidak perlu detrend terpisah yang justru bikin artefak.
        const lp = movingAvg(Float64Array.from(win, (p) => p.y), Math.max(2, Math.round(rate * 0.025)));
        const d = new Float64Array(n);
        for (let i = 1; i < n; i++) d[i] = lp[i] - lp[i - 1];
        const sq = new Float64Array(n);
        for (let i = 0; i < n; i++) sq[i] = d[i] * d[i];
        // Jendela integrasi 150 ms TOTAL (±75 ms): kompleks QRS menjadi satu
        // gugus tunggal; terlalu lebar membuat gugus saling menempel.
        const integ = movingAvg(sq, Math.max(2, Math.round(rate * 0.075)));

        // 4) Tolak sinyal datar / derau dominan: derau murni menghasilkan
        //    sinyal terintegrasi yang "gemuk" merata (puncak tak jauh di atas
        //    latar belakangnya), sedangkan EKG asli punya gugus QRS yang
        //    menonjol jauh di atas latar belakang (persentil-25).
        let ymaxI = 0;
        for (let i = 0; i < n; i++) if (integ[i] > ymaxI) ymaxI = integ[i];
        if (ymaxI <= 0) return { status: 'none' };
        const p25I = Array.from(integ).sort((a, b) => a - b)[Math.floor(n * 0.25)];
        if (p25I > 0 && ymaxI < 3 * p25I) return { status: 'none' };

        // 5) Puncak-R: pindai kiri-ke-kanan. Saat integ melintasi ambang
        //    adaptif (0,35 x maksimum), catat titik tertinggi dalam gugus
        //    (blob QRS) tersebut, lalu lanjutkan setelah jeda refraktori
        //    300 ms (batas atas ~200 bpm). Satu gugus = satu detak.
        const thr = 0.35 * ymaxI;
        const peaks = [];
        let lastX = -Infinity;
        let i = 1;
        while (i < n - 1) {
            if (integ[i] >= thr && win[i].x - lastX >= 300) {
                let bi = i, bv = integ[i];
                let j = i + 1;
                while (j < n && integ[j] > thr) {
                    if (integ[j] > bv) { bv = integ[j]; bi = j; }
                    j++;
                }
                peaks.push({ x: win[bi].x, a: bv });
                lastX = win[bi].x;
                i = j;
            } else {
                i++;
            }
        }

        // 6) Interval R-R -> BPM (median lebih tahan puncak yang terlewat).
        const rrAll = [];
        for (let i = 1; i < peaks.length; i++) {
            const rr = peaks[i].x - peaks[i - 1].x;
            if (rr >= 300 && rr <= 2000) rrAll.push(rr);
        }
        if (rrAll.length < 2) return { status: 'none' };
        const sorted = rrAll.slice().sort((a, b) => a - b);
        const med = sorted[Math.floor(sorted.length / 2)];
        const rrGood = rrAll.filter((v) => Math.abs(v - med) <= 0.4 * med);
        const use = rrGood.length >= 2 ? rrGood : rrAll;
        // Keteraturan: detak jantung asli menghasilkan RR yang konsisten;
        // pola acak (derau dominan, mis. elektroda lepas) ditolak.
        const tight = use.filter((v) => Math.abs(v - med) <= 0.25 * med);
        if (tight.length < 2 || tight.length < 0.6 * use.length) return { status: 'none' };
        // Konsistensi amplitudo puncak: kompleks R asli seragam tingginya,
        // puncak akibat derau murni tersebar lebar amplitudonya.
        const amps = peaks.map((p) => p.a).sort((a, b) => a - b);
        const medA = amps[Math.floor(amps.length / 2)];
        if (medA < 0.55 * ymaxI) return { status: 'none' };
        const ampTight = amps.filter((v) => Math.abs(v - medA) <= 0.4 * medA).length;
        if (ampTight < 0.6 * amps.length) return { status: 'none' };
        const medUse = use.slice().sort((a, b) => a - b)[Math.floor(use.length / 2)];
        return { status: 'ok', bpm: Math.round(60000 / medUse) };
    }

    /* ---------- Polling sampel EKG ---------- */
    function startEcgPolling(config) {
        const {
            pollUrl,
            chart,
            interval = 2500,
            onStatus,   // callback(statusText, isActive)
            onStats,    // callback(data)
            onError,    // callback()
        } = config;

        if (!pollUrl || !chart) return;

        let stopped = false;
        let busy = false;

        async function tick() {
            if (stopped || busy) return;
            busy = true;
            try {
                const url = pollUrl + (pollUrl.includes('?') ? '&' : '?') + 'after_id=' + (chart.HEKG_lastId || 0);
                const data = await getJSON(url);

                pushSamples(chart, data.samples || []);

                if (onStats) onStats(data);

                const sessionEnded = data.session && data.session.status !== 'aktif';
                if (onStatus) {
                    if (sessionEnded) {
                        onStatus('Sesi telah berakhir — tidak ada data baru.', false);
                        stopped = true;
                    } else if (data.samples && data.samples.length) {
                        onStatus('Data diterima', true);
                    } else {
                        onStatus('Menunggu data baru…', true);
                    }
                }
            } catch (e) {
                if (onError) onError();
                if (onStatus) onStatus('Koneksi ke server terputus — mencoba ulang…', false);
            } finally {
                busy = false;
                if (!stopped) setTimeout(tick, interval);
            }
        }

        tick();
    }

    /* ---------- Grafik garis umum (QoS dsb.) ---------- */
    function initLineChart(canvasId, series, opts = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !window.Chart) return null;

        const datasets = series.map((s, i) => ({
            label: s.label,
            data: s.data,
            borderColor: s.color || [COLORS.cyan, COLORS.navy, COLORS.amber, COLORS.red][i % 4],
            backgroundColor: s.fill || 'transparent',
            borderWidth: 1.8,
            pointRadius: s.pointRadius ?? 0,
            tension: .25,
            spanGaps: true,
            yAxisID: s.yAxisID || 'y',
            type: s.type || 'line',
        }));

        return new Chart(canvas, {
            type: 'line',
            data: { datasets },
            options: {
                scales: {
                    x: {
                        type: 'linear',
                        grid: { color: COLORS.grid },
                        ticks: { maxTicksLimit: 10, callback: (v) => opts.xFormat ? opts.xFormat(v) : fmtTime(v) },
                    },
                    y: {
                        grid: { color: COLORS.grid },
                        title: { display: !!opts.yLabel, text: opts.yLabel || '' },
                        beginAtZero: opts.beginAtZero !== false,
                    },
                },
                plugins: {
                    legend: { display: series.length > 1 || opts.legend, position: 'bottom' },
                    tooltip: { callbacks: { title: (items) => fmtDateTime(items[0].parsed.x) } },
                },
            },
        });
    }

    /* ---------- UX global ---------- */
    function bindConfirmForms() {
        $$('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                const msg = form.dataset.confirm || 'Lanjutkan tindakan ini?';
                if (!window.confirm(msg)) e.preventDefault();
            });
        });
    }

    function bindFlashDismiss() {
        $$('.alert').forEach((el) => {
            if (el.dataset.noDismiss) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = '&times;';
            btn.style.cssText = 'margin-left:auto;border:none;background:none;font-size:1.1rem;cursor:pointer;color:inherit;line-height:1;';
            btn.addEventListener('click', () => el.remove());
            el.appendChild(btn);
            setTimeout(() => { el.style.transition = 'opacity .6s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 700); }, 8000);
        });
    }

    function bindSidebar() {
        const shell = $('.app-shell');
        const toggle = $('#sidebarToggle');
        const backdrop = $('#sidebarBackdrop');
        if (!shell || !toggle) return;

        toggle.addEventListener('click', () => shell.classList.toggle('sidebar-open'));
        if (backdrop) backdrop.addEventListener('click', () => shell.classList.remove('sidebar-open'));
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindConfirmForms();
        bindFlashDismiss();
        bindSidebar();
    });

    /* ---------- Ekspor API ---------- */
    window.HEKG = {
        COLORS,
        fmtTime,
        fmtDateTime,
        getJSON,
        initEcgChart,
        pushSamples,
        startEcgPolling,
        estimateBpm,
        initLineChart,
    };
})();
