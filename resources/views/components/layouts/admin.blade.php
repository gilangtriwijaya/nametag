@props(['title' => null])

@php
    $authUser  = auth()->user();
    $isSuper   = $authUser?->hasRole('superadmin');
    $isOrg     = $authUser?->hasRole('org_admin');
    $hasHeader = isset($header) && trim($header) !== '';
    $pageTitle = $title ?: ($hasHeader ? strip_tags($header) : null);

    // ===== Helper role case-insensitive =====
    $roleNames = collect($authUser?->getRoleNames() ?? [])
        ->map(fn ($r) => mb_strtolower($r))
        ->all();

    $hasRoleLike = function (array $candidates) use ($roleNames): bool {
        foreach ($candidates as $c) {
            if (in_array(mb_strtolower($c), $roleNames, true)) {
                return true;
            }
        }
        return false;
    };

    $isBagor    = $hasRoleLike(['admin_bagor','admin bagor','verifikator bagor','verif bagor']);
    $isAdminOpd = $hasRoleLike(['admin opd','admin-opd','admin_opd','Admin OPD']);
    $isVerOpd   = $hasRoleLike(['verifikator opd','verifikator-opd','verifikator_opd','Verifikator OPD','verifikator']);

    // Hanya superadmin yang boleh melihat menu Log Scan QR
    $canSeeScanLogs = $isSuper;

    $userInitial = $authUser && $authUser->name
        ? mb_strtoupper(mb_substr($authUser->name, 0, 1))
        : 'U';
@endphp

<!doctype html>
<html lang="id" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $pageTitle ? ($pageTitle.' · Anambas-ID') : 'Anambas-ID' }}</title>

  {{-- Favicon (use local logo) --}}
  <link rel="icon" href="{{ asset('images/logo-pemda.png') }}" />
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-pemda.png') }}" />
  <link rel="apple-touch-icon" href="{{ asset('images/logo-pemda.png') }}" />

  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>[x-cloak]{display:none!important}</style>

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: {
              50:'#eef7ff',100:'#d7edff',200:'#b3ddff',300:'#86c8ff',400:'#52acff',
              500:'#1e90ff',600:'#0f79e6',700:'#0e62bd',800:'#0e4f98',900:'#0f447e'
            },
            navy: {900:'#0b1220',800:'#0f172a'}
          },
          boxShadow: {
            card:'0 10px 40px -12px rgba(2,6,23,.20)',
            soft:'0 6px 24px -10px rgba(2,6,23,.18)'
          },
          backgroundImage:{
            'radial-soft':'radial-gradient(1200px 600px at 75% -100px, rgba(30,144,255,.10), transparent)',
            'radial-dark':'radial-gradient(1200px 600px at 75% -100px, rgba(30,144,255,.16), transparent)'
          }
        }
      }
    }
  </script>

  @stack('styles')
</head>
<body
  class="h-full bg-brand-50 text-slate-800 dark:bg-navy-900 dark:text-slate-100"
  x-data="{
    open:false,
    dark:(localStorage.theme==='dark'),
    collapsed: JSON.parse(localStorage.getItem('sbCollapsed') || 'false')
  }"
  x-init="
    $watch('dark', v => localStorage.theme = v ? 'dark' : 'light');
    $watch('collapsed', v => localStorage.setItem('sbCollapsed', JSON.stringify(v)));
  "
  :class="dark ? 'dark' : ''"
