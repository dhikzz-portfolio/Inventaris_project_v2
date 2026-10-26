@php
    $borrower = $loan->borrower;
    $loanIdentity = match ($borrower?->borrower_type?->value) {
        'SISWA' => trim(($borrower?->class_name ? 'Kelas '.$borrower->class_name : '').($borrower?->major?->name ? ' • '.$borrower->major->name : '')),
        'GURU' => $borrower?->major?->name ?? 'Guru',
        'STAF' => 'Staf / Tenaga Kependidikan',
        default => \App\Enums\Role::tryFrom((string) $borrower?->role)?->label() ?? '',
    };
@endphp

<table class="kop">
    <tr>
        <td width="72">
            @if ($setting->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($setting->logo_path))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($setting->logo_path) }}">
            @endif
        </td>
        <td>
            <div class="instansi">{{ $setting->institution_name }}</div>
            <div class="alamat">{{ $setting->address ?: '' }}</div>
            <div class="alamat">Kode Lokasi/Instansi: {{ $setting->location_code }}</div>
        </td>
    </tr>
</table>

<div class="judul">{{ $title }}</div>
<div class="nomor">Nomor: {{ $documentNumber }}</div>

<table class="info">
    {{-- Blok kegiatan hanya muncul untuk peminjaman yang benar-benar terkait kegiatan. --}}
    @if ($loan->event)
        <tr><td>Nama Kegiatan</td><td>: {{ $loan->event->name }}</td></tr>
        <tr><td>Penanggung Jawab</td><td>: {{ $loan->event->pic_name }}</td></tr>
        <tr><td>Tanggal Kegiatan</td><td>: {{ $loan->event->start_date->translatedFormat('d F Y') }} s.d. {{ $loan->event->end_date->translatedFormat('d F Y') }}</td></tr>
    @endif
    <tr><td>Tanggal Pinjam</td><td>: {{ $loan->start_date->translatedFormat('d F Y') }}</td></tr>
    <tr><td>Tanggal Kembali</td><td>: {{ $loan->due_date->translatedFormat('d F Y') }}</td></tr>
    <tr><td>Tujuan Peminjaman</td><td>: {{ $loan->purpose }}</td></tr>
    <tr>
        <td>Peminjam</td>
        <td>: {{ $borrower?->displayName() ?: '—' }} @if ($loanIdentity !== '') — {{ $loanIdentity }} @endif</td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th width="24">No</th>
            <th>Nama Barang &amp; Spesifikasi</th>
            <th width="120">Kode Inventaris Unit</th>
            <th width="46">Jumlah</th>
            <th width="72">Kondisi Keluar</th>
            <th width="72">Kondisi Kembali</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($loan->loanItems as $i => $li)
            @php
                $codes = $li->unitAssignments
                    ->map(fn ($assignment) => $assignment->unit?->inventory_code)
                    ->filter()
                    ->values();
            @endphp
            <tr>
                <td align="center">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $li->item?->name }}</strong>
                    @if ($li->item?->specification)
                        <br><span style="font-size:8pt;color:#555;">{{ $li->item->specification }}</span>
                    @endif
                    @if ($li->item?->room)
                        <br><span style="font-size:8pt;color:#666;">{{ $li->item->room->code }} — {{ $li->item->room->name }}</span>
                    @endif
                </td>
                <td class="unit-codes">
                    @if ($codes->isNotEmpty())
                        {{ $codes->implode(', ') }}
                    @else
                        —
                    @endif
                </td>
                <td align="center">{{ $li->qty_borrowed }} {{ $li->item?->unit }}</td>
                <td align="center">{{ $li->condition_out->label() }}</td>
                <td align="center">{{ $li->condition_in?->label() ?: '________' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="catatan">
    {{ $loan->event_id ? 'Demikian Berita Acara Serah Terima barang ini' : 'Demikian Surat Peminjaman barang ini' }} dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
    Barang yang tidak dikembalikan sesuai jumlah/kondisi akan tercatat dalam sistem (selisih berflag Hilang, kerusakan dengan foto bukti).
</div>

@php
    // Blok tanda tangan dibangun dari aktual persetujuan, bukan dari nomor level
    // tetap: Wakasek boleh menyetujui sendiri sehingga level 1/2 tidak lagi
    // merepresentasikan satu role tertentu.
    $signatureBlocks = [];

    foreach ($loan->approvals->sortBy('level') as $approval) {
        $signatureBlocks[] = [
            'role' => $approval->approver?->role
                ? \App\Enums\Role::from($approval->approver->role)->shortLabel()
                : 'Pihak Berwenang',
            'name' => $approval->approver?->displayName(),
            'signature_path' => $approval->signature_path,
        ];
    }
@endphp

<table class="ttd">
    <tr>
        <td>
            <div class="role">Peminjam</div>
            <div class="space"></div>
            <div class="nama">{{ $borrower?->displayName() ?: '..............................' }}</div>
            @if ($loanIdentity !== '')
                <div class="identitas">{{ $loanIdentity }}</div>
            @endif
        </td>
        @forelse ($signatureBlocks as $block)
            <td>
                <div class="role">{{ $block['role'] }}</div>
                <div class="space">
                    @if ($block['signature_path'] && \Illuminate\Support\Facades\Storage::disk('local')->exists($block['signature_path']))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($block['signature_path']) }}"><br>
                    @endif
                </div>
                <div class="nama">{{ $block['name'] ?: '..............................' }}</div>
            </td>
        @empty
            <td>
                <div class="role">Petugas / Wakasek Sarpras</div>
                <div class="space"></div>
                <div class="nama">..............................</div>
            </td>
        @endforelse
    </tr>
</table>

<div class="footer">
    Dokumen digenerate otomatis oleh Sistem Manajemen Inventaris &amp; Aset Sekolah — {{ $setting->institution_name }}<br>
    Diterbitkan oleh: {{ $loan->handoverOfficer?->name ?? $loan->approvals->last()?->approver?->name ?? 'Sistem' }} • {{ now()->format('d M Y H:i') }} • Ref: {{ $documentNumber }}
</div>
