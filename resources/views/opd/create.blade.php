<x-layouts.admin :title="'Tambah OPD'">
  @if (session('status'))
    <div class="mb-6 rounded-xl bg-emerald-50 text-emerald-700 px-4 py-3 ring-1 ring-emerald-200">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-6 rounded-xl bg-rose-50 text-rose-700 px-4 py-3 ring-1 ring-rose-200">
      <ul class="list-disc pl-5 space-y-1">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card p-6 md:p-8">
    {{-- enctype diperlukan untuk upload file --}}
    <form method="POST" action="{{ route('opd.store') }}" enctype="multipart/form-data" class="space-y-6" x-data="{preview:''}">
      @csrf
        @include('opd.form', ['opd' => $opd])
  </div>
</x-layouts.admin>
