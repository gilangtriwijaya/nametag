@props(['name'])
<input type="checkbox" name="{{ $name }}" {{ $attributes->merge(['class' => 'rounded border-slate-300 text-sky-600 focus:ring-sky-500']) }}>
