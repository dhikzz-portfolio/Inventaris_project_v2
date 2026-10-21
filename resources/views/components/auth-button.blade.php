@props(['label'])

<button
    type="submit"
    data-submit-button
    class="group inline-flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition-all duration-200 hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 active:bg-brand-800 disabled:opacity-75 disabled:hover:bg-brand-600 sm:min-h-[56px]"
>
    <span data-submit-label class="inline-flex items-center gap-2">
        {{ $label }}
        <span class="material-icons-round text-lg transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
    </span>
    <span data-submit-loading class="inline-flex items-center gap-2" style="display: none;">
        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="text-white/30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <circle class="text-white" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="30" stroke-dashoffset="45" stroke-linecap="round"></circle>
        </svg>
        Memproses...
    </span>
</button>
