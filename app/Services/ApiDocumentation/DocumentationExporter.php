<?php

namespace App\Services\ApiDocumentation;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Router;
use RuntimeException;

final class DocumentationExporter
{
    /** @return array<string, string> */
    public function artifacts(): array
    {
        if (! class_exists(Generator::class)) {
            throw new RuntimeException('API export requires development dependencies.');
        }
        $result = app(Generator::class)->generate(Scramble::getGeneratorConfig('default'));
        if ($result->diagnostics()->isNotEmpty()) {
            throw new RuntimeException('Scramble inference emitted diagnostics; inspect a disposable scramble:export trial before updating contracts.');
        }
        $inferred = $result->spec();
        $documents = (new DocumentationSchemas)->documents($inferred);
        $coverage = [];
        $seen = [];
        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $key = $method.' '.trim($route->uri(), '/');
                $document = DocumentationRoutes::selected()[$key] ?? null;
                $entry = [
                    'route' => $key,
                    'action' => $route->getActionName(),
                    'middleware' => $route->gatherMiddleware(),
                    'status' => $document === null ? 'excluded' : 'documented',
                    'reason' => $document === null ? 'Outside the initial reviewed account/store/raffle, WooCommerce and inbound-webhook scope; expand with reviewed schemas.' : 'Reviewed Scramble inference with explicit auth/response overlays.',
                ];
                if ($document !== null) {
                    $entry['document'] = $document;
                    $seen[] = $key;
                }
                $coverage[$key] = $entry;
            }
        }
        if (array_diff(array_keys(DocumentationRoutes::selected()), $seen)) {
            throw new RuntimeException('A documented route no longer exists.');
        }
        ksort($coverage);
        $sources = [];
        foreach ([
            'app/Http/Controllers/AccountController.php',
            'app/Http/Controllers/StoreController.php',
            'app/Http/Controllers/RaffleController.php',
            'app/Http/Controllers/WebhookController.php',
            'app/Http/Controllers/Invoicing/BankInboundWebhookController.php',
            'app/Http/Controllers/Integrations/WooCommerceIntegrationController.php',
            'app/Http/Controllers/Integrations/WooCommerceConnectController.php',
            'app/Services/Integrations/WooCommerceDocumentService.php',
            'app/Services/Integrations/IntegrationDocumentInboxService.php',
            'app/Services/Invoicing/BankInboundWebhookPayloadNormalizer.php',
            'app/Services/Invoicing/BankEmailOriginVerifier.php',
            'app/Services/Invoicing/BankMimeVerificationService.php',
            'app/Services/Invoicing/BankMimeCaptureService.php',
            'app/Support/Invoicing/BankMimeDiagnosticRunner.php',
            'app/Services/Invoicing/BankInboundEmailService.php',
            'app/Services/Invoicing/BankImport/TatraBankEmailParser.php',
            'app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php',
            'app/Services/SepaEmailConfirmationService.php',
            'app/Support/Invoicing/ParsedBankTransaction.php',
            'app/Support/Invoicing/BankAccountIdentifier.php',
            'app/Jobs/ProcessBankNotificationEmail.php',
            'bootstrap/app.php',
            'config/bank_inbound.php',
            'config/bank_mail_capture.php',
            'runtime/bank-mail-verifier/cli.mjs',
            'runtime/bank-mail-verifier/diagnostic-cli.mjs',
            'runtime/bank-mail-verifier/verifier.mjs',
            'runtime/bank-mail-verifier/package.json',
            'runtime/bank-mail-verifier/package-lock.json',
            'app/Services/Raffle/RafflePayloadService.php',
            'app/Http/Middleware/AuthenticateWooCommerceIntegration.php',
            'app/Http/Middleware/EnsureStoreOwnership.php',
            'app/Services/StoreResponseFormatter.php',
            'app/Models/CompanyContact.php',
            'app/Models/BusinessDocument.php',
            'app/Models/User.php',
            'app/Http/Middleware/RequireVerifiedEmail.php',
        ] as $source) {
            $sources[$source] = hash_file('sha256', base_path($source));
        }
        $documents['route-coverage'] = [
            'generator' => 'dedoc/scramble 0.13.49 + reviewed DocumentationSchemas overlays',
            'scope' => 'Runtime api/* routes, excluding automatically added HEAD; framework/vendor/web routes are outside this inventory.',
            'source_hashes' => $sources,
            'routes' => array_values($coverage),
        ];
        $artifacts = [];
        foreach ($documents as $id => $document) {
            $artifacts[$id.'.json'] = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        }

        return $artifacts;
    }
}
