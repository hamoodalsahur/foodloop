<?php

// The "Report this listing" form on the listing page (customers only).

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->listing = dealAt(0.4, ['title' => 'Sandwich & pastry pack']);
    $this->page = route('listings.show', $this->listing);
    $this->send = fn (array $form = []) => $this->actingAs($this->customer)
        ->post(route('reports.store', $this->listing), ['reason' => 'food_quality', 'details' => 'Bread was stale at pickup.', ...$form]);
});

it('offers the report form to signed-in customers only', function () {
    $this->get($this->page)->assertDontSee('Report this listing');

    $this->actingAs($this->listing->seller->user)->get($this->page)->assertDontSee('Report this listing');

    $this->actingAs($this->customer)->get($this->page)
        ->assertSeeInOrder(['Something wrong? Report this listing', 'What is wrong?', 'Halal information', 'Food quality', 'Wrong description', 'Other', 'Send report']);
});

it('saves a report, thanks the customer and puts it in front of the admins', function () {
    ($this->send)()->assertRedirect($this->page)->assertSessionHas('status', 'Thanks. An admin will look at your report.');

    $report = Report::sole();

    expect($report->reporter->is($this->customer))->toBeTrue()
        ->and($report->listing->is($this->listing))->toBeTrue()
        ->and($report->reason)->toBe(ReportReason::FoodQuality)
        ->and($report->details)->toBe('Bread was stale at pickup.')
        ->and($report->status)->toBe(ReportStatus::Open);

    $this->get($this->page)->assertSee('Thanks. An admin will look at your report.');

    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertSeeInOrder(['Open reports', '1', 'Sandwich &amp; pastry pack', '“Bread was stale at pickup.”'], false);
});

it('checks the form', function (array $form, string $field, string $message) {
    ($this->send)($form)->assertRedirect();

    expect(Report::count())->toBe(0);

    // Back on the listing page the form is open again, with the message in it.
    $this->actingAs($this->customer)->get($this->page)
        ->assertSee($message)
        ->assertSee('<details open', false);
})->with([
    'no reason' => [['reason' => ''], 'reason', 'Choose what is wrong.'],
    'made-up reason' => [['reason' => 'too_tasty'], 'reason', 'Choose what is wrong.'],
    '"other" without saying what' => [['reason' => 'other', 'details' => ''], 'details', 'Tell us what is wrong, in a few words.'],
    'too long' => [['details' => str_repeat('a', 501)], 'details', 'Keep it under 500 characters.'],
]);

it('lets details be left empty for the named reasons', function () {
    ($this->send)(['reason' => 'halal_info', 'details' => ''])->assertSessionHas('status');

    expect(Report::sole()->details)->toBeNull();
});

it('takes one open report per customer per listing', function () {
    ($this->send)();

    ($this->send)(['details' => 'Again!'])
        ->assertRedirect($this->page)
        ->assertSessionHasErrors(['report' => 'You’ve already reported this listing. An admin is looking at it.']);

    expect(Report::count())->toBe(1);

    // Someone else can still report it, and so can they once theirs is dealt with.
    $this->actingAs(User::factory()->create())->post(route('reports.store', $this->listing), ['reason' => 'food_quality']);
    Report::query()->update(['status' => 'dismissed']);
    ($this->send)();

    expect(Report::count())->toBe(3);
});

it('is for customers only', function () {
    auth()->logout();
    $this->post(route('reports.store', $this->listing), ['reason' => 'food_quality'])->assertRedirect('/login');

    $this->actingAs($this->listing->seller->user)->post(route('reports.store', $this->listing), ['reason' => 'food_quality'])->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('reports.store', $this->listing), ['reason' => 'food_quality'])->assertForbidden();

    expect(Report::count())->toBe(0);
});

it('cannot report a listing the public cannot see', function (string $state) {
    $hidden = Listing::factory()->{$state}()->create();

    $this->actingAs($this->customer)->post(route('reports.store', $hidden), ['reason' => 'food_quality'])->assertNotFound();
})->with(['draft', 'paused']);

it('can still report a deal after it has closed', function () {
    $closed = Listing::factory()->closed()->create();

    $this->actingAs($this->customer)->post(route('reports.store', $closed), ['reason' => 'food_quality'])->assertSessionHas('status');
});
