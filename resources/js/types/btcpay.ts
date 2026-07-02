export type BtcPayAppType =
    | 'PointOfSale'
    | 'Crowdfund'
    | 'PaymentButton'
    | 'PayButton'
    | string;

export interface BtcPayApp {
    id: string;
    app_type: BtcPayAppType;
    name: string;
    archived?: boolean;
    config?: Record<string, unknown>;
    metadata?: Record<string, unknown>;
}

export interface StoreInvoiceSummary {
    id: string;
    invoice_id: string;
    status: string;
    amount: string;
    currency: string;
    created_time: string;
    source?: 'pos' | 'pay_button' | 'ln_address' | 'tickets' | 'api' | 'other';
}

export interface StoreDashboardApps {
    crowdfund: BtcPayApp[];
    point_of_sale: BtcPayApp[];
    payment_button: BtcPayApp[];
}

export interface StoreDashboardStats {
    paid_invoices_last_7d: number;
    total_invoices: number;
    recent_invoices: StoreInvoiceSummary[];
    apps: StoreDashboardApps;
    is_ready: boolean;
    has_wallet_connection: boolean;
    total_revenue_sats?: number;
    total_revenue_default_currency?: number;
    default_currency?: string;
    sales: {
        last_7_days: Array<{ date: string; count: number }>;
        last_30_days: Array<{ date: string; count: number }>;
        total_7d: number;
        total_30d: number;
    };
    top_items: Array<{
        name: string;
        count: number;
        total: number;
        currency: string;
    }>;
    can_filter_by_source?: boolean;
    by_source?: Record<string, unknown>;
}
