{{-- resources/views/employees/partials/_pagination.blade.php --}}
@if($employees->lastPage() > 1)
<div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    {{-- Info section --}}
    <div class="text-sm text-slate-600 dark:text-slate-400">
        <span class="font-semibold text-slate-700 dark:text-slate-300">
            {{ $employees->firstItem() ?? 0 }}-{{ $employees->lastItem() ?? 0 }}
        </span>
        dari
        <span class="font-semibold text-slate-700 dark:text-slate-300">
            {{ $employees->total() }}
        </span>
        data
    </div>

    {{-- Pagination links --}}
    <div class="flex flex-wrap items-center gap-1">
        {{-- Previous --}}
        @if($employees->onFirstPage())
            <button disabled class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed dark:border-slate-700 dark:bg-slate-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        @else
            <a href="{{ $employees->previousPageUrl() }}" class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        @endif

        {{-- Page numbers --}}
        <div class="flex flex-wrap gap-1">
            @php
                $currentPage = $employees->currentPage();
                $lastPage = $employees->lastPage();
                $range = 2; // Show 2 pages before and after current
                
                $start = max(1, $currentPage - $range);
                $end = min($lastPage, $currentPage + $range);
                
                // If we're showing pages starting from 3+, show page 1
                if ($start > 1) {
                    $showFirstDots = ($start > 2);
                } else {
                    $showFirstDots = false;
                }
                
                // If we're showing pages ending before lastPage-2, show page lastPage
                if ($end < $lastPage) {
                    $showLastDots = ($end < $lastPage - 1);
                } else {
                    $showLastDots = false;
                }
            @endphp

            {{-- First page --}}
            @if($start > 1)
                <a href="{{ $employees->url(1) }}" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">1</a>
                @if($showFirstDots)
                    <span class="h-9 flex items-center px-1 text-slate-400">…</span>
                @endif
            @endif

            {{-- Range pages --}}
            @for($i = $start; $i <= $end; $i++)
                @if($i === $currentPage)
                    <button disabled class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-brand-600 bg-brand-600 text-sm font-semibold text-white dark:border-brand-500 dark:bg-brand-500">{{ $i }}</button>
                @else
                    <a href="{{ $employees->url($i) }}" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">{{ $i }}</a>
                @endif
            @endfor

            {{-- Last page --}}
            @if($end < $lastPage)
                @if($showLastDots)
                    <span class="h-9 flex items-center px-1 text-slate-400">…</span>
                @endif
                <a href="{{ $employees->url($lastPage) }}" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">{{ $lastPage }}</a>
            @endif
        </div>

        {{-- Next --}}
        @if($employees->hasMorePages())
            <a href="{{ $employees->nextPageUrl() }}" class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @else
            <button disabled class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed dark:border-slate-700 dark:bg-slate-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        @endif
    </div>
</div>
@endif
