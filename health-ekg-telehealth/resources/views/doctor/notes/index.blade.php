@extends('layouts.app')
@section('title', 'Catatan Pemeriksaan')
@section('content')
<div class="page-head">
    <div>
        <h1>Catatan Pemeriksaan</h1>
        <div class="page-desc">
            Catatan klinis dengan audit trail. Sistem tidak menghasilkan diagnosis atau rekomendasi medis otomatis.
        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="file-text"/> Buat Catatan Baru</h2>
    <form method="POST" action="{{ route('doctor.notes.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="patient_id">Pasien *</label>
                <select id="patient_id" name="patient_id" required>
                    <option value="">- Pilih pasien -</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->code }}</option>
                    @endforeach
                </select>
                @if($patients->isEmpty())
                    <p class="small" style="margin-top:6px;color:#94a3b8">
                        Belum ada pasien yang ditugaskan kepada Anda. Hubungi Admin Puskesmas untuk menautkan pasien.
                    </p>
                @endif
            </div>
            <div class="field full">
                <label for="body">Isi Catatan *</label>
                <textarea id="body" name="body" rows="4" required maxlength="5000"
                          placeholder="Keluhan, observasi klinis, dan hasil pemeriksaan pasien…"></textarea>
            </div>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="btn btn-accent">Simpan Catatan</button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Catatan Saya ({{ $notes->total() }})</h2>
    @if($notes->isEmpty())
        <x-empty-state title="Belum ada catatan" message="Catatan yang Anda buat akan tampil di sini dan otomatis muncul di akun pasien yang bersangkutan." icon="file-text"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Pasien</th><th>Isi Catatan</th><th>Diubah</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @foreach($notes as $note)
                    <tr>
                        <td class="small">{{ $note->created_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ $note->patient?->name ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($note->body, 160) }}</td>
                        <td class="small">{{ $note->updated_at?->locale('id')->translatedFormat('d M H:i') }}</td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('doctor.notes.edit', $note) }}"><x-icon name="edit"/> Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $notes->links() }}
    @endif
</div>
@endsection
