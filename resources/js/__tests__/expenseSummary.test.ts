import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import { createI18n } from "vue-i18n";
import en from "@/locales/en.json";
import sk from "@/locales/sk.json";
import ExpenseListSummary from "@/components/invoicing/ExpenseListSummary.vue";
import { formatExpenseSummaryAmount, summarizeExpenses } from "@/utils/expenseSummary";

describe("filtered expense totals", () => {
    it("counts all rows and keeps currencies and payment states separate", () => {
        expect(summarizeExpenses([
            { status: "recorded", total: "0.10", currency: " eur " },
            { status: "paid", total: "0.20", currency: "EUR" },
            { status: "recorded", total: "100.25", currency: "czk" },
            { status: "cancelled", total: "999.00", currency: "EUR" },
        ])).toEqual({
            count: 3,
            currencies: [
                { currency: "CZK", count: 1, total: "100.25", unpaid_total: "100.25" },
                { currency: "EUR", count: 2, total: "0.30", unpaid_total: "0.10" },
            ],
        });
    });

    it("adds exact cents beyond Number's safe integer range and preserves credits", () => {
        expect(summarizeExpenses([
            { status: "recorded", total: "9007199254740991.99", currency: "EUR" },
            { status: "recorded", total: "0.02", currency: "EUR" },
            { status: "paid", total: "-10.50", currency: "EUR" },
        ]).currencies).toEqual([
            { currency: "EUR", count: 3, total: "9007199254740981.51", unpaid_total: "9007199254740992.01" },
        ]);
    });

    it("has no page-size cap and defaults blank currencies consistently", () => {
        const rows = Array.from({ length: 52 }, () => ({ status: "recorded", total: "0.10", currency: " " }));
        expect(summarizeExpenses(rows)).toEqual({
            count: 52,
            currencies: [{ currency: "EUR", count: 52, total: "5.20", unpaid_total: "5.20" }],
        });
    });

    it("counts zero totals and returns an empty summary for no active expenses", () => {
        expect(summarizeExpenses([{ status: "recorded", total: "0", currency: "EUR" }]).currencies[0])
            .toEqual({ currency: "EUR", count: 1, total: "0.00", unpaid_total: "0.00" });
        expect(summarizeExpenses([{ status: "cancelled", total: "1.00", currency: "EUR" }]))
            .toEqual({ count: 0, currencies: [] });
    });

    it("uses document status and never derives a remaining balance from free-text notes", () => {
        const rows = [{ status: "recorded", total: "100.00", currency: "EUR", internal_note: "Payment amount: 80.00" }];
        expect(summarizeExpenses(rows).currencies[0].unpaid_total).toBe("100.00");
    });

    it.each(["NaN", "Infinity", "1e6", "12,50", "12.345", "", "not a total"])(
        "rejects malformed or over-precision monetary values: %s", (total) => {
            expect(() => summarizeExpenses([{ status: "recorded", total, currency: "EUR" }]))
                .toThrow("invalid_expense_total");
        },
    );
});

describe("exact expense summary formatting", () => {
    it("formats large values and negative sub-unit values without rounding their cents", () => {
        expect(formatExpenseSummaryAmount("9007199254740992.01", "EUR", "en-US"))
            .toBe("€9,007,199,254,740,992.01");
        expect(formatExpenseSummaryAmount("-0.01", "USD", "en-US")).toBe("-$0.01");
        expect(formatExpenseSummaryAmount("-12.50", "EUR", "sk-SK")).toContain("-12,50");
    });

    it.each(["123", "EU$"])("formats malformed stored currency %s as exact decimal plus literal code", (currency) => {
        expect(formatExpenseSummaryAmount("9007199254740992.01", currency, "en-US"))
            .toBe(`9,007,199,254,740,992.01 ${currency}`);
        expect(formatExpenseSummaryAmount("-0.01", currency, "en-US")).toBe(`-0.01 ${currency}`);
        expect(formatExpenseSummaryAmount("-12.50", currency, "sk-SK")).toBe(`-12,50 ${currency}`);
    });

    it("preserves malformed currency groups and escapes their literal codes in the footer", () => {
        const summary = summarizeExpenses([
            { status: "recorded", total: "12.50", currency: "123" },
            { status: "paid", total: "0.01", currency: "EU$" },
            { status: "recorded", total: "-0.01", currency: "<b>" },
            { status: "recorded", total: "3.00", currency: "EUR" },
        ]);
        expect(summary.count).toBe(4);
        expect(summary.currencies.map((group) => group.currency)).toEqual(["123", "<B>", "EU$", "EUR"]);
        const wrapper = mount(ExpenseListSummary, {
            props: { summary },
            global: { plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })] },
        });
        expect(wrapper.findAll("dl")).toHaveLength(4);
        expect(wrapper.text()).toContain("12.50 123");
        expect(wrapper.text()).toContain("0.01 EU$");
        expect(wrapper.text()).toContain("-0.01 <B>");
        expect(wrapper.text()).toContain("€3.00");
        expect(wrapper.find("b").exists()).toBe(false);
        wrapper.unmount();
    });

    it("renders semantic currency groups, total count and unpaid document labels", () => {
        const summary = summarizeExpenses([
            { status: "recorded", total: "12.50", currency: "EUR" },
            { status: "paid", total: "100.00", currency: "CZK" },
        ]);
        const wrapper = mount(ExpenseListSummary, {
            props: { summary },
            global: { plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })] },
        });
        expect(wrapper.find('section[aria-labelledby="expense-list-summary-heading"]').exists()).toBe(true);
        expect(wrapper.find("h2").text()).toBe("Filtered expenses summary");
        expect(wrapper.text()).toContain("Expenses: 2");
        expect(wrapper.findAll("h3").map((node) => node.text())).toEqual(["CZK · Expenses: 1", "EUR · Expenses: 1"]);
        expect(wrapper.findAll("dl")).toHaveLength(2);
        expect(wrapper.findAll("dt").map((node) => node.text()))
            .toEqual(["Total", "Unpaid expense total", "Total", "Unpaid expense total"]);
        expect(wrapper.classes()).toContain("w-full");
        expect(wrapper.classes()).toContain("sm:w-fit");
        wrapper.unmount();
    });

    it("renders the Slovak labels without changing precise formatted values", () => {
        const wrapper = mount(ExpenseListSummary, {
            props: { summary: summarizeExpenses([{ status: "recorded", total: "0.30", currency: "EUR" }]) },
            global: { plugins: [createI18n({ legacy: false, locale: "sk", messages: { sk } })] },
        });
        expect(wrapper.text()).toContain("Sumár filtrovaných nákladov");
        expect(wrapper.text()).toContain("Počet nákladov: 1");
        expect(wrapper.text()).toContain("Suma neuhradených nákladov");
        expect(wrapper.findAll("dd").every((node) => node.text().includes("0,30"))).toBe(true);
        wrapper.unmount();
    });
});
