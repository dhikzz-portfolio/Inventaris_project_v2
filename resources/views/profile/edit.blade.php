<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Profil Saya</h2>
    </x-slot>

    @if (session('warning'))
        <div role="alert" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            {{ session('warning') }}
        </div>
    @endif

    <div class="py-2">
        <div class="mx-auto max-w-7xl space-y-5">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
