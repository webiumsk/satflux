<?php

namespace Tests\Feature;

use App\Enums\RegWatchChangeStatus;
use App\Enums\RegWatchSourceType;
use App\Enums\RegWatchTopic;
use App\Models\RegWatchChange;
use App\Models\RegWatchJurisdiction;
use App\Models\RegWatchRule;
use App\Models\RegWatchSource;
use Database\Seeders\RegWatchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegWatchSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seed_regwatch(): void
    {
        $this->seed(RegWatchSeeder::class);
    }

    #[Test]
    public function seeder_creates_jurisdictions_sources_and_placeholder_rules(): void
    {
        $this->seed_regwatch();

        $this->assertSame(8, RegWatchJurisdiction::count());
        $this->assertSame(4, RegWatchSource::count());
        $this->assertSame(12, RegWatchRule::count());

        $codes = RegWatchJurisdiction::pluck('code')->sort()->values()->all();
        $this->assertSame(['AT', 'CH', 'CZ', 'DE', 'HU', 'PL', 'SK', 'US-WY'], $codes);

        // Sources exist only for SK and CZ in phase 1, with official URLs.
        $slugs = RegWatchSource::pluck('slug')->sort()->values()->all();
        $this->assertSame(
            ['cz-e-sbirka', 'cz-financni-sprava', 'sk-financna-sprava', 'sk-slov-lex'],
            $slugs,
        );
    }

    #[Test]
    public function all_seeded_rules_are_unverified_placeholders(): void
    {
        $this->seed_regwatch();

        // The critical LEGAL.md rule: no concrete rates/thresholds may be
        // seeded - every rule is a TODO placeholder pending human review.
        foreach (RegWatchRule::all() as $rule) {
            $this->assertNull($rule->verified_on, "rule {$rule->slug} must not be verified");
            $this->assertSame('TODO: overiť z oficiálneho zdroja', $rule->rule_text);
            $this->assertNotNull($rule->source_id);
            $this->assertStringStartsWith('https://', $rule->source_url);
        }

        // SK and CZ each carry the full phase-1 topic set.
        $skTopics = RegWatchRule::whereHas('jurisdiction', fn ($q) => $q->where('code', 'SK'))
            ->pluck('topic')->map(fn (RegWatchTopic $t) => $t->value)->sort()->values()->all();
        $this->assertSame(
            ['archiving', 'income_tax', 'oss', 'reverse_charge', 'us_llc_income', 'vat_registration'],
            $skTopics,
        );
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seed_regwatch();
        $this->seed_regwatch();

        $this->assertSame(8, RegWatchJurisdiction::count());
        $this->assertSame(4, RegWatchSource::count());
        $this->assertSame(12, RegWatchRule::count());
    }

    #[Test]
    public function relations_and_enum_casts_work(): void
    {
        $this->seed_regwatch();

        $sk = RegWatchJurisdiction::where('code', 'SK')->firstOrFail();
        $this->assertCount(2, $sk->sources);
        $this->assertCount(6, $sk->rules);

        $source = RegWatchSource::where('slug', 'sk-financna-sprava')->firstOrFail();
        $this->assertSame(RegWatchSourceType::TaxAuthority, $source->type);
        $this->assertSame('SK', $source->jurisdiction->code);
        $this->assertTrue($source->rules->isNotEmpty());

        $rule = RegWatchRule::where('slug', 'sk-vat-registration')->firstOrFail();
        $this->assertSame(RegWatchTopic::VatRegistration, $rule->topic);

        // The changelog rows the monitoring cron will insert (status 'new').
        $change = RegWatchChange::create([
            'source_id' => $source->id,
            'rule_id' => $rule->id,
            'summary' => 'test detection',
            'detected_at' => now(),
        ]);
        $this->assertSame(RegWatchChangeStatus::New, $change->refresh()->status);
        $this->assertSame($rule->id, $change->rule->id);
        $this->assertSame($source->id, $change->source->id);
    }
}
