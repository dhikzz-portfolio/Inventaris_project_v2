<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36pt 45pt; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        .kop { width: 100%; border-bottom: 3px solid #000; padding-bottom: 8px; margin-bottom: 16px; }
        .kop td { vertical-align: middle; }
        .kop img { max-width: 64px; max-height: 64px; }
        .kop .instansi { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 9pt; }
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 10px 0 4px; }
        .nomor { text-align: center; font-size: 9pt; margin-bottom: 14px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th, table.data td { border: 1px solid #444; padding: 5px 7px; font-size: 9.5pt; }
        table.data th { background: #f0f0f0; text-align: left; }
        .info td { padding: 2.5px 0; vertical-align: top; }
        .info td:first-child { width: 155px; }
        .ttd { width: 100%; margin-top: 34px; }
        .ttd td { width: 33.33%; text-align: center; vertical-align: top; font-size: 9.5pt; }
        .ttd .role { font-weight: bold; text-decoration: underline; }
        .ttd .space { height: 70px; }
        .ttd img { max-height: 55px; max-width: 140px; }
        .ttd .nama { font-weight: bold; margin-top: 6px; }
        .footer { margin-top: 18px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }
        .catatan { font-size: 9pt; background: #f7f7f7; border: 1px dashed #aaa; padding: 7px; margin-bottom: 12px; }
    </style>
</head>
<body>
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
        <tr><td>Nama Kegiatan</td><td>: {{ $loan->event?->name ?: '— (peminjaman rutin)' }}</td></tr>
        <tr><td>Penanggung Jawab</td><td>: {{ $loan->event?->pic_name ?: '—' }}</td></tr>
        <tr><td>Tanggal Kegiatan</td><td>: {{ $loan->event ? $loan->event->start_date->format('d M Y').' s.d. '.$loan->event->end_date->format('d M Y') : '—' }}</td></tr>
        <tr><td>Tanggal Pinjam</td><td>: {{ $loan->start_date->format('d M Y H:i') }}</td></tr>
        <tr><td>Tanggal Kembali</td><td>: {{ $loan->due_date->format('d M Y H:i') }}</td></tr>
        <tr><td>Tujuan Peminjaman</td><td>: {{ $loan->purpose }}</td></tr>
        <tr><td>Peminjam</td><td>: {{ $loan->borrower?->name }} ({{ $loan->borrower?->username }}) — {{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</td></tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th width="24">No</th>
                <th width="110">ID Internal</th>
                <th>Nama Barang</th>
                <th width="46">Jumlah</th>
                <th width="76">Kondisi Keluar</th>
                <th width="76">Kondisi Kembali</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($loan->loanItems as $i => $li)
                <tr>
                    <td align="center">{{ $i + 1 }}</td>
                    <td style="font-family: monospace;">{{ $li->item?->internal_id }}</td>
                    <td>{{ $li->item?->name }}<br><span style="font-size:8pt;color:#666;">{{ $li->item?->room?->code }} — {{ $li->item?->room?->name }}</span></td>
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
                'name' => $approval->approver?->name,
                'signature_path' => $approval->signature_path,
            ];
        }
    @endphp

    <table class="ttd">
        <tr>
            <td>
                <div class="role">Peminjam</div>
                <div class="space"></div>
                <div class="nama">{{ $loan->borrower?->name ?: '..............................' }}</div>
                <div>NIM/NIP: ....................</div>
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
</body>
</html>
