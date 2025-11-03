<?php

use App\Models\DailyReport;
use App\Models\DailyReportTravel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use function Livewire\Volt\{state};

state([]);

$activeUsers = User::query()->where('is_active', true)->orderBy('name')->get();

$query = DailyReport::query()
    ->with(['user', 'project'])
    ->when(Request::filled('date_from'), fn($q) => $q->whereDate('report_date', '>=', Request::string('date_from')))
    ->when(Request::filled('date_to'), fn($q) => $q->whereDate('report_date', '<=', Request::string('date_to')))
    ->when(Request::filled('user_id'), fn($q) => $q->where('user_id', Request::integer('user_id')))
    ->when(Request::filled('keyword'), function ($q) {
        $kw = '%' . Request::string('keyword') . '%';
        $q->where(function ($q) use ($kw) {
            $q->where('project_code', 'like', $kw)->orWhere('project_name', 'like', $kw)->orWhere('comment', 'like', $kw)->orWhereHas('project', fn($pq) => $pq->where('code', 'like', $kw)->orWhere('name', 'like', $kw));
        });
    })
    ->when((int) Request::query('has_travel') === 1, fn($q) => $q->has('travels'))
    ->when((int) Request::query('has_highway') === 1, fn($q) => $q->whereHas('travels', fn($t) => $t->where('use_highway', true)));

// Lightweight aggregates for list view
$query->withCount('travels')->addSelect([
    'has_highway' => DailyReportTravel::query()->selectRaw('MAX(use_highway)')->whereColumn('daily_report_id', 'daily_reports.id'),
]);

$reports = $query->orderBy('report_date', 'desc')->orderBy('id', 'desc')->paginate(20)->withQueryString();

?>

