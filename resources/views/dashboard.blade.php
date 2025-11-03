<x-layouts.app :title="__('Dashboard')">
    @php
        /** @var \App\Models\User $authUser */
        $authUser = auth()->user();

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        $thisMonthReports = \App\Models\DailyReport::query()
            ->where('user_id', $authUser->id)
            ->whereBetween('report_date', [$monthStart, $today])
            ->latest('report_date')
            ->get();

        $thisMonthCount = $thisMonthReports->count();
        $thisMonthMinutes = $thisMonthReports->sum(function ($r) {
            if ($r->work_start_time && $r->work_end_time) {
                return $r->work_end_time->diffInMinutes($r->work_start_time);
            }
            return 0;
        });

        $recentReports = \App\Models\DailyReport::query()
            ->where('user_id', $authUser->id)
            ->latest('report_date')
            ->latest('id')
            ->limit(5)
            ->get();
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">ダッシュボード</h1>
            <div class="flex gap-2">
                <flux:button tag="a" href="{{ route('reports.index') }}" size="sm" wire:navigate>
                    自分の日報
                </flux:button>
                @if (in_array($authUser->role_id, [1, 2], true))
                    <flux:button tag="a" href="{{ route('admin.reports.index') }}" size="sm" wire:navigate>
                        日報一覧（管理）
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between">
                <div class="font-medium">作業日報の入力を開始</div>
                <flux:button tag="a" href="{{ route('reports.create') }}" variant="primary" class="px-6 py-2.5"
                    wire:navigate>
                    作業日報入力開始
                </flux:button>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="text-sm text-gray-500">今月の登録件数</div>
                <div class="mt-1 text-3xl font-semibold">{{ $thisMonthCount }}</div>
                <div class="mt-3 text-sm text-gray-500">対象期間: {{ \Carbon\Carbon::parse($monthStart)->format('Y/m/d') }}
                    ～ {{ \Carbon\Carbon::parse($today)->format('Y/m/d') }}</div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="text-sm text-gray-500">今月の作業時間（合計）</div>
                <div class="mt-1 text-3xl font-semibold">
                    @php
                        $h = intdiv($thisMonthMinutes, 60);
                        $m = $thisMonthMinutes % 60;
                    @endphp
                    {{ sprintf('%d時間 %02d分', $h, $m) }}
                </div>
                <div class="mt-3 text-sm text-gray-500">自分の記録のみ集計</div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="text-sm text-gray-500">クイックアクセス</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button tag="a" href="{{ route('reports.index') }}" size="xs" wire:navigate>自分の日報
                    </flux:button>
                    @if (in_array($authUser->role_id, [1, 2], true))
                        <flux:button tag="a" href="{{ route('admin.reports.index') }}" size="xs"
                            wire:navigate>管理一覧</flux:button>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <h2 class="text-lg font-semibold">直近の日報（自分）</h2>
            <div class="mt-3 space-y-3">
                @forelse ($recentReports as $report)
                    @php
                        $label =
                            $report->project_code ?:
                            ($report->project_name ?:
                            $report->project?->code . ' ' . $report->project?->name);
                    @endphp
                    <div class="rounded border p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">{{ $report->report_date?->format('Y/m/d (D)') }}</div>
                            <div class="text-sm text-gray-600">{{ $report->work_start_time?->format('H:i') }} ～
                                {{ $report->work_end_time?->format('H:i') }}</div>
                        </div>
                        <div class="mt-1 font-medium">{{ $label ?: '未指定の工事' }}</div>
                        @if ($report->comment)
                            <div class="text-sm text-gray-700 mt-1">
                                {{ \Illuminate\Support\Str::limit($report->comment, 60) }}</div>
                        @endif
                    </div>
                @empty
                    <flux:text>まだ日報がありません。</flux:text>
                @endforelse
            </div>
        </div>
    </div>

</x-layouts.app>
