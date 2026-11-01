@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<div class="page-head">
    <div>
        <h1>Notifikasi</h1>
        <div class="page-desc">Notifikasi dibuat dari event nyata sistem: data EKG baru, sesi berjalan, jeda data, dan informasi administratif.</div>
    </div>
    <div class="page-actions">
        <form method="POST" action="{{ route('notifications.readAll') }}" data-confirm="Tandai semua notifikasi sebagai sudah dibaca?">
            @csrf
            <button type="submit" class="btn btn-outline"><x-icon name="check-circle"/> Tandai Semua Dibaca</button>
        </form>
    </div>
</div>

<div class="card">
    @if($notifications->isEmpty())
        <x-empty-state title="Belum ada notifikasi" message="Notifikasi akan muncul ketika ada event pemeriksaan atau perangkat yang relevan dengan akun Anda."/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Kategori</th><th>Judul</th><th>Isi</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @foreach($notifications as $n)
                    <tr>
                        <td class="small">{{ $n->created_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td><x-badge :status="$n->category === 'data' ? 'warning' : 'info'">{{ $n->category }}</x-badge></td>
                        <td><strong>{{ $n->title }}</strong></td>
                        <td class="small">{{ $n->body }}</td>
                        <td>{{ $n->is_read ? '<span class="muted small">Sudah dibaca</span>' : '<strong class="small">Baru</strong>' }}</td>
                        <td>
                            <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">{{ $n->link ? 'Buka' : 'Tandai dibaca' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $notifications->links() }}
    @endif
</div>
@endsection