<div class="space-y-6">
    <!-- Title and CSV -->
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">日報一覧（管理）</h1>
        <?php if (auth()->user()->role_id === 1): ?>
        <a href="<?= route('admin.reports.export', request()->query()) ?>"
            class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">CSV出力</a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= route('admin.reports.index') ?>" class="grid grid-cols-1 gap-3 md:grid-cols-6">
        <div class="md:col-span-1">
            <label class="block text-sm text-gray-600">日付From</label>
            <input type="date" name="date_from" value="<?= e(request('date_from')) ?>"
                class="mt-1 w-full rounded border px-2 py-1" />
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm text-gray-600">日付To</label>
            <input type="date" name="date_to" value="<?= e(request('date_to')) ?>"
                class="mt-1 w-full rounded border px-2 py-1" />
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm text-gray-600">入力者</label>
            <select name="user_id" class="mt-1 w-full rounded border px-2 py-1">
                <option value="">すべて</option>
                <?php foreach ($activeUsers as $u): ?>
                <option value="<?= $u->id ?>" <?= (string) $u->id === (string) request('user_id') ? 'selected' : '' ?>>
                    <?= e($u->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm text-gray-600">キーワード（工事ID・工事件名・コメント）</label>
            <input type="text" name="keyword" value="<?= e(request('keyword')) ?>"
                placeholder="例: PRJ-001 / プラント / 清掃" class="mt-1 w-full rounded border px-2 py-1" />
        </div>
        <div class="md:col-span-1 flex items-end gap-4">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="has_travel" value="1" <?= request('has_travel') ? 'checked' : '' ?> />
                <span>移動あり</span>
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="has_highway" value="1"
                    <?= request('has_highway') ? 'checked' : '' ?> />
                <span>高速あり</span>
            </label>
        </div>
        <div class="md:col-span-6 flex items-center gap-3">
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white hover:bg-black">検索する</button>
            <a href="<?= route('admin.reports.index') ?>" class="rounded border px-4 py-2 hover:bg-gray-50">クリア</a>
        </div>
    </form>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full border divide-y">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">日付</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">入力者</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">工事ID / 工事件名</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">作業時間</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">移動</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">高速</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">コメント</th>
                    <th class="px-3 py-2 text-left text-sm text-gray-600">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($reports as $report): ?>
                <?php
                $dateStr = $report->report_date?->format('Y/m/d (D)');
                $projectDisplay = $report->project_code ?: ($report->project_name ?: trim(($report->project->code ?? '') . ' ' . ($report->project->name ?? '')));
                $travelLabel = $report->travels_count === 0 ? '-' : ($report->travels_count === 1 ? '移動1' : '往復');
                $highwayLabel = (int) ($report->has_highway ?? 0) === 1 ? '有' : '無';
                $commentShort = \Illuminate\Support\Str::limit((string) $report->comment, 40);
                $workStart = $report->work_start_time ? \Illuminate\Support\Carbon::parse($report->work_start_time)->format('H:i') : '';
                $workEnd = $report->work_end_time ? \Illuminate\Support\Carbon::parse($report->work_end_time)->format('H:i') : '';
                ?>
                <tr>
                    <td class="px-3 py-2 text-sm whitespace-nowrap"><?= e($dateStr) ?></td>
                    <td class="px-3 py-2 text-sm whitespace-nowrap"><?= e($report->user?->name) ?></td>
                    <td class="px-3 py-2 text-sm">
                        <div class="whitespace-pre-line"><?= e($projectDisplay) ?></div>
                    </td>
                    <td class="px-3 py-2 text-sm whitespace-nowrap"><?= e($workStart) ?> ～ <?= e($workEnd) ?></td>
                    <td class="px-3 py-2 text-sm whitespace-nowrap">
                        <span><?= e($travelLabel) ?></span>
                        <?php if ((int) ($report->has_highway ?? 0) === 1): ?>
                        <span class="ml-1 inline-block rounded bg-yellow-100 px-1 text-[10px] text-yellow-800">高</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-sm whitespace-nowrap"><?= e($highwayLabel) ?></td>
                    <td class="px-3 py-2 text-sm"><?= e($commentShort) ?></td>
                    <td class="px-3 py-2 text-sm">
                        <div class="flex gap-2">
                            <a class="text-blue-600 hover:underline" href="/admin/reports/<?= $report->id ?>">詳細</a>
                            <?php if (auth()->user()->role_id === 1): ?>
                            <a class="text-green-700 hover:underline"
                                href="/admin/reports/<?= $report->id ?>/edit">編集</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-3">
        <?php foreach ($reports as $report): ?>
        <?php
        $dateStr = $report->report_date?->format('Y/m/d (D)');
        $projectDisplay = $report->project_code ?: ($report->project_name ?: trim(($report->project->code ?? '') . ' ' . ($report->project->name ?? '')));
        $travelLabel = $report->travels_count === 0 ? '-' : ($report->travels_count === 1 ? '移動1' : '往復');
        $highwayLabel = (int) ($report->has_highway ?? 0) === 1 ? '有' : '無';
        $commentShort = \Illuminate\Support\Str::limit((string) $report->comment, 40);
        $workStart = $report->work_start_time ? \Illuminate\Support\Carbon::parse($report->work_start_time)->format('H:i') : '';
        $workEnd = $report->work_end_time ? \Illuminate\Support\Carbon::parse($report->work_end_time)->format('H:i') : '';
        ?>
        <div class="rounded border p-3">
            <div class="flex justify-between text-sm">
                <div class="font-medium"><?= e($dateStr) ?></div>
                <div><?= e($report->user?->name) ?></div>
            </div>
            <div class="mt-1 text-sm"><?= e($projectDisplay) ?></div>
            <div class="mt-1 flex items-center gap-2 text-sm">
                <span><?= e($workStart) ?> ～ <?= e($workEnd) ?></span>
                <span>・<?= e($travelLabel) ?></span>
                <span>・高速: <?= e($highwayLabel) ?></span>
            </div>
            <div class="mt-1 text-sm text-gray-700"><?= e($commentShort) ?></div>
            <div class="mt-2 flex gap-3 text-sm">
                <a class="text-blue-600 hover:underline" href="/admin/reports/<?= $report->id ?>">詳細</a>
                <?php if (auth()->user()->role_id === 1): ?>
                <a class="text-green-700 hover:underline" href="/admin/reports/<?= $report->id ?>/edit">編集</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination & Count -->
    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-600">
            <?php if ($reports->total() > 0): ?>
            全<?= $reports->total() ?>件中 <?= $reports->firstItem() ?>〜<?= $reports->lastItem() ?>件を表示
            <?php else: ?>
            件数: 0
            <?php endif; ?>
        </div>
        <div>
            <?= $reports->links() ?>
        </div>
    </div>
</div>
