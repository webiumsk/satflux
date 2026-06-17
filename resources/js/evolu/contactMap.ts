import { booleanToSqliteBoolean } from "@evolu/common";
import type { CompanyContactRow, ContactFormState } from "@/composables/useCompanyContact";
import { normalizeIsoCountryCode } from "@/utils/isoCountryCode";
import type { ContactId, CompanyId } from "./schema";

export type EvoluContactRow = {
    id: ContactId;
    companyId: CompanyId;
    name: string;
    registrationNumber: string | null;
    peppolParticipantId: string | null;
    email: string | null;
    phone: string | null;
    fax: string | null;
    taxId: string | null;
    vatId: string | null;
    street: string | null;
    city: string | null;
    postalCode: string | null;
    stateRegion: string | null;
    country: string | null;
    bankAccount: string | null;
    bankCode: string | null;
    iban: string | null;
    swift: string | null;
    deliveryStreet: string | null;
    deliveryPostalCode: string | null;
    deliveryCity: string | null;
    deliveryCountry: string | null;
    defaultPaymentTermsDays: string | null;
    notes: string | null;
    contactPersonsJson: string | null;
    isActive: 0 | 1 | null;
};

function sqliteBoolToBoolean(value: 0 | 1 | null | undefined): boolean {
    return value !== 0;
}

function parseContactPersons(json: string | null): CompanyContactRow["contact_persons"] {
    if (!json?.trim()) return null;
    try {
        const parsed = JSON.parse(json);
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

export function evoluContactToApi(row: EvoluContactRow): CompanyContactRow {
    return {
        id: row.id,
        name: row.name,
        registration_number: row.registrationNumber,
        peppol_participant_id: row.peppolParticipantId,
        email: row.email,
        phone: row.phone,
        fax: row.fax,
        tax_id: row.taxId,
        vat_id: row.vatId,
        street: row.street,
        city: row.city,
        postal_code: row.postalCode,
        state_region: row.stateRegion,
        country: normalizeIsoCountryCode(row.country),
        bank_account: row.bankAccount,
        bank_code: row.bankCode,
        iban: row.iban,
        swift: row.swift,
        delivery_street: row.deliveryStreet,
        delivery_postal_code: row.deliveryPostalCode,
        delivery_city: row.deliveryCity,
        delivery_country: normalizeIsoCountryCode(row.deliveryCountry),
        default_payment_terms_days: (() => {
            if (!row.defaultPaymentTermsDays) return null;
            const days = Number(row.defaultPaymentTermsDays);
            return Number.isFinite(days) ? days : null;
        })(),
        notes: row.notes,
        contact_persons: parseContactPersons(row.contactPersonsJson),
        is_active: sqliteBoolToBoolean(row.isActive),
    };
}

export function contactPayloadFromForm(
    form: ContactFormState,
    includeDelivery: boolean,
): Omit<EvoluContactRow, "id" | "companyId"> & { companyId?: CompanyId } {
    const persons = form.contact_persons
        .filter((p) => (p.name || p.phone || p.email || "").trim() !== "")
        .map((p) => ({
            name: p.name || null,
            phone: p.phone || null,
            email: p.email || null,
        }));

    return {
        name: form.name.trim(),
        registrationNumber: form.registration_number.trim() || null,
        peppolParticipantId: form.peppol_participant_id.trim() || null,
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        fax: form.fax.trim() || null,
        taxId: form.tax_id.trim() || null,
        vatId: form.vat_id.trim() || null,
        street: form.street.trim() || null,
        city: form.city.trim() || null,
        postalCode: form.postal_code.trim() || null,
        stateRegion: form.state_region.trim() || null,
        country: normalizeIsoCountryCode(form.country.trim()),
        bankAccount: form.bank_account.trim() || null,
        bankCode: form.bank_code.trim() || null,
        iban: form.iban.trim() || null,
        swift: form.swift.trim() || null,
        deliveryStreet: includeDelivery ? form.delivery_street.trim() || null : null,
        deliveryPostalCode: includeDelivery ? form.delivery_postal_code.trim() || null : null,
        deliveryCity: includeDelivery ? form.delivery_city.trim() || null : null,
        deliveryCountry: includeDelivery
            ? normalizeIsoCountryCode(form.delivery_country.trim())
            : null,
        defaultPaymentTermsDays:
            form.default_payment_terms_days != null
                ? String(form.default_payment_terms_days)
                : null,
        notes: form.notes.trim() || null,
        contactPersonsJson: persons.length ? JSON.stringify(persons) : null,
        isActive: booleanToSqliteBoolean(form.is_active),
    };
}

export function contactFirstLetter(name: string): string {
    const trimmed = name.trim();
    if (!trimmed) return "#";
    const base = trimmed[0].normalize("NFD").replace(/\p{M}/gu, "").toUpperCase();
    if (base.length === 1 && base >= "A" && base <= "Z") return base;
    return "#";
}

export function filterContacts(
    rows: CompanyContactRow[],
    options: { q?: string; letter?: string },
): CompanyContactRow[] {
    let result = rows.filter((c) => c.is_active !== false);
    const q = options.q?.trim().toLowerCase();
    if (q) {
        result = result.filter((c) => {
            const hay = [
                c.name,
                c.email,
                c.phone,
                c.registration_number,
                c.tax_id,
                c.vat_id,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return hay.includes(q);
        });
    }
    const letter = options.letter;
    if (letter && letter !== "all") {
        result = result.filter((c) => contactFirstLetter(c.name) === letter);
    }
    return result.sort((a, b) => a.name.localeCompare(b.name, "sk"));
}

export function availableContactLetters(rows: CompanyContactRow[]): string[] {
    const letters = new Set<string>();
    for (const row of rows) {
        if (row.is_active === false) continue;
        letters.add(contactFirstLetter(row.name));
    }
    return Array.from(letters).sort();
}
