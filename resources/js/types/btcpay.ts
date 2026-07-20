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

export interface StoreSettingsReceipt {
    enabled: boolean;
    show_qr: boolean | null;
    show_payments: boolean | null;
}

/**
 * Response of GET/PUT /stores/{id}/settings - snake_case mapping of the
 * BTCPay store built in StoreSettingsController::mapBtcPayStoreToResponse.
 */
export interface StoreSettings {
    id: string;
    name: string;
    website: string | null;
    support_url: string | null;
    logo_url: string | null;
    css_url: string | null;
    payment_sound_url: string | null;
    brand_color: string | null;
    apply_brand_color_to_backend: boolean;
    default_currency: string;
    additional_tracked_rates: string[];
    invoice_expiration: number | null;
    refund_bolt11_expiration: number | null;
    display_expiration_timer: number | null;
    monitoring_expiration: number | null;
    speed_policy: string | null;
    lightning_description_template: string | null;
    payment_tolerance: number | null;
    archived: boolean;
    anyone_can_create_invoice: boolean;
    receipt: StoreSettingsReceipt;
    lightning_amount_in_satoshi: boolean;
    lightning_private_route_hints: boolean;
    on_chain_with_ln_invoice_fallback: boolean;
    redirect_automatically: boolean;
    show_recommended_fee: boolean;
    recommended_fee_block_target: number;
    default_lang: string;
    html_title: string | null;
    network_fee_mode: string | null;
    pay_join_enabled: boolean;
    auto_detect_language: boolean;
    show_pay_in_wallet_button: boolean;
    show_store_header: boolean;
    celebrate_payment: boolean;
    play_sound_on_payment: boolean;
    lazy_payment_methods: boolean;
    default_payment_method: string | null;
    payment_method_criteria: Array<Record<string, unknown>>;
    timezone: string;
    preferred_exchange: string | null;
    store_url: string;
    lnurl_enabled: boolean;
    lnurl_classic_mode: boolean;
    lnurl_allow_payee_comment: boolean;
}

/** PUT /stores/{id}/settings body - name is required, id is never sent, the rest is optional. */
export type UpdateStoreSettingsPayload = Partial<Omit<StoreSettings, 'id'>> & { name: string };

/** One BTCPay payment-method criterion as edited in the settings form. */
export interface StoreSettingsPaymentCriterion {
    payment_method: string;
    type: string;
    value: string;
}

/**
 * Normalized (null-free) editing shape backing the StoreSettings tabs. The
 * loaded StoreSettings response is mapped into this on load; every field has
 * a default so the child tabs can v-model it directly.
 */
export interface StoreSettingsForm {
    name: string;
    website: string;
    support_url: string;
    css_url: string;
    payment_sound_url: string;
    brand_color: string;
    apply_brand_color_to_backend: boolean;
    default_currency: string;
    additional_tracked_rates: string[];
    invoice_expiration: number;
    refund_bolt11_expiration: number;
    display_expiration_timer: number;
    monitoring_expiration: number;
    speed_policy: string;
    lightning_description_template: string;
    payment_tolerance: number;
    archived: boolean;
    anyone_can_create_invoice: boolean;
    receipt: StoreSettingsReceipt;
    lightning_amount_in_satoshi: boolean;
    lightning_private_route_hints: boolean;
    on_chain_with_ln_invoice_fallback: boolean;
    lnurl_enabled: boolean;
    lnurl_classic_mode: boolean;
    lnurl_allow_payee_comment: boolean;
    redirect_automatically: boolean;
    show_recommended_fee: boolean;
    recommended_fee_block_target: number;
    default_lang: string;
    html_title: string;
    network_fee_mode: string;
    pay_join_enabled: boolean;
    auto_detect_language: boolean;
    show_pay_in_wallet_button: boolean;
    show_store_header: boolean;
    celebrate_payment: boolean;
    play_sound_on_payment: boolean;
    lazy_payment_methods: boolean;
    default_payment_method: string;
    payment_method_criteria: StoreSettingsPaymentCriterion[];
    timezone: string;
    preferred_exchange: string;
}