>

  {{-- Banner Impersonate --}}
  @if(session()->has('impersonate.by'))
    <div class="bg-amber-50 dark:bg-amber-900/30 border-b border-amber-300/60 dark:border-amber-700/60">
      <div class="max-w-7xl mx-auto px-4 md:px-6 py-3 flex items-center justify-between text-sm">
        <div class="text-amber-800 dark:text-amber-200">
          Anda sedang <strong>menyamar</strong> sebagai
          <span class="font-semibold">{{ auth()->user()->name }}</span>.
        </div>
        <form method="POST" action="{{ route('impersonate.stop') }}">
          @csrf
          <button class="rounded-lg px-3 py-1.5 bg-amber-600 text-white font-medium hover:bg-amber-700">
            Kembali ke akun semula
          </button>
        </form>
      </div>
    </div>
  @endif

  <!-- Overlay mobile -->
  <div x-show="open" x-cloak x-transition.opacity @click="open=false"
       class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm md:hidden"></div>

  <div class="min-h-full flex">

    {{-- SIDEBAR --}}
    <aside
      class="fixed z-40 inset-y-0 left-0 bg-navy-800/95 text-slate-200 border-r border-slate-800/50 md:static transform transition-all duration-200 ease-in-out"
      :class="[ open ? 'translate-x-0' : '-translate-x-full md:translate-x-0', collapsed ? 'w-6' : 'w-62' ]"
      aria-label="Sidebar"
    >
      {{-- Brand --}}
      <div class="h-16 flex items-center gap-3 px-4 border-b border-slate-700/50">
        <img src="{{ asset('images/logo-pemda.png') }}" class="h-10 w-10" alt="Logo">
        <div class="transition-all duration-200"
             :class="collapsed ? 'opacity-0 pointer-events-none w-0' : 'opacity-100 w-auto'">
          <div class="text-base font-bold leading-tight">Anambas-ID</div>
          <div class="text-xs text-slate-400">Kab. Kepulauan Anambas</div>
        </div>
      </div>

      {{-- Menu --}}
      <nav class="px-3 py-4 space-y-1">

        {{-- Dashboard --}}
        <x-nav.link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
          <svg class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-white"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M12 3v1m0 16v1m9-10h-1M4 12H3m15.36 6.36l-.7-.7M6.34 6.34l-.7-.7m12.02 0l-.7.7M6.34 17.66l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
          </svg>
          <span class="transition-all" :class="collapsed ? 'md:hidden' : 'inline'">Dashboard</span>
          </x-nav.link>
        @if($isSuper || $isOrg)
          <x-nav.link href="{{ route('logs.index') }}" :active="request()->routeIs('logs.*')">
            <svg class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-white"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
            </svg>
            <span class="transition-all" :class="collapsed ? 'md:hidden' : 'inline'">Log Aktivitas</span>
          </x-nav.link>
        @endif

        {{-- Log Scan QR --}}
        @if($canSeeScanLogs)
          <x-nav.link href="{{ route('scan-logs.index') }}" :active="request()->routeIs('scan-logs.*')">
            <svg class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-white"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 4h6v6H4V4zm0 10h6v6H4v-6zm10-10h6v6h-6V4zm3 10v6m-3-3h6"/>
            </svg>
            <span class="transition-all" :class="collapsed ? 'md:hidden' : 'inline'">Log Scan QR</span>
          </x-nav.link>
        @endif

        {{-- Pegawai --}}
        <x-nav.link href="{{ route('employees.index') }}" :active="request()->routeIs('employees.*')">
          <svg class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-white"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M7 9h7M7 13h4M17 17a3 3 0 00-6 0"/>
          </svg>
          <span class="transition-all" :class="collapsed ? 'md:hidden' : 'inline'">Pegawai</span>
        </x-nav.link>

        {{-- Unit Kerja (normalized) --}}
        @if($isSuper || $isOrg)
          <x-nav.link href="{{ route('unit-kerja.index') }}" :active="request()->routeIs('unit-kerja.*')">
            <svg class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4z"/>
            </svg>
            <span class="transition-all" :class="collapsed ? 'md:hidden' : 'inline'">Unit Kerja</span>
          </x-nav.link>
        @endif

      </nav>
    </aside>

    {{-- AREA KONTEN --}}
    <div class="flex-1 min-w-0 md:ml-0">

      {{-- TOPBAR --}}
      <header class="sticky top-0 z-20 bg-white/80 dark:bg-navy-800/80 backdrop-blur border-b border-slate-200/80 dark:border-slate-700/60">
        <div class="h-16 flex items-center gap-3 px-4 md:px-6">

          {{-- Toggle sidebar (mobile) --}}
          <button class="md:hidden rounded-lg p-2 hover:bg-slate-900/5 dark:hover:bg-white/5"
                  @click="open = true" aria-label="Buka Sidebar">
            <svg class="h-6 w-6 text-slate-700 dark:text-slate-200"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>

          {{-- Collapse (desktop) --}}
          <button class="hidden md:inline-flex rounded-lg p-2 hover:bg-slate-900/5 dark:hover:bg-white/5"
                  @click="collapsed = !collapsed"
                  :title="collapsed ? 'Perluas sidebar' : 'Sembunyikan sidebar'">
            <svg x-show="!collapsed" x-cloak class="h-6 w-6 text-slate-600 dark:text-slate-300"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                    d="M6 4h2v16H6M10 12l4-4m0 0l4 4m-4-4v8"/>
            </svg>
            <svg x-show="collapsed" x-cloak class="h-6 w-6 text-slate-600 dark:text-slate-300"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                    d="M6 4h2v16H6M18 12l-4 4m0 0l-4-4m4 4V8"/>
            </svg>
          </button>

          <div class="flex-1 font-semibold truncate">{{ $pageTitle ?? '—' }}</div>

          {{-- Bersihkan Cache --}}
          @if (Route::has('admin.flush'))
            <form method="POST" action="{{ route('admin.flush') }}" class="ml-2">
              @csrf
              <button class="px-2 py-1 text-xs rounded bg-slate-200 hover:bg-slate-300
                             dark:bg-slate-700 dark:hover:bg-slate-600">
                Bersihkan Cache
              </button>
            </form>
          @endif

          {{-- USER MENU --}}
          <div class="relative" x-data="{u:false}">
                <button class="flex items-center gap-3 rounded-lg px-2 py-1 hover:bg-slate-900/5 dark:hover:bg-white/5"
                    @click="u=!u" aria-haspopup="menu" :aria-expanded="u">
              <div class="h-8 w-8 rounded-full bg-brand-600 text-white grid place-items-center font-bold">
                {{ $userInitial }}
              </div>
              <div class="hidden md:block text-left">
                <div class="text-sm font-semibold leading-tight truncate max-w-[12rem]">
                  {{ $authUser->name ?? 'User' }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Profil</div>
              </div>
              <svg class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                      clip-rule="evenodd" />
              </svg>
            </button>

            <div x-show="u" x-cloak x-transition @click.outside="u=false"
                 class="absolute right-0 mt-2 w-80 rounded-xl border border-slate-200 bg-white shadow-card
                        dark:bg-navy-800 dark:border-slate-700 overflow-hidden">
              <div class="px-4 py-3 text-sm">
                <div class="font-semibold">{{ $authUser->name ?? 'User' }}</div>
                <div class="text-slate-500 dark:text-slate-400 truncate">{{ $authUser->email ?? '' }}</div>
              </div>

              {{-- Impersonate cepat (khusus superadmin) --}}
              @if ($isSuper)
                <div class="border-t border-slate-200 dark:border-slate-700"></div>
                @php
                  // Static shortcut: hanya tampilkan satu akun 'setda'.
                  $setdaUser = \App\Models\User::where('username', 'setda')
                    ->orWhere('email', 'like', '%setda%')
                    ->orWhere('name', 'like', '%Setda%')
                    ->with(['roles','opd'])
                    ->first();
                @endphp

                <div class="px-4 py-2 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  Impersonasi · Setda
                </div>

                @if ($setdaUser && Route::has('impersonate.start'))
                  <div class="max-h-64 overflow-y-auto">
                    <form method="POST" action="{{ route('impersonate.start', ['user' => $setdaUser->getKey()]) }}">
                      @csrf
                      <button type="submit" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-white/5">
                        <div class="text-sm font-medium">{{ $setdaUser->name }}</div>
                        <div class="text-xs text-slate-500">
                          {{ $setdaUser->opd->nama ?? '—' }} · {{ $setdaUser->roles->pluck('name')->join(', ') }}
                        </div>
                      </button>
                    </form>
                  </div>
                @else
                  <div class="px-4 pb-2 text-sm text-slate-500">
                    Pengguna Setda tidak ditemukan.
                  </div>
                @endif
              @endif

              <div class="border-t border-slate-200 dark:border-slate-700"></div>
              <form method="POST" action="{{ route('sso.back') }}" class="p-2">
                @csrf
                <button class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-900/5 dark:hover:bg-white/5">
                  Keluar
                </button>
              </form>
            </div>
          </div>
        </div>
      </header>

      {{-- HEADER SLOT opsional --}}
      @if($hasHeader)
        <div class="px-4 md:px-6 pt-4">
          {!! $header !!}
        </div>
      @endif

      <main class="p-4 md:p-6 lg:p-8">
        {{ $slot }}
      </main>
    </div>
  </div>

  @stack('scripts')
  {{-- Toast container + helper JS for lightweight notifications (global) --}}
  <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2 pointer-events-none"></div>
  <script>
    (function(){
      const colors = { success: 'bg-emerald-600', error: 'bg-red-600', info: 'bg-sky-600', warning: 'bg-yellow-500' };
      function makeEl(type, message) {
        const el = document.createElement('div');
        el.className = `max-w-xs text-white px-4 py-2 rounded shadow-lg ${colors[type] || colors.info}`;
        el.style.opacity = '0';
        el.style.pointerEvents = 'auto';
        el.innerHTML = `<div class="flex items-center gap-3"><div class="w-5 h-5 animate-spin border-2 border-white/30 rounded-full border-t-white"></div><div class="flex-1">${message}</div></div>`;
        return el;
      }

      window.showToast = function(type, message, ttl = 5000) {
        try {
          const cont = document.getElementById('toast-container');
          if (!cont) return null;
          const id = `t_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;
          const wrapper = document.createElement('div');
          wrapper.dataset.toastId = id;
          wrapper.style.pointerEvents = 'auto';
          const el = makeEl(type, message);
          wrapper.appendChild(el);
          cont.appendChild(wrapper);
          requestAnimationFrame(() => { wrapper.style.transition = 'opacity 160ms'; wrapper.style.opacity = '1'; });

          if (ttl && ttl > 0) {
            wrapper._timeout = setTimeout(() => {
              wrapper.style.opacity = '0';
              wrapper.addEventListener('transitionend', () => wrapper.remove(), { once: true });
            }, ttl);
          }

          return id;
        } catch (e) { return null; }
      };

      window.updateToast = function(id, type, message, ttl = 5000) {
        try {
          const cont = document.getElementById('toast-container');
          if (!cont) return false;
          const wrapper = Array.from(cont.children).find(ch => ch.dataset && ch.dataset.toastId === id);
          if (!wrapper) return false;
          // clear previous timeout if any
          if (wrapper._timeout) { clearTimeout(wrapper._timeout); wrapper._timeout = null; }
          // replace content
          wrapper.innerHTML = '';
          const el = makeEl(type, message);
          // remove spinner for success/error
          if (type === 'success' || type === 'error') {
            el.querySelector('.animate-spin')?.classList.remove('animate-spin');
            el.querySelector('.animate-spin')?.classList.add('opacity-0');
          }
          wrapper.className = '';
          wrapper.appendChild(el);
          // auto-dismiss after ttl
          if (ttl && ttl > 0) {
            wrapper._timeout = setTimeout(() => {
              wrapper.style.opacity = '0';
              wrapper.addEventListener('transitionend', () => wrapper.remove(), { once: true });
            }, ttl);
          }
          return true;
        } catch (e) { return false; }
      };

      window.dismissToast = function(id) {
        try {
          const cont = document.getElementById('toast-container');
          if (!cont) return false;
          const wrapper = Array.from(cont.children).find(ch => ch.dataset && ch.dataset.toastId === id);
          if (!wrapper) return false;
          if (wrapper._timeout) clearTimeout(wrapper._timeout);
          wrapper.style.opacity = '0';
          wrapper.addEventListener('transitionend', () => wrapper.remove(), { once: true });
          return true;
        } catch (e) { return false; }
      };
    })();
  </script>
</body>
</html>
