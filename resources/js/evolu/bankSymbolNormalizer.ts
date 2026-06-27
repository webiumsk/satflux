export function normalizeVariableSymbol(value: string | null | undefined): string | null {
    if (value == null || value.trim() === "") return null;
    const digits = value.replace(/\D/g, "");
    return digits !== "" ? digits : null;
}

export function normalizeConstantSymbol(value: string | null | undefined): string | null {
    return normalizeVariableSymbol(value);
}

export function normalizeSpecificSymbol(value: string | null | undefined): string | null {
    return normalizeVariableSymbol(value);
}
