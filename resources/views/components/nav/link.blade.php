@props([
  'href' => '#',
  'active' => false,
])

@php
  // pastikan boolean beneran (antisipasi "1"/"0"/"true"/"false")
  $isActive = filter_var($active, FILTER_VALIDATE_BOOLEAN);
@endphp

<a
  {{ $attributes->merge(['href' => $href]) // tetap bisa override href dari pemanggil
      ->class([
        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
        // state
        'bg-white/10 text-white' => $isActive,
        'text-slate-300 hover:text-white hover:bg-white/5' => ! $isActive,
      ]) }}
  @if($isActive) aria-current="page" @endif
>
  {{ $slot }}
</a>
