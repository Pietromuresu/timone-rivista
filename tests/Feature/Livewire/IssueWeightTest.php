<?php

use App\Enums\UserRole;
use App\Events\IssuePageCountUpdated;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Magazines\Edit as MagazineEdit;
use App\Livewire\Timone\Grid;
use App\Livewire\Timone\WeightPanel;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Magazine;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function weightAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function weightEditorFor(Magazine $magazine): User
{
    $user = User::factory()->create(['role' => UserRole::Redattore]);
    $user->magazines()->attach($magazine);

    return $user;
}

/**
 * Crea un numero NM-like dal form reale (Issues\Create), così i parametri
 * passano davvero dalla copia dei default della rivista.
 */
// Fase 2 (2026-10-26): $totalPages sono le pagine INTERNE (copertine a
// parte): 208 interne + 6 di copertina (la rivista NM ha la bandella) è lo
// stesso numero NM dell'Excel che prima si scriveva come "214 totali".
function createWeightIssue(Magazine $magazine, User $user, int $totalPages = 208, array $weightOverrides = []): Issue
{
    $component = Livewire::actingAs($user)->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero peso')
        ->set('total_pages', $totalPages);

    foreach ($weightOverrides as $field => $value) {
        $component->set("weight.{$field}", $value);
    }

    $component->call('save')->assertHasNoErrors()->assertRedirect();

    return Issue::where('title', 'Numero peso')->firstOrFail();
}

test('creating an issue copies the magazine weight defaults onto the issue', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $issue = createWeightIssue($magazine, weightEditorFor($magazine), weightOverrides: ['n_copie' => '8200', 'n_bancali' => '5']);

    expect($issue->formato_base_mm)->toBe(210)
        ->and($issue->formato_altezza_mm)->toBe(270)
        ->and((float) $issue->grammatura_interno)->toBe(80.0)
        ->and((float) $issue->grammatura_copertina)->toBe(170.0)
        ->and($issue->pagine_copertina)->toBe(6)
        ->and((float) $issue->maggiorazione_percentuale)->toBe(2.0)
        ->and($issue->n_copie)->toBe(8200)
        ->and($issue->n_bancali)->toBe(5);

    // 208 pagine interne + 6 di copertina (I–IV e bandella, create da sole): caso NM dell'Excel.
    $weight = $issue->weight();

    expect($weight->input->pagineInterno)->toBe(208)
        ->and($weight->pesoCopiaG)->toEqualWithDelta(510.67422, 0.0001)
        ->and($weight->pesoLordoKg)->toEqualWithDelta(4287.528604, 0.0001);
});

test('weight parameters can be overridden for a single issue at creation', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $issue = createWeightIssue($magazine, weightEditorFor($magazine), weightOverrides: ['grammatura_interno' => '90']);

    expect((float) $issue->grammatura_interno)->toBe(90.0)
        ->and((float) $magazine->fresh()->grammatura_interno)->toBe(80.0);
});

test('the creation form marks fields that differ from the magazine default', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->assertDontSee('modificato')
        ->set('weight.grammatura_interno', '90')
        ->assertSee('modificato')
        ->set('weight.grammatura_interno', '80.00')
        ->assertDontSee('modificato');
});

test('the creation form shows a live weight preview', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', 208)
        ->assertSee('510,7 g')
        ->assertSee('± 31 g');
});

test('changing the magazine defaults does not change the weight of an existing issue', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $issue = createWeightIssue($magazine, weightEditorFor($magazine));
    $before = $issue->weight()->pesoCopiaG;

    Livewire::actingAs(weightAdmin())->test(MagazineEdit::class, ['magazine' => $magazine])
        ->set('weight.grammatura_interno', '100')
        ->set('weight.formato_altezza_mm', '297')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $magazine->fresh()->grammatura_interno)->toBe(100.0)
        ->and($issue->fresh()->weight()->pesoCopiaG)->toBe($before);

    $log = ActivityLog::where('action', 'magazine.weight_defaults_changed')->first();

    expect($log)->not->toBeNull()
        ->and($log->issue_id)->toBeNull()
        ->and($log->new_values)->toMatchArray(['grammatura_interno' => '100', 'formato_altezza_mm' => '297']);

    // ...ma un numero creato DOPO usa i nuovi default.
    $later = Livewire::actingAs(weightAdmin())->test(IssueCreate::class, ['magazine' => $magazine->fresh()]);

    expect($later->get('weight')['grammatura_interno'])->toBe('100');
});

