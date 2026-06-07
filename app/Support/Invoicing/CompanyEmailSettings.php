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
                'subject' => '#MY_COMPANY# - Faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe tohto mailu vám posielame faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou dňa #DUE_DATE#.\n\nPlatbu prosím poukážte na číslo účtu #ACCOUNT# a uveďte variabilný symbol VS: #VARIABLE_SYMBOL#\n\nĎakujeme\n\nS pozdravom,",
            ],
            'settlement_invoice' => [
                'subject' => '#MY_COMPANY# - Vyúčtovacia faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame vyúčtovaciu faktúru číslo #NUMBER# na sumu #AMOUNT#.\n\nS pozdravom,",
            ],
            'invoice_from_proforma' => [
                'subject' => '#MY_COMPANY# - Faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe tohto mailu vám posielame ostrú faktúru číslo #NUMBER# k zálohovej faktúre #PROFORMA_NUMBER#.\n\nĎakujeme\n\nS pozdravom,",
            ],
            'credit_note' => [
                'subject' => '#MY_COMPANY# - Dobropis #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame dobropis číslo #NUMBER#.\n\nS pozdravom,",
            ],
            'proforma' => [
                'subject' => '#MY_COMPANY# - Zálohová faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame zálohovú faktúru číslo #NUMBER# na sumu #AMOUNT#.\n\nS pozdravom,",
            ],
            'quote' => [
                'subject' => '#MY_COMPANY# - Cenová ponuka #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame cenovú ponuku číslo #NUMBER# platnú do #VALID_UNTIL#.\n\nS pozdravom,",
            ],
            'delivery_note' => [
                'subject' => '#MY_COMPANY# - Dodací list #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame dodací list číslo #NUMBER#.\n\nS pozdravom,",
            ],
            'order_received' => [
                'subject' => '#MY_COMPANY# - Prijatá objednávka #NUMBER#',
                'body' => "Dobrý deň,\n\npotvrdzujeme prijatie objednávky číslo #NUMBER#.\n\nS pozdravom,",
            ],
            'order_issued' => [
                'subject' => '#MY_COMPANY# - Vydaná objednávka #NUMBER#',
                'body' => "Dobrý deň,\n\nv prílohe zasielame objednávku číslo #NUMBER#.\n\nS pozdravom,",
            ],
            'reminder_sms' => [
                'subject' => '',
                'body' => 'Pripomienka: faktúra #NUMBER# na sumu #AMOUNT#, splatnosť #DUE_DATE#. #MY_COMPANY#',
            ],
            'reminder_email' => [
                'subject' => 'Pripomienka - Faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\npripomíname neuhradenú faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou #DUE_DATE#.\n\nS pozdravom,",
            ],
            'dunning_sms' => [
                'subject' => '',
                'body' => 'Upomienka: faktúra #NUMBER# na sumu #AMOUNT#, splatnosť #DUE_DATE#. #MY_COMPANY#',
            ],
            'dunning_email' => [
                'subject' => 'Upomienka - Faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nv našom systéme evidujeme neuhradenú faktúru číslo #NUMBER# na sumu #AMOUNT# so splatnosťou dňa #DUE_DATE#.\n\nPlatbu prosím poukážte na číslo účtu #ACCOUNT# a uveďte variabilný symbol VS: #VARIABLE_SYMBOL#\n\nĎakujeme\n\nS pozdravom,",
            ],
            'thank_you' => [
                'subject' => 'Poďakovanie - Faktúra #NUMBER#',
                'body' => "Dobrý deň,\n\nďakujeme za úhradu faktúry číslo #NUMBER#.\n\nS pozdravom,",
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
