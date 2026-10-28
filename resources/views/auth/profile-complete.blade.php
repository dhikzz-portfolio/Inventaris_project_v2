<x-guest-layout
    heading="Lengkapi Profil"
    subheading="Anda wajib melengkapi profil sebelum dapat mengakses dashboard."
    pageTitle="Lengkapi Profil"
>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.complete.store') }}" class="space-y-4 sm:space-y-5" id="profile-complete-form">
        @csrf

        <x-auth-input id="name" label="Nama lengkap *" :value="old('name', $user->name)" placeholder="Nama sesuai identitas" required />

        <div>
            <label for="birth_date" class="mb-2 block text-sm font-semibold text-slate-700">Tanggal lahir *</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}" @readonly($user->birth_date !== null) @required($user->birth_date === null)
                   class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        </div>

        <div class="rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            Kategori awal akun: <strong>{{ $user->borrower_type?->label() ?? 'Belum ditentukan' }}</strong>. Kategori tidak dapat diubah melalui formulir ini.
        </div>

        @if ($user->borrower_type === \App\Enums\BorrowerType::GURU)
            <x-auth-input id="degree" label="Gelar (jika ada)" :value="old('degree', $user->degree)" placeholder="Contoh: S.Pd, M.Pd" />
        @elseif ($user->borrower_type === \App\Enums\BorrowerType::SISWA)
            <div>
                <label for="class_name" class="mb-2 block text-sm font-semibold text-slate-700">Kelas *</label>
                <input id="class_name" name="class_name" type="text" value="{{ old('class_name', $user->class_name) }}" required
                       placeholder="Contoh: X RPL 1"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
            </div>
            <div>
                <label for="major_id" class="mb-2 block text-sm font-semibold text-slate-700">Jurusan *</label>
                <select id="major_id" name="major_id" required class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                    <option value="">— pilih jurusan —</option>
                    @foreach ($majors as $major)
                        <option value="{{ $major->id }}" @selected(old('major_id', $user->major_id) == $major->id)>{{ $major->name }}</option>
                    @endforeach
                </select>
            </div>
        @elseif ($user->borrower_type === \App\Enums\BorrowerType::STAF)
            <x-auth-input id="work_unit" label="Unit Kerja *" :value="old('work_unit', $user->work_unit)" placeholder="Masukkan unit kerja" required />
        @endif

        <x-auth-button label="Simpan dan Lanjutkan" />
    </form>

</x-guest-layout>