test('changing the issue page count changes the calculated weight', function () {
    Event::fake([IssuePageCountUpdated::class]);

    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user, totalPages: 208);
    $before = $issue->weight()->pesoCopiaG;

    Livewire::actingAs($user)->test(Grid::class, ['issue' => $issue])
        ->set('newTotalPages', '212')
        ->call('resizePages')
        ->assertDispatched('issue-page-count-changed');

    $after = $issue->fresh()->weight();

    // 4 pagine interne in più = 2 fogli 21×27 a 80 g/m² × 1,02.
    expect($after->input->pagineInterno)->toBe(212)
        ->and($after->pesoCopiaG - $before)->toEqualWithDelta(2 * 567 * 80 / 10000 * 1.02, 0.0001);
});

test('the weight panel re-renders with the new page count when notified', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user, totalPages: 208);

    $panel = Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('208 pag.');

    $issue->update(['total_pages' => 212]);

    $panel->dispatch('issue-page-count-changed')->assertSee('212 pag.');
});

test('the weight panel shows "Parametri peso da configurare" when parameters are missing', function () {
    $magazine = Magazine::factory()->create();
    $issue = Issue::factory()->create(['magazine_id' => $magazine->id, 'total_pages' => 0]);

    Livewire::actingAs(weightEditorFor($magazine))->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('Parametri peso da configurare')
        ->assertSee('Base (mm)');
});

test('the weight panel shows an odd page count as a blocking error', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user, totalPages: 207);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('devono essere un numero pari');
});

test('editing issue weight parameters is saved and logged', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_interno', '70')
        ->set('params.peso_reale_g', '505.5')
        ->call('saveParams')
        ->assertHasNoErrors();

    $issue->refresh();
    $log = ActivityLog::where('action', 'issue.weight_params_changed')->first();

    expect((float) $issue->grammatura_interno)->toBe(70.0)
        ->and((float) $issue->peso_reale_g)->toBe(505.5)
        ->and($log)->not->toBeNull()
        ->and($log->issue_id)->toBe($issue->id)
        ->and(array_keys($log->new_values))->toBe(['grammatura_interno', 'peso_reale_g']);
});

test('read-only users cannot edit issue weight parameters', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $issue = createWeightIssue($magazine, weightEditorFor($magazine));
    $viewer = User::factory()->create(['role' => UserRole::SolaLettura]);
    $viewer->magazines()->attach($magazine);

    Livewire::actingAs($viewer)->test(WeightPanel::class, ['issue' => $issue])
        ->assertDontSee('Modifica parametri')
        ->set('params', ['grammatura_interno' => '10'])
        ->call('saveParams');

    expect((float) $issue->fresh()->grammatura_interno)->toBe(80.0);
});

test('the suggested surcharge is shown and only an admin can copy it to the magazine defaults', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $editor = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $editor);
    $issue->update(['peso_reale_g' => 515.68]);

    // Redattore: vede il suggerimento ma non può salvarlo nella rivista.
    Livewire::actingAs($editor)->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('Usa questa maggiorazione per i prossimi numeri')
        ->assertDontSee('Copia nei default della rivista')
        ->call('applySuggestedSurcharge');

    expect((float) $magazine->fresh()->maggiorazione_percentuale)->toBe(2.0);

    $expected = round((515.68 / (471.744 + 28.917) - 1) * 100, 2);

    Livewire::actingAs(weightAdmin())->test(WeightPanel::class, ['issue' => $issue])
        ->call('applySuggestedSurcharge')
        ->assertHasNoErrors();

    expect((float) $magazine->fresh()->maggiorazione_percentuale)->toBe($expected)
        // Mai applicata al numero stesso.
        ->and((float) $issue->fresh()->maggiorazione_percentuale)->toBe(2.0);
});

// --- Grammature dalla modale e protezione dagli errori di unità (2026-10-26) ---

