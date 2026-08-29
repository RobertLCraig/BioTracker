<?php

namespace Tests\Feature;

use App\Enums\AbnormalFlag;
use App\Models\LabPanel;
use App\Models\LabResult;
use App\Models\LabTestDefinition;
use App\Models\User;
use App\Services\Lab\PkbTestImportService;
use App\Services\Reports\ReportExportService;
use Database\Seeders\LabTestDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LabResultImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LabTestDefinitionSeeder::class);
    }

    /** One PKB payload with three lipid analytes sharing one order. */
    private function pkbPayload(): array
    {
        $point = fn (int $id, float $val, ?float $low, ?float $high, string $unit, ?string $comment = null) => [
            'id' => $id,
            'labOrderId' => 'ORDER1',
            'labType' => 'BLS',
            'date' => ['value' => 1784289600000],
            'enteredDate' => ['value' => 1784308140000],
            'deleted' => false,
            'replaceDate' => ['value' => null],
            'privacyFlags' => ['generalHealth' => true],
            'source' => ['displayText' => 'UHS Sussex (East)', 'via' => 'Via Integration (HL7)', 'sourceType' => 'ORG'],
            'comment' => ['commented' => $comment !== null, 'comments' => $comment],
            'range' => $low === null && $high === null
                ? ['rangeProvided' => false, 'low' => null, 'high' => null, 'textRange' => null, 'display' => null]
                : ['rangeProvided' => true, 'low' => $low, 'high' => $high, 'textRange' => null, 'display' => "$low - $high $unit"],
            'value' => ['comparator' => null, 'display' => trim("$val $unit"), 'rawNumericValue' => $val, 'localizedValue' => (string) $val],
        ];

        $meta = fn (string $name, int $typeId, string $unit, array $points) => [
            'name' => $name,
            'testResultType' => 'loincMapping',
            'testResultTypeId' => $typeId,
            'testResultTypeName' => $name,
            'unit' => $unit,
            'textualResultsOnly' => false,
            'dataPoints' => $points,
        ];

        return [
            'count' => 3,
            'tests' => [[
                'formCode' => null,
                'formName' => null,
                'testHistoryMetadata' => [
                    $meta('Serum cholesterol', 943400139, 'mmol/L', [$point(1001, 4.9, 0.0, 5.0, 'mmol/L', 'Risks increase with cholesterol above 4 mmol/L<br>and/or LDL above 2 mmol/L')]),
                    $meta('Serum HDL cholesterol', 943400140, 'mmol/L', [$point(1002, 0.8, 1.0, 3.0, 'mmol/L')]),
                    $meta('Serum chol:HDL ratio', 943400141, '', [$point(1003, 6.1, null, null, '')]),
                ],
            ]],
        ];
    }

    public function test_pkb_import_maps_fields_derives_flags_and_infers_panel(): void
    {
        $user = User::factory()->create();
        $result = app(PkbTestImportService::class)->import($user, $this->pkbPayload());

        $this->assertSame(1, $result['panels']);
        $this->assertSame(3, $result['results_created']);
        $this->assertSame(0, $result['results_updated']);

        // Panel inferred from labOrderId, grouped once.
        $panel = LabPanel::withoutGlobalScopes()->where('user_id', $user->id)->sole();
        $this->assertSame('ORDER1', $panel->lab_order_id);
        $this->assertSame('pkb_json', $panel->source);
        $this->assertSame('BLS', $panel->lab_type);

        // HDL 0.8 with range 1-3 → derived low (this is PKB's UI "out of range").
        $hdl = LabResult::withoutGlobalScopes()->where('test_name', 'Serum HDL cholesterol')->sole();
        $this->assertSame(AbnormalFlag::Low, $hdl->abnormal_flag);
        $this->assertSame($panel->id, $hdl->lab_panel_id);

        // Cholesterol 4.9 in 0-5 → normal; matched the seeded (curated) definition by PKB id.
        $chol = LabResult::withoutGlobalScopes()->where('test_name', 'Serum cholesterol')->sole();
        $this->assertSame(AbnormalFlag::Normal, $chol->abnormal_flag);
        $this->assertSame(
            LabTestDefinition::where('pkb_type_id', '943400139')->value('id'),
            $chol->test_definition_id,
        );
        $this->assertTrue($chol->testDefinition->is_curated);

        // Ratio has no range → unknown.
        $ratio = LabResult::withoutGlobalScopes()->where('test_name', 'Serum chol:HDL ratio')->sole();
        $this->assertSame(AbnormalFlag::Unknown, $ratio->abnormal_flag);

        // Comment encrypted at rest, decrypts via accessor.
        $this->assertStringContainsString('Risks increase', $chol->comment);
        $this->assertStringNotContainsString('Risks increase', $chol->getRawOriginal('comment'));
    }

    public function test_reimport_is_idempotent_on_external_id(): void
    {
        $user = User::factory()->create();
        $importer = app(PkbTestImportService::class);

        $importer->import($user, $this->pkbPayload());
        $second = $importer->import($user, $this->pkbPayload());

        $this->assertSame(0, $second['results_created']);
        $this->assertSame(3, $second['results_updated']);
        $this->assertSame(3, LabResult::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertSame(1, LabPanel::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_import_endpoint_accepts_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/lab-results/import', ['payload' => $this->pkbPayload()])
            ->assertOk()
            ->assertJson(['queued' => false, 'results_created' => 3, 'panels' => 1]);

        $this->assertSame(3, LabResult::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_trends_lists_series_and_returns_a_numeric_series(): void
    {
        $user = User::factory()->create();
        app(PkbTestImportService::class)->import($user, $this->pkbPayload());

        // No test_key → available series picker.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/lab-results/trends')
            ->assertOk()
            ->assertJsonPath('data.series.0.name', fn ($name) => is_string($name));

        // With test_key → the numeric series for that analyte.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/lab-results/trends?test_key=serum-hdl-cholesterol')
            ->assertOk()
            ->assertJsonPath('data.name', 'Serum HDL cholesterol')
            ->assertJsonPath('data.points.0.value', 0.8)
            ->assertJsonPath('data.points.0.flag', 'low');
    }

    public function test_paste_parser_previews_without_persisting(): void
    {
        $user = User::factory()->create();

        $paste = "Serum HDL cholesterol 17 Jul 2025 0.8\nmmol/L out of range\n"
               . "Hb 17 Jul 2025 154\ng/L in range";

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/lab-results/parse', ['text' => $paste])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.test_name', 'Serum HDL cholesterol')
            ->assertJsonPath('data.0.value_numeric', 0.8)
            ->assertJsonPath('data.0.unit', 'mmol/L')
            ->assertJsonPath('data.0.abnormal_flag', 'abnormal')
            ->assertJsonPath('data.1.abnormal_flag', 'normal');

        // Preview only — nothing written.
        $this->assertSame(0, LabResult::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_report_includes_lab_section(): void
    {
        $user = User::factory()->create();
        app(PkbTestImportService::class)->import($user, $this->pkbPayload());

        $service = app(ReportExportService::class);
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-31');

        $csv = $service->generateCsv($user, $from, $to, ['labs']);
        $this->assertStringContainsString('--- Lab / Test Results ---', $csv);
        $this->assertStringContainsString('Serum HDL cholesterol', $csv);

        // Blade renders without error and produces a real PDF.
        $pdf = $service->generatePdf($user, $from, $to, ['labs']);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * The SPA's Labs view groups the flat result list by panel and renders the
     * value/range/flag from these exact fields, so pin them.
     */
    public function test_index_carries_the_fields_the_labs_view_groups_and_renders_on(): void
    {
        $user = User::factory()->create();
        app(PkbTestImportService::class)->import($user, $this->pkbPayload());

        // A standalone manual result: no lab_order_id, so no panel (see LabResultImporter).
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/lab-results', [
            'test_name' => 'Serum ferritin',
            'value_text' => '30',
            'value_numeric' => 30,
            'unit' => 'ug/L',
            'sampled_at' => '2026-08-01T09:00:00Z',
        ])->assertCreated();

        $rows = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/lab-results')
            ->assertOk()
            ->json('data');

        // Newest first, and the panel-less row is distinguishable from panelled ones.
        $this->assertSame('Serum ferritin', $rows[0]['test_name']);
        $this->assertNull($rows[0]['lab_panel_id']);
        $this->assertNotNull($rows[1]['lab_panel_id']);

        $hdl = collect($rows)->firstWhere('test_name', 'Serum HDL cholesterol');
        $this->assertSame('low', $hdl['abnormal_flag']);
        $this->assertSame('mmol/L', $hdl['value']['unit']);
        // decimal:4 casts, which the view trims back to 0.8 / 1 – 3.
        $this->assertSame(0.8, (float) $hdl['value']['numeric']);
        $this->assertSame(1.0, (float) $hdl['range']['low']);
        $this->assertSame(3.0, (float) $hdl['range']['high']);

        // Panels supply the group heading and its date.
        $panel = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/lab-panels')
            ->assertOk()
            ->json('data.0');

        $this->assertSame($hdl['lab_panel_id'], $panel['id']);
        $this->assertSame('ORDER1', $panel['lab_order_id']);
        $this->assertNotNull($panel['collected_at']);
    }

    public function test_manual_store_resolves_definition(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/lab-results', [
                'test_name' => 'Serum cholesterol',
                'value_text' => '5.2',
                'value_numeric' => 5.2,
                'unit' => 'mmol/L',
                'range_low' => 0,
                'range_high' => 5,
                'sampled_at' => '2026-07-17T09:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('value.text', '5.2')
            ->assertJsonPath('abnormal_flag', 'high');

        $result = LabResult::withoutGlobalScopes()->where('user_id', $user->id)->sole();
        $this->assertNotNull($result->test_definition_id);   // definition resolved
        $this->assertNull($result->lab_panel_id);            // standalone manual result → no panel
        $this->assertSame('serum-cholesterol', $result->test_key);
    }

    /** A name that matches no slug but does match an alias resolves, case and spacing aside. */
    public function test_alias_resolves_without_creating_a_second_definition(): void
    {
        $before = LabTestDefinition::count();
        $hb = LabTestDefinition::where('slug', 'hb')->sole();

        foreach (['Haemoglobin estimation', '  haemoglobin   ESTIMATION  '] as $name) {
            $this->assertSame($hb->id, LabTestDefinition::resolveForImport(null, null, $name)->id);
        }

        $this->assertSame($before, LabTestDefinition::count());
    }

    /** An alias match backfills the PKB id the same way a slug match does. */
    public function test_alias_match_backfills_the_pkb_type_id(): void
    {
        $creatinine = LabTestDefinition::where('slug', 'serum-creatinine')->sole();
        $this->assertNull($creatinine->pkb_type_id);

        $resolved = LabTestDefinition::resolveForImport('943400999', 'loincMapping', 'Creatinine', 'umol/L');

        $this->assertSame($creatinine->id, $resolved->id);
        $this->assertSame('943400999', $resolved->fresh()->pkb_type_id);
        $this->assertSame('loincMapping', $resolved->fresh()->code_system);
    }

    /** No id, no slug, no alias → still recorded, as an uncurated definition. */
    public function test_unmatched_analyte_still_creates_an_uncurated_definition(): void
    {
        $before = LabTestDefinition::count();

        $created = LabTestDefinition::resolveForImport(null, null, 'Serum unobtainium', 'nmol/L');

        $this->assertSame($before + 1, LabTestDefinition::count());
        $this->assertFalse($created->is_curated);
        $this->assertSame('serum-unobtainium', $created->slug);
    }

    /** AC#5: a manual entry named by alias trends as one series with the PKB import. */
    public function test_manual_entry_named_by_alias_shares_the_import_test_key(): void
    {
        $user = User::factory()->create();
        app(PkbTestImportService::class)->import($user, $this->pkbPayload());

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/lab-results', [
            'test_name' => 'Cholesterol',            // an alias of "Serum cholesterol"
            'value_text' => '5.2',
            'value_numeric' => 5.2,
            'unit' => 'mmol/L',
            'sampled_at' => '2026-08-01T09:00:00Z',
        ])->assertCreated()->assertJsonPath('test_key', 'serum-cholesterol');

        $keys = LabResult::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereIn('test_name', ['Cholesterol', 'Serum cholesterol'])
            ->pluck('test_key');

        $this->assertCount(2, $keys);
        $this->assertSame(['serum-cholesterol'], $keys->unique()->values()->all());
    }

    /** Two definitions claiming one alias would make resolution order-dependent. */
    public function test_seeded_aliases_are_unique_across_the_catalog(): void
    {
        $slugs = LabTestDefinition::all()
            ->flatMap(fn (LabTestDefinition $d) => collect($d->aliases ?? [])
                ->map(fn ($alias) => \Illuminate\Support\Str::slug($alias))
                ->push($d->slug))
            ->all();

        $this->assertSame(array_unique($slugs), $slugs, 'A seeded alias collides with another alias or slug.');
    }
}
