import { describe, expect, it, vi } from "vitest";
import { resolveImportSettleActions } from "../evolu/integrationInboxPaid";

// The sync module pulls the API client + bridge ensure transitively - keep
// the pure builders importable without network/WASM.
vi.mock("@/services/api", () => ({
    default: {},
    invoicingApi: { companies: {}, numberSeries: {} },
}));
vi.mock("@/evolu/bridgeCompanyEnsure", () => ({
    ensureBridgeCompanyIdForLocalCompany: vi.fn(),
}));
// ephemeralBridge transitively imports the real Evolu client - inert token.
vi.mock("@/evolu/client", () => ({
    allDocumentSnapshotsQuery: "allDocumentSnapshotsQuery",
}));

import {
    buildAutoIssueCompanyPayload,
    buildLocalHighCounters,
} from "../evolu/autoIssueProfileSync";
import type { CompanyId } from "../evolu/schema";
import type { EvoluDocumentRow } from "../evolu/documentMap";
import type { EvoluNumberSeriesRow } from "../evolu/numberSeriesMap";

describe("resolveImportSettleActions (auto-issue convergence)", () => {
    it("auto-issued paid entry: applies the reserved number AND marks paid", () => {
        const actions = resolveImportSettleActions({
            number: "FV20260042",
            is_paid: true,
            order_total: 99,
        });
        expect(actions).toEqual({
            reservedNumber: "FV20260042",
            issueLocally: false,
            markPaid: true,
        });
    });

    it("paid entry without a number issues locally and marks paid (legacy path)", () => {
        const actions = resolveImportSettleActions({ is_paid: true });
        expect(actions).toEqual({
            reservedNumber: null,
            issueLocally: true,
            markPaid: true,
        });
    });

    it("unpaid entry with a number only applies it", () => {
        const actions = resolveImportSettleActions({ number: "FV20260001", is_paid: false });
        expect(actions).toEqual({
            reservedNumber: "FV20260001",
            issueLocally: false,
            markPaid: false,
        });
    });

    it("unpaid entry without a number stays a draft", () => {
        const actions = resolveImportSettleActions({ payment_method: "bacs" });
        expect(actions).toEqual({
            reservedNumber: null,
            issueLocally: false,
            markPaid: false,
        });
    });
});

describe("buildAutoIssueCompanyPayload", () => {
    const company = {
        id: "c1",
        legal_name: "Webium s.r.o.",
        trade_name: "Webium",
        country: "sk",
        iban: "SK3112000000198742637541",
        vat_payer: 1,
        email_settings: { delivery_method: "smtp", smtp: { password: "secret" } },
        app_settings: {
            show_pay_by_square: false,
            efaktura_sapi_client_secret: "EFAKTURA-SECRET",
        },
        logo_url: "data:image/png;base64,AAA",
    };

    it("maps header fields and normalizes the country", () => {
        const payload = buildAutoIssueCompanyPayload(company);
        expect(payload.legal_name).toBe("Webium s.r.o.");
        expect(payload.country).toBe("SK");
        expect(payload.vat_payer).toBe(true);
        expect(payload.logo_url).toBe("data:image/png;base64,AAA");
    });

    it("never carries email settings or efaktura credentials", () => {
        const payload = buildAutoIssueCompanyPayload(company);
        expect(payload).not.toHaveProperty("email_settings");
        expect(payload.app_settings).toEqual({ show_pay_by_square: false });
        expect(JSON.stringify(payload)).not.toContain("EFAKTURA-SECRET");
        expect(JSON.stringify(payload)).not.toContain("secret");
    });
});

describe("buildLocalHighCounters", () => {
    it("reports the highest locally issued invoice counter for the local format", () => {
        const companyId = "c1" as CompanyId;
        const series = [
            {
                id: "s1",
                companyId,
                documentType: "invoice",
                format: "FVYYYYNNNN",
                name: "FV",
                isDefault: 1,
            },
        ] as unknown as EvoluNumberSeriesRow[];
        const documents = [
            { id: "d1", companyId, documentType: "invoice", status: "issued", number: "FV20260041" },
            { id: "d2", companyId, documentType: "invoice", status: "issued", number: "FV20260007" },
            { id: "d3", companyId, documentType: "invoice", status: "draft", number: null },
        ] as unknown as EvoluDocumentRow[];

        expect(buildLocalHighCounters(companyId, documents, series)).toEqual({ invoice: 41 });
    });

    it("returns zero for an empty dataset", () => {
        expect(buildLocalHighCounters("c1" as CompanyId, [], [])).toEqual({ invoice: 0 });
    });
});