test('the modal shows the paper section with the weight per page of each grammage', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->assertSee(['Carta', 'Grammatura interno (g/m²)', 'Grammatura copertina (g/m²)', 'Pagine copertina'])
        ->assertSee('80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g', escape: false)
        ->assertSee('170 g/m² → 4,820 g a pagina · 6 pagine = 28,9 g', escape: false);
});

test('a grammage typed as the weight of a page is rejected with the unit message', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_interno', '0,8')
        ->call('saveParams')
        ->assertHasErrors(['params.grammatura_interno' => 'min'])
        ->assertSee('Inserisci la grammatura della carta in g/m² (es. 80), non il peso di una pagina. Il peso a pagina viene calcolato automaticamente.');

    expect((float) $issue->fresh()->grammatura_interno)->toBe(80.0);
});

test('a grammage above the maximum is rejected', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_copertina', '450')
        ->call('saveParams')
        ->assertHasErrors(['params.grammatura_copertina' => 'max'])
        ->assertSee('Valore troppo alto per una carta da rivista, controlla l&#039;unità di misura.', escape: false);

    expect((float) $issue->fresh()->grammatura_copertina)->toBe(170.0);
});

test('grammage limits come from config, not hard-coded', function () {
    config(['peso_rivista.grammatura.min' => 50]);

    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_interno', '45')
        ->call('saveParams')
        ->assertHasErrors(['params.grammatura_interno' => 'min']);
});

test('cover pages must be even and between 2 and 12', function (string $pages) {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.pagine_copertina', $pages)
        ->call('saveParams')
        ->assertHasErrors('params.pagine_copertina')
        ->assertSee('Le pagine di copertina devono essere un numero pari tra 2 e 12.');
})->with(['0', '5', '14']);

test('comma and dot decimals save the same grammage', function (string $typed) {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_interno', $typed)
        ->call('saveParams')
        ->assertHasNoErrors();

    expect((float) $issue->fresh()->grammatura_interno)->toBe(80.5);
})->with(['80,5', '80.5']);

test('comma decimals are accepted in the creation form and in the magazine settings', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $issue = createWeightIssue($magazine, weightEditorFor($magazine), weightOverrides: ['grammatura_copertina' => '170,5']);

    expect((float) $issue->grammatura_copertina)->toBe(170.5);

    Livewire::actingAs(weightAdmin())->test(MagazineEdit::class, ['magazine' => $magazine])
        ->set('weight.grammatura_interno', '90,5')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $magazine->fresh()->grammatura_interno)->toBe(90.5);
});

test('editing the grammage from the modal changes the issue weight, never the magazine defaults', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);
    $before = $issue->weight()->pesoCopiaG;

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.grammatura_interno', '90')
        ->set('params.grammatura_copertina', '200')
        // Fase 2: le pagine di copertina di un numero derivano dalle
        // copertine esistenti — un valore scritto nella modale è ignorato.
        ->set('params.pagine_copertina', '4')
        ->call('saveParams')
        ->assertHasNoErrors();

    $issue->refresh();
    $magazine->refresh();

    expect($issue->weight()->pesoCopiaG)->not->toBe($before)
        ->and((float) $issue->grammatura_interno)->toBe(90.0)
        ->and((float) $issue->grammatura_copertina)->toBe(200.0)
        ->and($issue->pagine_copertina)->toBe(6)
        ->and((float) $magazine->grammatura_interno)->toBe(80.0)
        ->and((float) $magazine->grammatura_copertina)->toBe(170.0)
        ->and($magazine->pagine_copertina)->toBe(6)
        ->and(ActivityLog::where('action', 'magazine.weight_defaults_changed')->exists())->toBeFalse();
});

test('the modal previews the new weight and the bracket change before saving', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->assertSee('Nessuna variazione rispetto al valore salvato (510,7 g).')
        ->set('params.grammatura_interno', '70')
        // 208 × 1,9845 + 28,917 = 441,693 × 1,02 = 450,52686 g: −60,1 g, scaglione 1.000 → 500.
        ->assertSee('450,5 g')
        ->assertSee('−60,1 g rispetto al valore salvato (510,7 g), passa da scaglione 1.000 g a 500 g.');

    // Solo anteprima: nulla è stato salvato.
    expect((float) $issue->fresh()->grammatura_interno)->toBe(80.0);
});

