# Registered IBAN lengths

`iban-lengths.json` contains the 89 registered country-code/IBAN-length pairs from
the official SWIFT IBAN Registry, Release 103 (September 2026). Source: [SWIFT IBAN
Registry](https://www.swift.com/sites/default/files/files/iban-registry_4.pdf),
linked from the [SWIFT IBAN standard page](https://www.swift.com/standards/data-standards/iban-international-bank-account-number).

The browser supplier-payment validator and PHP expense-import validator both use
this same local snapshot. They reject unknown countries and incorrect lengths
before modulo 97 checksum validation. They normalize printed ASCII spaces and case;
control characters are rejected. A successful check validates the account format,
not account existence, ownership or payment settlement.

Update the country lengths only against a new official registry release and keep
PHP/browser regressions together. No remote lookup is performed during import or
QR generation.
