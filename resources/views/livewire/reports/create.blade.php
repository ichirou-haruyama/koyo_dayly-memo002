<?php

use App\Models\DailyReport;
use App\Models\DailyReportMember;
use App\Models\DailyReportTravel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @var array<string, mixed>
     */
    public array $form = [
        'report_date' => '',
        'project_id' => null,
        'project_code' => '',
        'project_name' => '',
        'work_start_time' => '',
        'work_end_time' => '',
        'comment' => '',
        'has_travel' => false,
        'travels' => [],
        'members' => [],
    ];

    public function mount(): void
    {
        $this->form['report_date'] = now()->toDateString();
        $this->form['work_start_time'] = '';
        $this->form['work_end_time'] = '';
        $this->form['has_travel'] = false;
        $this->form['travels'] = [];
        $this->form['members'] = [];
    }

    public function addTravel(string $direction): void
    {
        if (!in_array($direction, ['outbound', 'return'], true)) {
            return;
        }

        // 既に同方向があるなら何もしない
        foreach ($this->form['travels'] as $t) {
            if (($t['direction'] ?? null) === $direction) {
                return;
            }
        }

        $this->form['travels'][] = [
            'direction' => $direction,
            'depart_time' => '',
            'arrive_time' => '',
            'distance_km' => '',
            'use_highway' => false,
        ];
    }

    public function removeTravel(string $direction): void
    {
        $this->form['travels'] = array_values(
            array_filter($this->form['travels'], function ($t) use ($direction) {
                return ($t['direction'] ?? null) !== $direction;
            }),
        );
    }

    public function addMember(): void
    {
        $this->form['members'][] = [
            'member_name' => '',
            'note' => '',
        ];
    }

    public function removeMember(int $index): void
    {
        if (!isset($this->form['members'][$index])) {
            return;
        }

        unset($this->form['members'][$index]);
        $this->form['members'] = array_values($this->form['members']);
    }

    public function save(): void
    {
        $rules = [
            'form.report_date' => ['required', 'date'],
            'form.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'form.project_code' => ['nullable', 'string', 'max:255'],
            'form.project_name' => ['nullable', 'string', 'max:255'],
            'form.work_start_time' => ['required', 'date_format:H:i'],
            'form.work_end_time' => ['required', 'date_format:H:i', 'after:form.work_start_time'],
            'form.comment' => ['nullable', 'string', 'max:200'],
            'form.has_travel' => ['boolean'],
            'form.travels' => ['array'],
            'form.travels.*.direction' => ['required_with:form.has_travel', 'in:outbound,return'],
            'form.travels.*.depart_time' => ['nullable', 'date_format:H:i'],
            'form.travels.*.arrive_time' => ['nullable', 'date_format:H:i'],
            'form.travels.*.distance_km' => ['nullable', 'numeric', 'min:0'],
            'form.travels.*.use_highway' => ['boolean'],
            'form.members' => ['array'],
            'form.members.*.member_name' => ['nullable', 'string', 'max:255'],
            'form.members.*.note' => ['nullable', 'string', 'max:255'],
        ];

        $this->validate($rules);

        $report = DailyReport::create([
            'report_date' => $this->form['report_date'],
            'user_id' => Auth::id(),
            'project_id' => $this->form['project_id'] ?: null,
            'project_code' => Str::of((string) ($this->form['project_code'] ?? ''))->trim()->whenEmpty(fn() => null),
            'project_name' => Str::of((string) ($this->form['project_name'] ?? ''))->trim()->whenEmpty(fn() => null),
            'work_start_time' => $this->form['work_start_time'] . ':00',
            'work_end_time' => $this->form['work_end_time'] . ':00',
            'comment' => Str::of((string) ($this->form['comment'] ?? ''))->trim()->whenEmpty(fn() => null),
        ]);

        // Travels（往路・復路は最大1行ずつ）
        if (($this->form['has_travel'] ?? false) && is_array($this->form['travels'])) {
            $byDirection = [];
            foreach ($this->form['travels'] as $t) {
                $dir = $t['direction'] ?? null;
                if (!$dir || isset($byDirection[$dir])) {
                    continue;
                }
                $byDirection[$dir] = $t;
            }

            foreach (['outbound', 'return'] as $dir) {
                if (!isset($byDirection[$dir])) {
                    continue;
                }
                $t = $byDirection[$dir];
                $report->travels()->create([
                    'direction' => $dir,
                    'depart_time' => !empty($t['depart_time']) ? $t['depart_time'] . ':00' : null,
                    'arrive_time' => !empty($t['arrive_time']) ? $t['arrive_time'] . ':00' : null,
                    'distance_km' => $t['distance_km'] === '' || $t['distance_km'] === null ? null : (float) $t['distance_km'],
                    'use_highway' => (bool) ($t['use_highway'] ?? false),
                ]);
            }
        }

        // Members（名前が空の行は無視）
        if (is_array($this->form['members'])) {
            foreach ($this->form['members'] as $m) {
                $name = Str::of((string) ($m['member_name'] ?? ''))->trim();
                if ($name->isEmpty()) {
                    continue;
                }
                $report->members()->create([
                    'member_name' => (string) $name,
                    'note' => Str::of((string) ($m['note'] ?? ''))->trim()->whenEmpty(fn() => null),
                ]);
            }
        }

        session()->flash('status', '登録しました');
        $this->redirectRoute('reports.index', navigate: true);
    }
}; ?>

