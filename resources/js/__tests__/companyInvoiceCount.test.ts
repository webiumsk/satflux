import { describe, expect, it } from "vitest";
import { countCompanyInvoicesForList } from "@/evolu/companyInvoiceCount";
import type { CompanyId } from "@/evolu/schema";

const companyA = "company-a" as CompanyId;
const companyB = "company-b" as CompanyId;

describe("countCompanyInvoicesForList", () => {
    it("counts issued and paid invoices but not proformas or drafts", () => {
        const documents = [
            { companyId: companyA, documentType: "invoice", status: "issued" },
            { companyId: companyA, documentType: "invoice", status: "paid" },
            { companyId: companyA, documentType: "invoice", status: "paid" },
            { companyId: companyA, documentType: "invoice", status: "draft" },
            { companyId: companyA, documentType: "proforma", status: "issued" },
            { companyId: companyB, documentType: "invoice", status: "paid" },
        ];

        expect(countCompanyInvoicesForList(documents, companyA)).toBe(4);
        expect(countCompanyInvoicesForList(documents, companyB)).toBe(1);
    });
});
