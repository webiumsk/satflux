/** Anchor ids in #how-it-works and i18n keys for the Features submenu. */
export const LANDING_FEATURE_NAV = [
  { id: 'how-it-works-pos', labelKey: 'header.feature_pos' },
  { id: 'how-it-works-eshop', labelKey: 'header.feature_eshop' },
  { id: 'how-it-works-crowdfund', labelKey: 'header.feature_crowdfund' },
  { id: 'how-it-works-ln-address', labelKey: 'header.feature_ln_address' },
  { id: 'how-it-works-tickets', labelKey: 'header.feature_tickets' },
  { id: 'how-it-works-raffle', labelKey: 'header.feature_raffle' },
  { id: 'how-it-works-pay-button', labelKey: 'header.feature_pay_button' },
  { id: 'how-it-works-exports', labelKey: 'header.feature_exports' },
] as const;

export type LandingFeatureNavId = (typeof LANDING_FEATURE_NAV)[number]['id'];
