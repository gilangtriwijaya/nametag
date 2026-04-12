@props(['type' => 'text', 'name' => null, 'value' => null, 'autofocus' => false, 'placeholder' => null])
<input type="{{ $type }}"
       name="{{ $name }}"
       value="{{ old($name, $value) }}"
       {{ $attributes->merge([
          'class' => 'mt-1 block w-full rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500',
          'placeholder' => $placeholder
       ]) }}
       @if($autofocus) autofocus @endif />