test('the modal marks values that differ from the magazine and can restore its defaults', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);
    $issue->update(['grammatura_interno' => 90]);

    $panel = Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->assertSee('modificato')
        ->assertSee('Ripristina default rivista')
        ->set('params.n_copie', '8200')
        ->call('restoreMagazineDefaults')
        ->assertDontSee('modificato')
        ->assertDontSee('Ripristina default rivista');

    expect($panel->get('params')['grammatura_interno'])->toBe('80')
        // La tiratura è solo del numero: il ripristino non la tocca.
        ->and($panel->get('params')['n_copie'])->toBe('8200')
        // Non salva da solo.
        ->and((float) $issue->fresh()->grammatura_interno)->toBe(90.0);
});

test('a grammage of 0.8 already in the database shows no weight, only the error', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);
    // Dato sporco salvato prima della validazione (scrittura diretta, senza form).
    $issue->update(['grammatura_interno' => 0.8]);

    $weight = $issue->fresh()->weight();

    expect($weight->complete)->toBeFalse()
        ->and($weight->pesoCopiaG)->toBeNull();

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('Grammatura interno 0,8 g/m² fuori dall&#039;intervallo ammesso (30–400 g/m²)', escape: false)
        ->assertDontSee('4,7 g')
        ->assertDontSee('Peso copia');
});

test('the creation form rejects a grammage typed as the weight of a page', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('title', 'Numero sbagliato')
        ->set('total_pages', 214)
        ->set('weight.grammatura_interno', '0.8')
        ->assertSee('Inserisci la grammatura della carta in g/m² (es. 80), non il peso di una pagina.')
        ->call('save')
        ->assertHasErrors(['weight.grammatura_interno' => 'min']);

    expect(Issue::where('title', 'Numero sbagliato')->exists())->toBeFalse();
});

test('the creation form shows the weight per page under each grammage', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();

    Livewire::actingAs(weightEditorFor($magazine))->test(IssueCreate::class, ['magazine' => $magazine])
        ->set('total_pages', 208)
        ->assertSee('80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g', escape: false)
        ->assertSee('170 g/m² → 4,820 g a pagina · 6 pagine = 28,9 g', escape: false);
});

test('the magazine settings reject out-of-range grammages and preview the defaults', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    createWeightIssue($magazine, weightEditorFor($magazine), totalPages: 208);

    Livewire::actingAs(weightAdmin())->test(MagazineEdit::class, ['magazine' => $magazine])
        // Pagine interne di prova proposte dall'ultimo numero.
        ->assertSet('previewTotalPages', '208')
        ->assertSee('80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g', escape: false)
        ->assertSee('510,7 g')
        ->set('weight.grammatura_copertina', '1,7')
        ->call('save')
        ->assertHasErrors(['weight.grammatura_copertina' => 'min']);

    expect((float) $magazine->fresh()->grammatura_copertina)->toBe(170.0);
});

test('a format typed in centimetres is rejected with the unit message', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->call('openEditor')
        ->set('params.formato_base_mm', '21')
        ->set('params.formato_altezza_mm', '2700')
        ->call('saveParams')
        ->assertHasErrors(['params.formato_base_mm' => 'min', 'params.formato_altezza_mm' => 'max'])
        ->assertSee('Inserisci il formato in millimetri (es. 210 × 270), non in centimetri.');

    expect($issue->fresh()->formato_base_mm)->toBe(210);
});

test('a format in centimetres already in the database shows no weight, only the error', function () {
    $magazine = Magazine::factory()->withWeightDefaults()->create();
    $user = weightEditorFor($magazine);
    $issue = createWeightIssue($magazine, $user);
    // Stesso dato sporco trovato sul numero 3 del DB locale il 2026-10-26.
    $issue->update(['formato_base_mm' => 21, 'formato_altezza_mm' => 27]);

    expect($issue->fresh()->weight()->complete)->toBeFalse();

    Livewire::actingAs($user)->test(WeightPanel::class, ['issue' => $issue])
        ->assertSee('Formato base 21 mm fuori dall&#039;intervallo ammesso (50–1000 mm)', escape: false)
        ->assertDontSee('5,1 g')
        ->assertDontSee('Peso copia');
});
