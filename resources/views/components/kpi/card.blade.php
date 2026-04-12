@props([
  'title'    => '',        // ✅ default supaya tidak undefined
  'value'    => 0,
  'icon'     => null,      // office | collection | users | id | check | x | qrcode
  'tone'     => null,      // emerald | rose | indigo | null
  'gradient' => false,     // true => gunakan brand gradient
])

@php
  $tones = [
    'emerald' => [
      'chip' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
      'accent' => 'from-emerald-400 to-emerald-600'
    ],
    'rose'    => [
      'chip' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
      'accent' => 'from-rose-400 to-rose-600'
    ],
    'indigo'  => [
      'chip' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
      'accent' => 'from-indigo-400 to-indigo-600'
    ],
  ];
  $chip = $tone && isset($tones[$tone]) ? $tones[$tone]['chip'] : null;
  $accent = $tone && isset($tones[$tone]) ? $tones[$tone]['accent'] : 'from-slate-300 to-slate-400';

  $icons = [
    'office'     => 'M3 21h18M4 21V7a2 2 0 0 1 2-2h3v16m6 0V5h3a2 2 0 0 1 2 2v14M9 9h2m-2 4h2m-2 4h2m6-8h2m-2 4h2m-2 4h2',
    'collection' => 'M3 7h18M3 12h18M3 17h18',
    'users'      => 'M17 20h5v-2a4 4 0 0 0-4-4h-1M9 20H4v-2a4 4 0 0 1 4-4h1M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
    'id'         => 'M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z M7 9h7 M7 13h4 M17 17a3 3 0 0 0-6 0',
    'check'      => 'M5 13l4 4L19 7',
    'x'          => 'M6 18L18 6M6 6l12 12',
    'qrcode'     => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v6h-6v-2h4v-4z',
  ];
  $iconPath = $icons[$icon] ?? 'M12 6v12M6 12h12';
@endphp

<div class="relative rounded-2xl p-5 {{ $gradient
  ? 'bg-brand-grad text-white ring-1 ring-white/10'
  : 'bg-white ring-1 ring-slate-200 dark:bg-navy-800 dark:ring-slate-700 text-slate-900 dark:text-slate-50' }} shadow-card overflow-hidden">

  {{-- left accent bar (subtle) --}}
  <div class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-2xl opacity-90 bg-gradient-to-b {{ $gradient ? 'from-white/30 to-white/10' : $accent }}"></div>

  <div class="flex items-center justify-between">
    <div class="text-sm/5 {{ $gradient ? 'opacity-90' : 'text-slate-500 dark:text-slate-400' }}">
      {{ $title ?? '' }}
    </div>

    @if ($icon)
      <div class="w-[38px] h-[38px] rounded-xl grid place-items-center
                  {{ $gradient ? 'bg-white/15 text-white' : ($tone ? 'bg-gradient-to-tr ' . $accent . ' text-white/95' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300') }}">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="{{ $iconPath }}"/>
        </svg>
      </div>
    @endif
  </div>

  <div class="mt-1 text-3xl font-bold">{{ is_numeric($value) ? number_format($value) : $value }}</div>

  @if (!$gradient && $chip)
    <div class="mt-2 inline-flex items-center gap-2 rounded-full px-3 py-0.5 text-xs font-medium {{ $chip }} ring-1 ring-slate-100/60">
      <span class="h-2 w-2 rounded-full bg-white/80 shadow-sm"></span>
      <span>{{ ucfirst($tone) }}</span>
    </div>
  @endif
</div>
