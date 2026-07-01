<?php

namespace App\Services\Invoicing;

use App\Models\Company;
use App\Support\Invoicing\CompanyEmailSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CompanyEmailSettingsService
{
    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeAndPersist(Company $company, array $incoming): array
    {
        $merged = $this->mergeIncomingSettings(
            CompanyEmailSettings::from($company->email_settings)->toArray(),
            $incoming,
        );

        $company->update(['email_settings' => $merged]);

        return $this->publicPayload($company->fresh());
    }

    /**
     * Apply email settings from an ephemeral snapshot onto an in-memory company (no DB write).
     *
     * @param  array<string, mixed>  $incoming
     */
    public function applyIncomingToCompany(Company $company, array $incoming): void
    {
        $company->email_settings = $this->mergeIncomingSettings(
            CompanyEmailSettings::from($company->email_settings)->toArray(),
            $incoming,
        );
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeIncomingSettings(array $current, array $incoming): array
    {
        $merged = $current;

        if (isset($incoming['delivery_method'])) {
            $merged['delivery_method'] = $incoming['delivery_method'];
        }

        if (isset($incoming['smtp']) && is_array($incoming['smtp'])) {
            $smtp = is_array($merged['smtp'] ?? null) ? $merged['smtp'] : CompanyEmailSettings::DEFAULTS['smtp'];
            foreach (['username', 'host', 'port', 'from_name', 'encryption', 'use_smtp_email_as_from'] as $field) {
                if (array_key_exists($field, $incoming['smtp'])) {
                    $smtp[$field] = $incoming['smtp'][$field];
                }
            }
            if (! empty($incoming['smtp']['password'])) {
                $smtp['password_encrypted'] = Crypt::encryptString((string) $incoming['smtp']['password']);
            }
            unset($smtp['password'], $smtp['password_set']);
            $merged['smtp'] = $smtp;
        }

        if (isset($incoming['templates']) && is_array($incoming['templates'])) {
            $templates = is_array($merged['templates'] ?? null) ? $merged['templates'] : [];
            foreach ($incoming['templates'] as $key => $tpl) {
                if (! in_array($key, CompanyEmailSettings::TEMPLATE_KEYS, true) || ! is_array($tpl)) {
                    continue;
                }
                $templates[$key] = [
                    'subject' => (string) ($tpl['subject'] ?? ''),
                    'body' => (string) ($tpl['body'] ?? ''),
                ];
            }
            $merged['templates'] = $templates;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(Company $company): array
    {
        $settings = CompanyEmailSettings::from($company->email_settings);
        $data = $settings->toArray();
        $smtp = $data['smtp'];
        $smtp['password_set'] = ! empty($smtp['password_encrypted'] ?? null);
        unset($smtp['password_encrypted']);
        $data['smtp'] = $smtp;

        return $data;
    }

    public function resolved(Company $company): CompanyEmailSettings
    {
        return CompanyEmailSettings::from($company->email_settings);
    }

    /**
     * @throws TransportExceptionInterface
     */
    /**
     * Registers a dynamic mailer for company SMTP; returns mailer name or null for system default.
     */
    public function registerCompanyMailer(Company $company): ?string
    {
        $settings = $this->resolved($company);
        if ($settings->get('delivery_method') !== CompanyEmailSettings::DELIVERY_SMTP) {
            return null;
        }

        $mailerName = 'company_smtp_'.$company->id;
        config(['mail.mailers.'.$mailerName => $this->smtpMailerConfig($settings)]);

        return $mailerName;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    public function resolveFromAddress(Company $company): array
    {
        $settings = $this->resolved($company);
        if ($settings->get('delivery_method') === CompanyEmailSettings::DELIVERY_SMTP) {
            $smtp = $settings->get('smtp');
            $useLogin = (bool) ($smtp['use_smtp_email_as_from'] ?? true);
            $address = $useLogin ? ($smtp['username'] ?? null) : config('mail.from.address');
            $name = $smtp['from_name'] ?? $company->displayName();

            return [$address, $name];
        }

        return [config('mail.from.address'), $company->displayName() ?: config('mail.from.name')];
    }

    public function sendSmtpTest(Company $company, string $recipientEmail): void
    {
        $settings = $this->resolved($company);
        if ($settings->get('delivery_method') !== CompanyEmailSettings::DELIVERY_SMTP) {
            throw new \InvalidArgumentException('SMTP delivery is not selected.');
        }

        $mailerName = $this->registerCompanyMailer($company) ?? 'smtp';
        [$fromAddress, $fromName] = $this->resolveFromAddress($company);

        Mail::mailer($mailerName)->raw(
            __('Test email from invoicing settings.'),
            function ($message) use ($recipientEmail, $fromAddress, $fromName) {
                $message->to($recipientEmail);
                if ($fromAddress) {
                    $message->from($fromAddress, $fromName ?? '');
                }
                $message->subject('SMTP test - satflux invoicing');
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function smtpMailerConfig(CompanyEmailSettings $settings): array
    {
        $smtp = $settings->get('smtp');
        $password = null;
        if (! empty($smtp['password_encrypted'])) {
            $password = Crypt::decryptString($smtp['password_encrypted']);
        }

        $encryption = $smtp['encryption'] ?? 'tls';
        if ($encryption === 'none') {
            $encryption = null;
        }

        return [
            'transport' => 'smtp',
            'host' => $smtp['host'] ?? 'localhost',
            'port' => (int) ($smtp['port'] ?? 587),
            'encryption' => $encryption,
            'username' => $smtp['username'] ?? null,
            'password' => $password,
            'timeout' => 15,
        ];
    }
}
