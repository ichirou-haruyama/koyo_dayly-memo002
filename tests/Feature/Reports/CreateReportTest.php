<?php

declare(strict_types=1);

use App\Models\DailyReport;
use App\Models\User;
use Livewire\Volt\Volt;

it('renders the create report page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('reports.create'))
        ->assertOk()
        ->assertSeeLivewire('reports.create');
});

it('creates a daily report with minimal fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('reports.create')
        ->set('form.report_date', now()->toDateString())
        ->set('form.work_start_time', '09:00')
        ->set('form.work_end_time', '17:30')
        ->call('save')
        ->assertHasNoErrors();

    expect(DailyReport::where('user_id', $user->id)->count())->toBe(1);
});
