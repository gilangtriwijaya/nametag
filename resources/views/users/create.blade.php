<x-layouts.admin :title="'Tambah Pengguna'">
  @include('users._form', [
    'action' => route('users.store'),
    'method' => 'POST',
    'user'   => $user,
    'opds'   => $opds,
    'roles'  => $roles,
  ])
</x-layouts.admin>
