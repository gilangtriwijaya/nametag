@props(['for' => null, 'value' => null])
<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700']) }}>
  {{ $value ?? $slot }}
</label>
