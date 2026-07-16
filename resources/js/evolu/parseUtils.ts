import { maxLength, NonEmptyString } from "@evolu/common";

/**
 * Generic over the validator's from() so the branded value type flows to the
 * caller - an erased ReturnType<ReturnType<typeof maxLength>> alias does not
 * match the concrete branded validators structurally.
 */
type Validator<R> = { from: (value: string) => R };

export function emptyToNull(value: string | null | undefined): string | null {
    if (value == null) return null;
    const trimmed = value.trim();
    return trimmed === "" ? null : trimmed;
}

export function parseRequired<R>(value: string, type: Validator<R>): R {
    return type.from(value.trim());
}

export function parseOptional<R>(
    value: string | null | undefined,
    type: Validator<R>,
): R | { ok: true; value: null } {
    const normalized = emptyToNull(value);
    if (normalized == null) return { ok: true as const, value: null };
    return type.from(normalized);
}

export const LegalNameType = maxLength(255)(NonEmptyString);
export const CurrencyType = maxLength(3)(NonEmptyString);
export const CountryType = maxLength(2)(NonEmptyString);
export const Opt16 = maxLength(16)(NonEmptyString);
export const Opt32 = maxLength(32)(NonEmptyString);
export const Opt64 = maxLength(64)(NonEmptyString);
export const Opt128 = maxLength(128)(NonEmptyString);
export const Opt255 = maxLength(255)(NonEmptyString);
export const Opt512 = maxLength(512)(NonEmptyString);
