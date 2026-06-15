import type { Evolu } from "@evolu/common/local-first";
import {
    allCompaniesDetailQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { EvoluCompanyRow } from "./companyMap";
import type { DocumentLinePayload } from "./documentCrud";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "./documentMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { generateLocalRecurringDocument } from "./recurringCrud";
import type { EvoluRecurringProfileLineRow, EvoluRecurringProfileRow } from "./recurringMap";
import { listDueRecurringProfiles } from "./recurringNextDate";
import type { InvoicingLocalSchema, RecurringProfileId } from "./schema";

export type RecurringDueGeneration = {
    profileId: string;
    companyId: string;
    documentId: string;
    number: string;
    title: string | null;
};

export type RecurringDueRunResult = {
    generated: RecurringDueGeneration[];
    errors: number;
};

const MAX_CATCHUP_ROUNDS = 48;

type RunnerSnapshot = {
    companies: EvoluCompanyRow[];
    profiles: EvoluRecurringProfileRow[];
    profileLines: EvoluRecurringProfileLineRow[];
    documents: EvoluDocumentRow[];
    documentLines: EvoluDocumentLineRow[];
    series: EvoluNumberSeriesRow[];
};

async function loadRunnerSnapshot(evolu: Evolu<InvoicingLocalSchema>): Promise<RunnerSnapshot> {
    const [companies, profiles, profileLines, documents, documentLines, series] = await Promise.all([
        evolu.loadQuery(allCompaniesDetailQuery),
        evolu.loadQuery(allRecurringProfilesQuery),
        evolu.loadQuery(allRecurringProfileLinesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allNumberSeriesQuery),
    ]);

    return {
        companies: companies as EvoluCompanyRow[],
        profiles: profiles as EvoluRecurringProfileRow[],
        profileLines: profileLines as EvoluRecurringProfileLineRow[],
        documents: documents as EvoluDocumentRow[],
        documentLines: documentLines as EvoluDocumentLineRow[],
        series: series as EvoluNumberSeriesRow[],
    };
}

function taxOptionsForCompany(company: EvoluCompanyRow) {
    const defaultVat = Number(company.vatRateDefault ?? 23) || 23;
    return {
        defaultVat,
        lineTaxApplies: (_line: DocumentLinePayload) => company.vatPayer === 1,
        lineTaxRate: (line: DocumentLinePayload) => Number(line.tax_rate) || defaultVat,
    };
}

let runnerInFlight: Promise<RecurringDueRunResult> | null = null;

export async function processDueLocalRecurringProfiles(
    evolu: Evolu<InvoicingLocalSchema>,
    today = new Date().toISOString().slice(0, 10),
): Promise<RecurringDueRunResult> {
    if (runnerInFlight) {
        return runnerInFlight;
    }

    runnerInFlight = (async () => {
        const generated: RecurringDueGeneration[] = [];
        let errors = 0;

        for (let round = 0; round < MAX_CATCHUP_ROUNDS; round += 1) {
            const snapshot = await loadRunnerSnapshot(evolu);
            const dueProfiles = listDueRecurringProfiles(snapshot.profiles, today);
            if (dueProfiles.length === 0) {
                break;
            }

            let issuedThisRound = 0;
            for (const profile of dueProfiles) {
                const company = snapshot.companies.find((row) => row.id === profile.companyId);
                if (!company) {
                    errors += 1;
                    continue;
                }

                const fresh = await loadRunnerSnapshot(evolu);
                const result = await generateLocalRecurringDocument(
                    evolu,
                    profile.id as RecurringProfileId,
                    company,
                    fresh.profiles,
                    fresh.profileLines,
                    fresh.documents,
                    fresh.documentLines,
                    fresh.series,
                    taxOptionsForCompany(company),
                );

                if (!result.ok) {
                    errors += 1;
                    continue;
                }

                generated.push({
                    profileId: profile.id,
                    companyId: profile.companyId,
                    documentId: result.value.documentId,
                    number: result.value.number,
                    title: profile.title,
                });
                issuedThisRound += 1;
            }

            if (issuedThisRound === 0) {
                break;
            }
        }

        return { generated, errors };
    })();

    try {
        return await runnerInFlight;
    } finally {
        runnerInFlight = null;
    }
}
