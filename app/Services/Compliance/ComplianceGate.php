<?php

namespace App\Services\Compliance;

use App\Models\ComplianceScreening;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComplianceGate
{
    public function __construct(
        protected GeoJurisdictionGuard $geoGuard,
        protected SanctionsScreeningProvider $sanctionsProvider,
    ) {}

    /**
     * @throws ValidationException
     */
    public function assertRegistrationAllowed(Request $request, string $email, ?string $name = null): void
    {
        if (! config('compliance.enabled')) {
            return;
        }

        $normalizedEmail = Str::lower(trim($email));
        $geo = $this->geoGuard->evaluate($request);

        $listResult = $this->screenLists($normalizedEmail, $name, $geo['country_code']);

        $decision = $this->resolveDecision($geo, $listResult);

        ComplianceScreening::create([
            'subject_type' => 'registration',
            'subject_email' => $normalizedEmail,
            'subject_name' => $name,
            'ip_address' => $request->ip(),
            'country_code' => $geo['country_code'],
            'geo_blocked' => $geo['geo_blocked'],
            'screening_provider' => $listResult->provider,
            'screening_status' => $listResult->status->value,
            'screening_reference' => $listResult->reference,
            'screening_payload_hash' => $listResult->payloadHash,
            'decision' => $decision->value,
            'decision_reason' => $geo['decision_reason'] ?? $listResult->decisionReason,
            'created_at' => now(),
        ]);

        if ($decision !== ComplianceDecision::Allowed) {
            throw ValidationException::withMessages([
                'email' => [__('messages.compliance_registration_unavailable')],
            ]);
        }
    }

    protected function screenLists(string $email, ?string $name, ?string $countryCode): ScreeningResult
    {
        if (! config('compliance.list_screening_enabled')) {
            return new ScreeningResult(
                status: ScreeningStatus::Skipped,
                provider: 'null',
            );
        }

        return $this->sanctionsProvider->screen(new ScreeningSubject(
            email: $email,
            name: $name,
            countryCode: $countryCode,
        ));
    }

    protected function resolveDecision(array $geo, ScreeningResult $listResult): ComplianceDecision
    {
        if (! $geo['allowed']) {
            return ComplianceDecision::Blocked;
        }

        return match ($listResult->status) {
            ScreeningStatus::Hit => ComplianceDecision::Blocked,
            ScreeningStatus::Error => (bool) config('compliance.fail_closed')
                ? ComplianceDecision::Blocked
                : ComplianceDecision::Allowed,
            ScreeningStatus::Clear, ScreeningStatus::Skipped => ComplianceDecision::Allowed,
        };
    }
}