<section class="max-w-screen-sm mx-auto p-4">
    <h1 class="text-xl font-semibold mb-4">日報作成</h1>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4">
            <flux:input type="date" wire:model.live="form.report_date" label="日付" required />

            <flux:input type="text" wire:model.live="form.project_code" label="工事ID（任意）" placeholder="PRJ-001 など" />
            <flux:input type="text" wire:model.live="form.project_name" label="工事件名（任意）" placeholder="○○設備更新工事 など" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="time" wire:model.live="form.work_start_time" label="開始時刻" required />
                <flux:input type="time" wire:model.live="form.work_end_time" label="終了時刻" required />
            </div>

            <flux:textarea wire:model.live="form.comment" label="作業内容（任意）" rows="4" placeholder="200文字まで" />
        </div>

        <div class="border-t pt-6">
            <div class="flex items-center justify-between">
                <flux:checkbox wire:model.live="form.has_travel" label="移動あり" />

                <div class="flex gap-2" wire:show="form.has_travel">
                    <flux:button type="button" size="sm" wire:click="addTravel('outbound')"
                        :disabled="collect($form['travels'] ?? [])->contains(fn($t)=>($t['direction'] ?? null) === 'outbound')">
                        往路を追加
                    </flux:button>
                    <flux:button type="button" size="sm" wire:click="addTravel('return')"
                        :disabled="collect($form['travels'] ?? [])->contains(fn($t)=>($t['direction'] ?? null) === 'return')">
                        復路を追加
                    </flux:button>
                </div>
            </div>

            @if ($form['has_travel'])
                <div class="mt-4 space-y-4">
                    @foreach ($form['travels'] ?? [] as $idx => $t)
                        <div class="rounded border p-3" wire:key="travel-{{ $t['direction'] ?? $idx }}">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-medium">{{ ($t['direction'] ?? '') === 'outbound' ? '往路' : '復路' }}</h3>
                                <flux:button color="gray" size="xs" type="button"
                                    wire:click="removeTravel('{{ $t['direction'] ?? '' }}')">削除</flux:button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <flux:input type="time"
                                    wire:model.live="form.travels.{{ $idx }}.depart_time" label="出発" />
                                <flux:input type="time"
                                    wire:model.live="form.travels.{{ $idx }}.arrive_time" label="到着" />
                                <flux:input type="number" step="0.01" min="0"
                                    wire:model.live="form.travels.{{ $idx }}.distance_km" label="距離(km)" />
                                <div class="flex items-end">
                                    <flux:checkbox wire:model.live="form.travels.{{ $idx }}.use_highway"
                                        label="高速利用" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="border-t pt-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold">他作業員</h2>
                <flux:button type="button" size="sm" wire:click="addMember">追加</flux:button>
            </div>

            <div class="space-y-3">
                @forelse (($form['members'] ?? []) as $mi => $m)
                    <div class="rounded border p-3 grid gap-3" wire:key="member-{{ $mi }}">
                        <flux:input type="text" wire:model.live="form.members.{{ $mi }}.member_name"
                            label="氏名" placeholder="山田 太郎" />
                        <div class="grid grid-cols-5 gap-3 items-end">
                            <div class="col-span-4">
                                <flux:input type="text" wire:model.live="form.members.{{ $mi }}.note"
                                    label="備考（任意）" />
                            </div>
                            <div class="col-span-1 text-right">
                                <flux:button type="button" color="gray" size="xs"
                                    wire:click="removeMember({{ $mi }})">削除</flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm text-gray-500">追加ボタンから作業員を登録できます</flux:text>
                @endforelse
            </div>
        </div>

        <div class="pt-2">
            <flux:button type="submit" variant="primary" class="w-full">登録する</flux:button>
        </div>
    </form>
</section>
