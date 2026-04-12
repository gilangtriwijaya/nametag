<button {{ $attributes->merge(['class' => 'inline-flex w-full justify-center items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-white font-medium hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500']) }}>
  {{ $slot }}
</button>
