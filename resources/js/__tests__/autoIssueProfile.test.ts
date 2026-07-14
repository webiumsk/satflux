import { beforeEach, describe, expect, it, vi } from "vitest";
import { resolveImportSettleActions } from "../evolu/integrationInboxPaid";

const apiMocks = vi.hoisted(() => ({
    getAutoIssueProfile: vi.fn(),
    putAutoIssueProfile: vi.fn(),
    deleteAutoIssueProfile: vi.fn(),
    updateEmailSettings: vi.fn(),
    seriesList: vi.fn(),
    seriesCreate: vi.fn(),
    seriesUpdate: vi.fn(),
    ensureBridge: vi.fn(),
}));

vi.mock("@/services/api", () => ({
    default: {},
    invoicingApi: {
        companies: {
            getAutoIssueProfile: apiMocks.getAutoIssueProfile,
            putAutoIssueProfile: apiMocks.putAutoIssueProfile,
            deleteAutoIssueProfile: apiMocks.deleteAutoIssueProfile,
            updateEmailSettings: apiMocks.updateEmailSettings,
        },
        numberSeries: {
            list: apiMocks.seriesList,
            create: apiMocks.seriesCreate,
            update: apiMocks.seriesUpdate,
        },
    },
}));
vi.mock("@/evolu/bridgeCompanyEnsure", () => ({
    ensureBridgeCompanyIdForLocalCompany: apiMocks.ensureBridge,
}));
// ephemeralBridge and the lazy sync path import the real Evolu client -
// replace it with inert queries + an empty-loading evolu.
vi.mock("@/evolu/client", () => ({
    allDocumentSnapshotsQuery: "allDocumentSnapshotsQuery",
    allDocumentsQuery: "allDocumentsQuery",
    allNumberSeriesQuery: "allNumberSeriesQuery",
    evolu: { loadQuery: vi.fn(async () => []) },
}));

import {
    buildAutoIssueCompanyPayload,
    buildLocalHighCounters,
    disableAutoIssueProfile,
    fetchAutoIssueProfileStatus,
    syncAutoIssueProfile,
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

describe("auto-issue sync orchestration", () => {
    const localCompany = {
        id: "local-1",
        legal_name: "Webium s.r.o.",
        email_settings: { delivery_method: "smtp", smtp: { host: "mail.example.com" } },
    };

    beforeEach(() => {
        for (const mock of Object.values(apiMocks)) {
            mock.mockReset();
        }
        apiMocks.ensureBridge.mockResolvedValue({ ok: true, bridgeCompanyId: "bridge-1" });
        apiMocks.seriesList.mockResolvedValue([]);
        apiMocks.putAutoIssueProfile.mockResolvedValue({
            company_id: "bridge-1",
            auto_email: true,
            synced_at: "2026-07-14T10:00:00Z",
        });
    });

    it("activates the profile only AFTER email settings and series synced", async () => {
        const result = await syncAutoIssueProfile(localCompany, true);
        expect(result.ok).toBe(true);

        const emailOrder = apiMocks.updateEmailSettings.mock.invocationCallOrder[0];
        const seriesOrder = apiMocks.seriesList.mock.invocationCallOrder[0];
        const profileOrder = apiMocks.putAutoIssueProfile.mock.invocationCallOrder[0];
        expect(emailOrder).toBeLessThan(profileOrder);
        expect(seriesOrder).toBeLessThan(profileOrder);
    });

    it("a failing prerequisite leaves auto-issue OFF (profile never written)", async () => {
        apiMocks.updateEmailSettings.mockRejectedValue(new Error("smtp save failed"));
        const errorSpy = vi.spyOn(console, "error").mockImplementation(() => {});

        const result = await syncAutoIssueProfile(localCompany, true);
        expect(result).toEqual({ ok: false, error: "sync_failed" });
        expect(apiMocks.putAutoIssueProfile).not.toHaveBeenCalled();

        errorSpy.mockRestore();
    });

    it("missing company identity is reported distinctly from bridge failures", async () => {
        apiMocks.ensureBridge.mockResolvedValue({ ok: true, bridgeCompanyId: null });
        expect(await syncAutoIssueProfile(localCompany, true)).toEqual({
            ok: false,
            error: "company_identity_missing",
        });
        expect(await fetchAutoIssueProfileStatus("local-1")).toEqual({
            ok: false,
            error: "company_identity_missing",
        });
        expect(await disableAutoIssueProfile("local-1")).toEqual({
            ok: false,
            error: "company_identity_missing",
        });

        apiMocks.ensureBridge.mockResolvedValue({ ok: false });
        expect(await syncAutoIssueProfile(localCompany, true)).toEqual({
            ok: false,
            error: "bridge_unavailable",
        });
    });

    it("fetch status distinguishes no-profile from fetch failure", async () => {
        apiMocks.getAutoIssueProfile.mockResolvedValue(null);
        expect(await fetchAutoIssueProfileStatus("local-1")).toEqual({ ok: true, status: null });

        apiMocks.getAutoIssueProfile.mockResolvedValue({
            company_id: "bridge-1",
            auto_email: false,
            synced_at: "2026-07-14T10:00:00Z",
        });
        expect(await fetchAutoIssueProfileStatus("local-1")).toEqual({
            ok: true,
            status: { autoEmail: false, syncedAt: "2026-07-14T10:00:00Z" },
        });

        apiMocks.getAutoIssueProfile.mockRejectedValue(new Error("500"));
        expect(await fetchAutoIssueProfileStatus("local-1")).toEqual({
            ok: false,
            error: "fetch_failed",
        });
    });

    it("disable deletes the server profile", async () => {
        apiMocks.deleteAutoIssueProfile.mockResolvedValue(undefined);
        expect(await disableAutoIssueProfile("local-1")).toEqual({ ok: true });
        expect(apiMocks.deleteAutoIssueProfile).toHaveBeenCalledWith("bridge-1");
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
