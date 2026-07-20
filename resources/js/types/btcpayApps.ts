/**
 * Shared shapes for the BTCPay app pages (PoS / Crowdfund / Pay Button /
 * Tickets). BTCPay's Greenfield payloads are loosely versioned, so entity
 * types keep an index signature for fields we do not model - typed access
 * for what the UI reads, no `any`.
 */

/** Minimal store handle passed between app pages (full row from /api/stores). */
export type StoreRef = { id: string; default_currency?: string; name?: string } & Record<string, unknown>;

/** Minimal app handle (id + type as the pages use them). */
export type AppRef = {
    id: string;
    app_type?: string;
    name?: string;
    archived?: boolean;
    config?: RawAppConfig;
    btcpay_app_id?: string;
    btcpay_app_url?: string;
} & Record<string, unknown>;

/** One PoS catalog item as edited in the products editor. */
export interface PosProduct {
    id: string;
    title: string;
    priceType: string;
    price: number;
    taxRate: number | null;
    image: string;
    description: string;
    categories: string | null;
    inventory: number | null;
    buyButtonText: string | null;
    disabled: boolean;
}

/** Raw PoS template item as stored in the BTCPay app config (untrusted shape). */
export type RawPosItem = Partial<{
    id: string;
    title: string;
    priceType: string;
    price: number | string;
    taxRate: number | string | null;
    image: string;
    description: string;
    categories: string[] | string;
    inventory: number | string | null;
    buyButtonText: string | null;
    disabled: boolean;
    imageUrl: string;
}> & Record<string, unknown>;

/** Crowdfund perks are edited with the same catalog-item shape as PoS products. */
export type CrowdfundPerk = PosProduct;

/** Crowdfund "Additional options" panels (shapes owned by CrowdfundForm). */
export interface CrowdfundContributions {
    sortByPopularity: boolean;
    displayRanking: boolean;
    displayValue: boolean;
    noAdditionalAfterTarget: boolean;
}

export interface CrowdfundBehavior {
    countAllInvoices: boolean;
}

export interface CrowdfundCheckout {
    /** BTCPay FormId: "" | "Email" | "Address" | custom form UUID */
    formId: string;
}

export interface CrowdfundAdvanced {
    htmlLanguage: string;
    htmlMetaTags: string;
    enableSounds: boolean;
    enableAnimations: boolean;
    enableDiscussion: boolean;
    soundsText: string;
    animationColorsText: string;
    disqusShortname: string;
    callbackNotificationUrl: string;
}

/**
 * Raw BTCPay app config blob (crowdfund/PoS). Fields the UI reads are typed
 * loosely (the API is string-happy); everything else stays unknown.
 */
export type RawAppConfig = Partial<{
    id: string;
    appId: string;
    appName: string;
    defaultView: string;
    showItems: boolean | string;
    showCustomAmount: boolean | string;
    showDiscount: boolean | string;
    showSearch: boolean | string;
    showCategories: boolean | string;
    enableTips: boolean | string;
    tipsMessage: string;
    defaultTaxRate: number | string;
    tipTaxRate: number | string | null;
    taxIncludedInPrice: boolean | string;
    requestCustomerData: string;
    fixedAmountPayButtonText: string;
    customAmountPayButtonText: string;
    redirectUrl: string;
    redirectAutomatically: boolean | string;
    title: string;
    displayTitle: string;
    tagline: string;
    description: string;
    enabled: boolean;
    currency: string;
    targetCurrency: string;
    targetAmount: number | string;
    goal: number | string;
    enforceTargetAmount: boolean;
    startDate: number | string | null;
    endDate: number | string | null;
    resetEvery: string;
    resetEveryAmount: number | string;
    mainImageUrl: string;
    featuredImage: string;
    featuredImageUrl: string;
    makePublic: boolean;
    notificationUrl: string;
    formId: string;
    htmlLang: string;
    htmlMetaTags: string;
    disqusEnabled: boolean;
    disqusShortname: string;
    soundsEnabled: boolean;
    sounds: string[] | string;
    animationsEnabled: boolean;
    animationColors: string[] | string;
    items: unknown;
    template: unknown;
    perks: unknown;
    perksTemplate: unknown;
    contributions: Partial<CrowdfundContributions>;
    crowdfundBehavior: Record<string, unknown>;
    checkout: Partial<CrowdfundCheckout> & Record<string, unknown>;
    advanced: Partial<CrowdfundAdvanced>;
}> & Record<string, unknown>;
