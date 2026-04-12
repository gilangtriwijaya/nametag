<x-layouts.admin :title="'Ubah Pengguna'">
  @include('users._form', [
    'action'          => route('users.update',$user),
    'method'          => 'PUT',
    'user'            => $user,
    'opds'            => $opds,
    'roles'           => $roles,
    'currentRoleIds'  => $currentRoleIds,
  ])
</x-layouts.admin>
