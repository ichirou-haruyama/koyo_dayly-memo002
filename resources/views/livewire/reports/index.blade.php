<?php

use App\Models\DailyReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\{state, computed};

new class extends Component {
    public function with(): array
    {
        $reports = DailyReport::query()->where('user_id', Auth::id())->latest('report_date')->latest('id')->limit(20)->get();

        return compact('reports');
    }
}; ?>

<section class="max-w-screen-sm mx-auto p-4">
    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-green-50 text-green-700 border border-green-200">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">日報一覧（自分）</h1>
        <flux:button tag="a" href="{{ route('reports.create') }}" variant="primary" size="sm">新規作成</flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($reports as $report)
            <div class="rounded border p-3">
                <div class="text-sm text-gray-500">{{ $report->report_date?->format('Y/m/d (D)') }}</div>
                <div class="font-medium">
                    @php
                        $label =
                            $report->project_code ?:
                            ($report->project_name ?:
                            $report->project?->code . ' ' . $report->project?->name);
                    @endphp
                    {{ $label ?: '未指定の工事' }}
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    {{ $report->work_start_time?->format('H:i') }} ～ {{ $report->work_end_time?->format('H:i') }}
                </div>
                @if ($report->comment)
                    <div class="text-sm text-gray-700 mt-1">{{ \Illuminate\Support\Str::limit($report->comment, 40) }}
                    </div>
                @endif
            </div>
        @empty
            <flux:text>まだ日報がありません。</flux:text>
        @endforelse
    </div>
</section>
