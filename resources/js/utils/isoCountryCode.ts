const COUNTRY_NAME_TO_ISO: Record<string, string> = {
    slovensko: "SK",
    slovakia: "SK",
    cesko: "CZ",
    "czech republic": "CZ",
    czechia: "CZ",
    rakusko: "AT",
    austria: "AT",
    osterreich: "AT",
    madarsko: "HU",
    hungary: "HU",
    magyarorszag: "HU",
    polsko: "PL",
    poland: "PL",
    polska: "PL",
    nemecko: "DE",
    germany: "DE",
    deutschland: "DE",
    usa: "US",
    "united states": "US",
    "united states of america": "US",
    "united kingdom": "GB",
    "great britain": "GB",
    england: "GB",
};

function countryLookupKey(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/\p{M}/gu, "");
}

/** Normalize contact/company country to ISO 3166-1 alpha-2 for ephemeral API payloads. */
export function normalizeIsoCountryCode(value: string | null | undefined): string | null {
    const text = (value ?? "").trim();
    if (!text) {
        return null;
    }
    if (text.length === 2) {
        return text.toUpperCase();
    }

    const mapped = COUNTRY_NAME_TO_ISO[countryLookupKey(text)];
    return mapped ?? null;
}
