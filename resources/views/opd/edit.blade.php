<x-layouts.admin :title="'Ubah OPD – Anambas-ID'">
  <x-slot:header>
    <h1 class="text-2xl font-semibold tracking-tight">Ubah OPD</h1>
  </x-slot:header>

  @include('opd.form', ['opd' => $opd])
</x-layouts.admin>
