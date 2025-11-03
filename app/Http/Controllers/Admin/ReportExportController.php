<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if ($user === null || ! in_array($user->role_id, [1, 2], true)) {
            abort(403);
        }

        $query = DailyReport::query()
            ->with(['user', 'project', 'travels', 'members'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('report_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('report_date', '<=', $request->string('date_to')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $kw = '%' . $request->string('keyword') . '%';
                $q->where(function ($q) use ($kw) {
                    $q->where('project_code', 'like', $kw)
                        ->orWhere('project_name', 'like', $kw)
                        ->orWhere('comment', 'like', $kw)
                        ->orWhereHas('project', fn($pq) => $pq->where('code', 'like', $kw)->orWhere('name', 'like', $kw));
                });
            })
            ->when($request->boolean('has_travel'), fn($q) => $q->has('travels'))
            ->when($request->boolean('has_highway'), fn($q) => $q->whereHas('travels', fn($tq) => $tq->where('use_highway', true)))
            ->orderBy('report_date', 'desc')
            ->orderBy('id', 'desc');

        $reports = $query->get();

        $filename = 'reports_' . Carbon::now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        Carbon::setLocale('ja');

        $callback = function () use ($reports) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");

            // Header (Japanese labels)
            fputcsv($output, [
                '日報ID',
                '日付',
                '曜日',
                '入力者',
                'メールアドレス',
                '工事ID',
                '工事件名',
                '作業開始',
                '作業終了',
                '作業時間(分)',
                '作業内容',
                '往路_出発',
                '往路_到着',
                '往路_距離(km)',
                '往路_高速',
                '復路_出発',
                '復路_到着',
                '復路_距離(km)',
                '復路_高速',
                '他作業員',
                '登録日時',
                '更新日時',
            ]);

            foreach ($reports as $report) {
                $outbound = $report->travels->firstWhere('direction', 'outbound');
                $return = $report->travels->firstWhere('direction', 'return');

                $start = $report->work_start_time ? Carbon::parse($report->work_start_time) : null;
                $end = $report->work_end_time ? Carbon::parse($report->work_end_time) : null;
                $workMinutes = ($start && $end) ? $start->diffInMinutes($end, false) : null;

                $weekday = Carbon::parse($report->report_date)->isoFormat('dd');

                $projectCode = $report->project_code ?: ($report->project->code ?? null);
                $projectName = $report->project_name ?: ($report->project->name ?? null);

                $members = $report->members->pluck('member_name')->implode(',');

                fputcsv($output, [
                    $report->id,
                    $report->report_date?->format('Y-m-d'),
                    $weekday,
                    $report->user?->name,
                    $report->user?->email,
                    $projectCode,
                    $projectName,
                    $start?->format('H:i'),
                    $end?->format('H:i'),
                    $workMinutes,
                    $report->comment,
                    $outbound?->depart_time?->format('H:i'),
                    $outbound?->arrive_time?->format('H:i'),
                    $outbound?->distance_km,
                    $outbound ? ($outbound->use_highway ? '有' : '無') : null,
                    $return?->depart_time?->format('H:i'),
                    $return?->arrive_time?->format('H:i'),
                    $return?->distance_km,
                    $return ? ($return->use_highway ? '有' : '無') : null,
                    $members,
                    $report->created_at?->format('Y-m-d H:i:s'),
                    $report->updated_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
