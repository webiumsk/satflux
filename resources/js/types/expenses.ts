export type ExpenseCurrencySummary = {
    currency: string;
    count: number;
    total: string;
    unpaid_total: string;
};

export type ExpenseSummary = {
    count: number;
    currencies: ExpenseCurrencySummary[];
};
