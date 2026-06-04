<?php

namespace App\Support\Invoicing;

/**
 * Per-company email delivery and document email/SMS templates.
 */
final class CompanyEmailSettings
{
    public const DELIVERY_SYSTEM = 'system';

    public const DELIVERY_SMTP = 'smtp';

    public const DELIVERY_GMAIL = 'gmail';

    public const DELIVERY_OFFICE = 'office';

    /** @var list<string> */
    public const TEMPLATE_KEYS = [
        'invoice',
        'settlement_invoice',
        'invoice_from_proforma',
        'credit_note',
        'proforma',
        'quote',
        'delivery_note',
        'order_received',
        'order_issued',
        'reminder_sms',
        'reminder_email',
        'dunning_sms',
        'dunning_email',
        'thank_you',
    ];

    public const DEFAULTS = [
        'delivery_method' => self::DELIVERY_SYSTEM,
        'smtp' => [
            'username' => null,
            'password_encrypted' => null,
            'host' => null,
            'port' => null,
            'from_name' => null,
            'encryption' => 'tls',
            'use_smtp_email_as_from' => true,
        ],
        'templates' => [],
    ];

    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values = []) {}

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function from(?array $stored): self
    {
        $stored = $stored ?? [];
        $templates = array_merge(self::defaultTemplates(), is_array($stored['templates'] ?? null) ? $stored['templates'] : []);
        $smtp = array_merge(self::DEFAULTS['smtp'], is_array($stored['smtp'] ?? null) ? $stored['smtp'] : []);

        return new self([
            'delivery_method' => $stored['delivery_method'] ?? self::DEFAULTS['delivery_method'],
            'smtp' => $smtp,
            'templates' => $templates,
        ]);
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    public static function defaultTemplates(): array
    {
        return [
            'invoice' => [
                'subject' => '#MOJA_FIRMA# - Faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe tohto mailu vám posielame faktúru číslo #CISLO# na sumu #SUMA# so splatnosťou dňa #SPLATNOST#.\n\nPlatbu prosím poukážte na číslo účtu #UCET# a uveďte variabilný symbol VS: #VAR#\n\nĎakujeme\n\nS pozdravom,",
            ],
            'settlement_invoice' => [
                'subject' => '#MOJA_FIRMA# - Vyúčtovacia faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame vyúčtovaciu faktúru číslo #CISLO# na sumu #SUMA#.\n\nS pozdravom,",
            ],
            'invoice_from_proforma' => [
                'subject' => '#MOJA_FIRMA# - Faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe tohto mailu vám posielame ostrú faktúru číslo #CISLO# k zálohovej faktúre #CISLO_ZAL#.\n\nĎakujeme\n\nS pozdravom,",
            ],
            'credit_note' => [
                'subject' => '#MOJA_FIRMA# - Dobropis #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame dobropis číslo #CISLO#.\n\nS pozdravom,",
            ],
            'proforma' => [
                'subject' => '#MOJA_FIRMA# - Zálohová faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame zálohovú faktúru číslo #CISLO# na sumu #SUMA#.\n\nS pozdravom,",
            ],
            'quote' => [
                'subject' => '#MOJA_FIRMA# - Cenová ponuka #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame cenovú ponuku číslo #CISLO# platnú do #PLATI_DO#.\n\nS pozdravom,",
            ],
            'delivery_note' => [
                'subject' => '#MOJA_FIRMA# - Dodací list #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame dodací list číslo #CISLO#.\n\nS pozdravom,",
            ],
            'order_received' => [
                'subject' => '#MOJA_FIRMA# - Prijatá objednávka #CISLO#',
                'body' => "Dobrý deň,\n\npotvrdzujeme prijatie objednávky číslo #CISLO#.\n\nS pozdravom,",
            ],
            'order_issued' => [
                'subject' => '#MOJA_FIRMA# - Vydaná objednávka #CISLO#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame objednávku číslo #CISLO#.\n\nS pozdravom,",
            ],
            'reminder_sms' => [
                'subject' => '',
                'body' => 'Pripomienka: faktúra #CISLO# na sumu #SUMA#, splatnosť #SPLATNOST#. #MOJA_FIRMA#',
            ],
            'reminder_email' => [
                'subject' => 'Pripomienka - Faktúra #CISLO#',
                'body' => "Dobrý deň,\n\npripomíname neuhradenú faktúru číslo #CISLO# na sumu #SUMA# so splatnosťou #SPLATNOST#.\n\nS pozdravom,",
            ],
            'dunning_sms' => [
                'subject' => '',
                'body' => 'Upomienka: faktúra #CISLO# na sumu #SUMA#, splatnosť #SPLATNOST#. #MOJA_FIRMA#',
            ],
            'dunning_email' => [
                'subject' => 'Upomienka - Faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nv našom systéme evidujeme neuhradenú faktúru číslo #CISLO# na sumu #SUMA# so splatnosťou dňa #SPLATNOST#.\n\nPlatbu prosím poukážte na číslo účtu #UCET# a uveďte variabilný symbol VS: #VAR#\n\nĎakujeme\n\nS pozdravom,",
            ],
            'thank_you' => [
                'subject' => 'Poďakovanie - Faktúra #CISLO#',
                'body' => "Dobrý deň,\n\nďakujeme za úhradu faktúry číslo #CISLO#.\n\nS pozdravom,",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}
