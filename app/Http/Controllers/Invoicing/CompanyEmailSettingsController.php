<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\TestCompanyEmailSmtpRequest;
use App\Http\Requests\Invoicing\UpdateCompanyEmailSettingsRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Invoicing\CompanyBrandingService;
use App\Services\Invoicing\CompanyEmailSettingsService;
use App\Support\Invoicing\CompanyEmailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CompanyEmailSettingsController extends Controller
{
    public function __construct(
        protected CompanyEmailSettingsService $emailSettings,
        protected CompanyBrandingService $brandingService,
    ) {}

    public function update(UpdateCompanyEmailSettingsRequest $request, Company $company): JsonResponse
    {
        $method = $request->input('delivery_method');
        if (in_array($method, [CompanyEmailSettings::DELIVERY_GMAIL, CompanyEmailSettings::DELIVERY_OFFICE], true)) {
            return response()->json([
                'message' => 'Gmail and Office sending will be available in a future update. Use system or custom SMTP for now.',
            ], 422);
        }

        $emailPayload = $this->emailSettings->mergeAndPersist($company, $request->validated());
        $fresh = $company->fresh();

        AuditLog::log('company.email_settings_updated', 'company', $company->id);

        return response()->json([
            'data' => array_merge(
                $fresh->toArray(),
                [
                    'app_settings' => $fresh->resolvedAppSettings(),
                    'email_settings' => $emailPayload,
                ],
                $this->brandingService->brandingMeta($fresh),
            ),
        ]);
    }

    public function testSmtp(TestCompanyEmailSmtpRequest $request, Company $company): JsonResponse
    {
        try {
            $this->emailSettings->sendSmtpTest($company, $request->validated('to'));

            return response()->json(['message' => 'Test email sent.']);
        } catch (TransportExceptionInterface $e) {
            Log::warning('Company SMTP test failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'SMTP connection failed: '.$e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
