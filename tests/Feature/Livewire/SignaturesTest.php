<?php

use App\Enums\UserRole;
use App\Events\IssuePageCountUpdated;
use App\Livewire\Magazines\Edit;
use App\Livewire\Timone\Grid;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

// Organizzazione in segnature (2026-10-29): riepilogo nella fascia, confini
// sulla griglia, impostazioni della rivista. Il calcolo è in tests/Unit/SignaturePlanTest.php.

beforeEach(fn () => Event::fake([IssuePageCountUpdated::class]));

function signatureIssue(int $pages, array $magazine = []): Issue
{
    return Issue::factory()->create(['magazine_id' => Magazine::factory()->create($magazine)->id, 'total_pages' => $pages]);
}

function signatureEditor(Issue $issue): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($issue->magazine);

    return $user;
}

test('the summary shows full signatures, remainder breakdown and the suggestion', function () {
    $issue = signatureIssue(40);

    Livewire::actingAs(signatureEditor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSeeHtml('data-testid="summary-signatures"')
        ->assertSee('1 trentaduesimo + 8 pagine')
        ->assertSee('(8)')
        ->assertSee('→ +24 = 64 · −8 = 32')
        ->assertDontSeeHtml('data-testid="summary-signatures-warning"');
});

test('a total that is not a multiple of 4 shows the warning', function () {
    $issue = signatureIssue(34);

    Livewire::actingAs(signatureEditor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('1 trentaduesimo + 2 pagine')
        ->assertSeeHtml('data-testid="summary-signatures-warning"')
        ->assertSee('34 non è multiplo di 4: non stampabile così');
});

test('the count follows the total pages, also for another user through polling', function () {
    $issue = signatureIssue(32);
    $a = signatureEditor($issue);
    $b = signatureEditor($issue);

    $other = Livewire::actingAs($b)->test(Grid::class, ['issue' => $issue->fresh()])->assertSee('1 trentaduesimo');

    Livewire::actingAs($a)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '40')->call('resizePages')
        ->assertSee('1 trentaduesimo + 8 pagine');

    // B non ha ricaricato: il poll vede pages_updated_at cambiato e ridisegna.
    $this->actingAs($b);
    $other->call('checkForUpdates')->assertSee('1 trentaduesimo + 8 pagine');
});

test('signature boundaries on the grid: label «32° n. 2» on page 33, alternating bands, nothing on the covers by default', function (string $view) {
    $issue = signatureIssue(64);
    $html = Livewire::actingAs(signatureEditor($issue))->test(Grid::class, ['issue' => $issue])->call('setViewMode', $view)->html();

    $page33 = $issue->pages()->where('position', 33)->value('id');
    $page32 = $issue->pages()->where('position', 32)->value('id');

    expect(substr_count($html, 'data-testid="signature-label"'))->toBe(1)
        ->and($html)->toContain('32° n. 2')
        ->and(preg_match('/data-page-id="'.$page33.'"[^>]*data-signature="even"[^>]*data-signature-start/s', $html))->toBe(1)
        ->and(preg_match('/data-page-id="'.$page32.'"[^>]*data-signature="odd"/s', $html))->toBe(1)
        ->and(preg_match('/data-cover-slot="[^"]+"[^>]*data-signature=/s', $html))->toBe(0)
        ->and($html)->toContain('data-testid="toggle-signatures"');
})->with(['griglia', 'doppia', 'lista']);

test('with covers included they are in the first signature and the boundary moves to page 29', function () {
    $issue = signatureIssue(60, ['signature_includes_covers' => true]);
    $grid = Livewire::actingAs(signatureEditor($issue))->test(Grid::class, ['issue' => $issue]);
    $html = $grid->html();

    $page29 = $issue->pages()->where('position', 29)->value('id');

    expect(preg_match('/data-page-id="'.$page29.'"[^>]*data-signature-start/s', $html))->toBe(1)
        ->and(preg_match('/data-cover-slot="I"[^>]*data-signature="odd"/s', $html))->toBe(1);
    $grid->assertSee('2 trentaduesimi');
});

test('a 16-page signature size is read from the magazine', function () {
    $issue = signatureIssue(40, ['signature_pages' => 16]);

    Livewire::actingAs(signatureEditor($issue))->test(Grid::class, ['issue' => $issue])
        ->assertSee('2 sedicesimi + 8 pagine')
        ->assertSee('16° n. 2')
        ->assertSee('16° n. 3');
});

test('magazine settings: saved, validated, and open timoni are told through the polling signal', function () {
    $issue = signatureIssue(32);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    DB::table('issues')->where('id', $issue->id)->update(['pages_updated_at' => '2026-01-01 00:00:00']);

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $issue->magazine])
        ->assertSet('signature_pages', 32)->assertSet('signature_includes_covers', false)
        ->set('signature_pages', 24)->call('save')->assertHasErrors(['signature_pages']);

    Livewire::actingAs($admin)->test(Edit::class, ['magazine' => $issue->magazine])
        ->set('signature_pages', 16)->set('signature_includes_covers', true)->call('save')->assertHasNoErrors();

    expect($issue->magazine->fresh()->signature_pages)->toBe(16)
        ->and($issue->magazine->fresh()->signature_includes_covers)->toBeTrue()
        ->and(DB::table('issues')->where('id', $issue->id)->value('pages_updated_at'))->not->toBe('2026-01-01 00:00:00');
});
