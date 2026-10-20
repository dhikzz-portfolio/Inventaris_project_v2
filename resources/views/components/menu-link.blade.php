@props(['href', 'active', 'icon', 'label', 'badge' => 0])
<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
       'bg-amber-500 text-slate-900 shadow' => $active,
       'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $active,
   ])>
    <span class="material-icons-round text-xl leading-none">{{ $icon }}</span>
    <span class="flex-1">{{ $label }}</span>
    @if ($badge > 0)
        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
</a>
