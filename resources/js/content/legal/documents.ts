import type { LegalDocId, LegalDocumentContent, LegalLocale } from './types';
import { LEGAL_OPERATOR, operatorAddressBlock } from './operator';
import { dpaEn, dpaEs, dpaSk, imprintEn, imprintEs, imprintSk } from './imprintDpa';

const OPERATOR = LEGAL_OPERATOR.name;
const OPERATOR_ADDRESS = operatorAddressBlock();
const OPERATOR_JURISDICTION = LEGAL_OPERATOR.jurisdiction;
const OPERATOR_EIN = LEGAL_OPERATOR.ein;
const SERVICE_NAME = LEGAL_OPERATOR.serviceName;
const CONTACT_EMAIL = LEGAL_OPERATOR.contactEmail;
const PRIVACY_EMAIL = LEGAL_OPERATOR.privacyEmail;
const LAST_UPDATED = '2026-06-02';

const termsEn: LegalDocumentContent = {
  title: 'Terms of Service',
  metaDescription:
    'Terms of Service for satflux.io operated by Webium LLC, a US LLC serving merchants worldwide.',
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Draft template for review by qualified legal counsel. Do not rely on this text as final legal advice.',
  sections: [
    {
      heading: '1. Agreement and operator',
      paragraphs: [
        `These Terms of Service ("Terms") govern your access to and use of ${SERVICE_NAME}, the Bitcoin and Lightning merchant control panel operated by ${OPERATOR}, a limited liability company formed in ${OPERATOR_JURISDICTION} ("we", "us", "our").`,
        `${OPERATOR_ADDRESS}. EIN: ${OPERATOR_EIN}.`,
        'By creating an account or using the service, you agree to these Terms. If you use the service on behalf of a business, you represent that you have authority to bind that business.',
      ],
    },
    {
      heading: '2. Service description',
      paragraphs: [
        `${SERVICE_NAME} provides tools to manage BTCPay Server stores, accept Bitcoin and Lightning payments, and (on paid plans) issue business invoices and related accounting features. We orchestrate BTCPay and connected wallets; we do not custody your funds unless you choose a custodial wallet integration.`,
        'Features may change. Beta or preview features are provided as-is and may be modified or withdrawn.',
      ],
    },
    {
      heading: '3. Accounts and eligibility',
      paragraphs: [
        'You must provide accurate registration information and keep credentials secure. You are responsible for activity under your account.',
        'You must be at least 18 years old (or the age of majority in your jurisdiction) and not prohibited from using the service under applicable law.',
      ],
    },
    {
      heading: '4. Subscriptions and payments',
      paragraphs: [
        'Paid plans (e.g. Pro) are billed in Bitcoin via BTCPay checkout unless otherwise stated. Trial periods and grace periods are described on the pricing page and in product documentation.',
        'Subscription fees are generally non-refundable except where required by mandatory consumer law. Downgrades take effect at the end of the current billing period.',
        'Taxes (including EU VAT, US sales tax, or local equivalents) may apply depending on your location and our tax registration status. Where we are not registered to collect tax in your jurisdiction, you may remain responsible for self-assessing applicable taxes.',
      ],
    },
    {
      heading: '5. Acceptable use',
      paragraphs: [
        'You may not use the service for unlawful activity, sanctions evasion, fraud, malware distribution, or to infringe third-party rights.',
        'You may not attempt to bypass security, access other tenants\' data, or overload our infrastructure. We may suspend or terminate accounts that violate these rules.',
      ],
    },
    {
      heading: '6. Intellectual property',
      paragraphs: [
        'We retain rights in the service, branding, and documentation. Open-source components are licensed under their respective licenses (see our GitHub repository).',
        'You retain rights in your business data, store content, and invoice content you upload or generate.',
      ],
    },
    {
      heading: '7. Limitation of liability',
      paragraphs: [
        'TO THE MAXIMUM EXTENT PERMITTED BY LAW, THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE". WE DISCLAIM WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.',
        `IN NO EVENT SHALL ${OPERATOR}, ITS MEMBERS, MANAGERS, OR SUPPLIERS BE LIABLE FOR INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR FOR LOST PROFITS, DATA, OR GOODWILL.`,
        `OUR AGGREGATE LIABILITY FOR ANY CLAIM ARISING OUT OF THESE TERMS OR THE SERVICE SHALL NOT EXCEED THE GREATER OF (A) AMOUNTS YOU PAID US FOR THE SERVICE IN THE TWELVE (12) MONTHS BEFORE THE CLAIM, OR (B) ONE HUNDRED US DOLLARS (USD $100).`,
        'Some jurisdictions do not allow certain limitations; in those cases our liability is limited to the minimum permitted by law.',
      ],
    },
    {
      heading: '8. Indemnification',
      paragraphs: [
        'You agree to indemnify and hold harmless Webium LLC and its affiliates from claims arising out of your use of the service, your content, your violation of these Terms, or your violation of applicable law.',
      ],
    },
    {
      heading: '9. Governing law and disputes',
      paragraphs: [
        `These Terms are governed by the laws of the State of Wyoming and applicable United States federal law, without regard to conflict-of-law rules.`,
        'If you are a consumer in the European Union or United Kingdom, you may also benefit from mandatory protections of your country of residence. Nothing in these Terms limits rights that cannot be waived under EU or UK consumer law.',
        'Disputes should first be raised with us at the contact below. Where permitted, you agree to attempt informal resolution before initiating formal proceedings.',
      ],
    },
    {
      heading: '10. Changes and contact',
      paragraphs: [
        'We may update these Terms. Material changes will be posted on this page with an updated date. Continued use after changes constitutes acceptance where permitted by law.',
        `Questions: ${CONTACT_EMAIL}. Operator: ${OPERATOR}, ${OPERATOR_JURISDICTION}.`,
      ],
    },
  ],
};

const privacyEn: LegalDocumentContent = {
  title: 'Privacy Policy',
  metaDescription:
    'Privacy Policy and GDPR information for satflux.io operated by Webium LLC.',
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Draft template for review by qualified legal counsel and a Data Protection Officer where required.',
  sections: [
    {
      heading: '1. Who we are',
      paragraphs: [
        `${OPERATOR} (${OPERATOR_JURISDICTION}) is the data controller for personal data processed through ${SERVICE_NAME} unless we state otherwise in a separate data processing agreement.`,
        `${OPERATOR_ADDRESS}. EIN: ${OPERATOR_EIN}.`,
        `Privacy contact: ${PRIVACY_EMAIL}. General contact: ${CONTACT_EMAIL}.`,
      ],
    },
    {
      heading: '2. Data we collect',
      paragraphs: [
        'Account data: email, name (if provided), password hash, language preference, subscription status.',
        'Usage data: logs, IP address, browser type, pages visited, API requests (for security and operations).',
        'Merchant content: store settings, invoice and business document data you enter, including customer/contact details on issued documents.',
        'Payment metadata: BTCPay invoice identifiers and settlement status (we do not receive your on-chain private keys).',
      ],
    },
    {
      heading: '3. Purposes and legal bases (GDPR)',
      paragraphs: [
        'Providing the service - contract (Art. 6(1)(b) GDPR).',
        'Security, fraud prevention, and abuse detection - legitimate interests (Art. 6(1)(f)), balanced against your rights.',
        'Product analytics and improvement - legitimate interests or consent where required.',
        'Legal and tax compliance - legal obligation (Art. 6(1)(c)) where applicable.',
        'Marketing emails - consent where required; you may opt out anytime.',
      ],
    },
    {
      heading: '4. Processors and subprocessors',
      paragraphs: [
        'We use infrastructure and service providers (hosting, email delivery, monitoring) that process data on our instructions.',
        'BTCPay Server (self-hosted or managed instance you connect to) processes payment data under your configuration.',
        'A current list of categories of processors is available on request at the privacy contact above.',
      ],
    },
    {
      heading: '5. International transfers',
      paragraphs: [
        `${OPERATOR} is based in the United States. If you are in the EEA, UK, or Switzerland, your data may be transferred outside your region.`,
        'Where required, we rely on appropriate safeguards such as Standard Contractual Clauses and supplementary measures.',
      ],
    },
    {
      heading: '6. Retention',
      paragraphs: [
        'Account data is kept while your account is active and for a reasonable period afterward for legal, tax, and dispute resolution purposes.',
        'Issued business documents may retain buyer snapshots as described in our product documentation and data retention settings.',
        'You may request deletion subject to legal retention obligations.',
      ],
    },
    {
      heading: '7. Your rights',
      paragraphs: [
        'Depending on your location, you may have rights to access, rectify, erase, restrict, port, or object to processing of your personal data, and to withdraw consent.',
        'EEA/UK users may lodge a complaint with their local supervisory authority.',
        'California residents may have additional rights under the CCPA/CPRA where applicable.',
        `Exercise rights via ${PRIVACY_EMAIL}. We may need to verify your identity.`,
      ],
    },
    {
      heading: '8. Cookies and similar technologies',
      paragraphs: [
        'We use essential cookies for authentication (Laravel session, CSRF) and optional preferences (e.g. language).',
        'We do not use third-party advertising cookies on the authenticated app. Public pages may use privacy-friendly analytics if enabled.',
      ],
    },
    {
      heading: '9. Children',
      paragraphs: [
        'The service is not directed at children under 16. We do not knowingly collect their data.',
      ],
    },
    {
      heading: '10. Updates',
      paragraphs: [
        'We will post updates on this page with a revised date. Significant changes may be notified by email or in-app notice where appropriate.',
      ],
    },
  ],
};

const termsSk: LegalDocumentContent = {
  title: 'Obchodné podmienky',
  metaDescription:
    'Obchodné podmienky služby satflux.io prevádzkovanej spoločnosťou Webium LLC, US LLC pre obchodníkov po celom svete.',
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Návrh šablóny na revíziu kvalifikovaným právnikom. Nepoužívajte tento text ako finálne právne poradenstvo.',
  sections: [
    {
      heading: '1. Dohoda a prevádzkovateľ',
      paragraphs: [
        `Tieto obchodné podmienky ("Podmienky") upravujú prístup a používanie ${SERVICE_NAME}, ovládacieho panela pre obchodníkov s Bitcoinom a Lightning prevádzkovaného spoločnosťou ${OPERATOR}, limited liability company so sídlom v ${OPERATOR_JURISDICTION} ("my", "nás", "naše").`,
        `${OPERATOR_ADDRESS}. EIN: ${OPERATOR_EIN}.`,
        'Vytvorením účtu alebo používaním služby súhlasíte s týmito Podmienkami. Ak službu používate v mene firmy, potvrdzujete, že máte oprávnenie túto firmu zaviazať.',
      ],
    },
    {
      heading: '2. Popis služby',
      paragraphs: [
        `${SERVICE_NAME} poskytuje nástroje na správu BTCPay Server obchodov, prijímanie platieb Bitcoinom a Lightning a (v platených plánoch) vystavovanie faktúr a súvisiacich účtovných funkcií. Orchestrujeme BTCPay a pripojené peňaženky; vaše prostriedky nekustódijujeme, pokiaľ si nezvolíte kustódialnu integráciu peňaženky.`,
        'Funkcie sa môžu meniť. Beta alebo náhľadové funkcie sú poskytované tak, ako sú, a môžu byť upravené alebo stiahnuté.',
      ],
    },
    {
      heading: '3. Účty a spôsobilosť',
      paragraphs: [
        'Musíte uviesť pravdivé registračné údaje a chrániť prihlasovacie údaje. Zodpovedáte za aktivitu pod vaším účtom.',
        'Musíte mať aspoň 18 rokov (alebo vek plnoletosti vo vašej jurisdikcii) a nesmú vám používanie služby zakazovať platné právne predpisy.',
      ],
    },
    {
      heading: '4. Predplatné a platby',
      paragraphs: [
        'Platené plány (napr. Pro) sa účtujú v Bitcoine cez BTCPay checkout, pokiaľ nie je uvedené inak. Skúšobné obdobia a odkladné lehoty sú popísané na stránke cenníka a v dokumentácii.',
        'Poplatky za predplatné sú vo všeobecnosti nevratné, okrem prípadov, keď to vyžaduje kogentné spotrebiteľské právo. Zníženie plánu nadobudne účinnosť na konci aktuálneho fakturačného obdobia.',
        'Dane (vrátane DPH v EÚ, sales tax v USA alebo miestnych ekvivalentov) môžu platiť podľa vašej lokality a nášho daňového statusu. Ak nie sme registrovaní na výber dane vo vašej jurisdikcii, môžete zostať zodpovední za samovymenenie príslušných daní.',
      ],
    },
    {
      heading: '5. Prípustné používanie',
      paragraphs: [
        'Službu nesmiete používať na nezákonnú činnosť, obchádzanie sankcií, podvod, šírenie malvéru ani na porušovanie práv tretích strán.',
        'Nesmiete obchádzať zabezpečenie, pristupovať k údajom iných tenantov ani preťažovať infraštruktúru. Účty porušujúce pravidlá môžeme pozastaviť alebo ukončiť.',
      ],
    },
    {
      heading: '6. Duševné vlastníctvo',
      paragraphs: [
        'Práva k službe, značke a dokumentácii si ponechávame my. Open-source komponenty sú licencované podľa príslušných licencií (pozri náš GitHub repozitár).',
        'Práva k vašim obchodným údajom, obsahu obchodu a faktúr si ponechávate vy.',
      ],
    },
    {
      heading: '7. Obmedzenie zodpovednosti',
      paragraphs: [
        'V MAXIMÁLNOM ROZSAHU POVOLENOM ZÁKONOM JE SLUŽBA POSKYTOVANÁ "TAK, AKO JE" A "AKO JE DOSTUPNÁ". Zriekame sa záruk obchodovateľnosti, vhodnosti na konkrétny účel a neporušenia práv.',
        `V ŽIADNOM PRÍPADE NEBUDE ${OPERATOR}, JEJ ČLENOVIA, MANAŽÉRI ANI DODÁVATELIA ZODPOVEDNÍ ZA NEPRIAME, NÁHODNÉ, ŠPECIÁLNE, NÁSLEDNÉ ALEBO TRESTNÉ ŠKODY, ANI ZA UŠLÝ ZISK, ÚDAJE ALEBO GOODWILL.`,
        `NAŠA SÚHRNNÁ ZODPOVEDNOSŤ ZA AKÝKOĽVEK NÁROK VYPLÝVAJÚCI Z TÝCHTO PODMIENOK ALEBO SLUŽBY NEPREKROČÍ VÄČŠIU Z HODNÔT (A) SUMY, KTORÚ STE NÁM ZA SLUŽBU ZAPLATILI ZA 12 MESIACOV PRED NÁROKOM, ALEBO (B) STO AMERICKÝCH DOLÁROV (USD 100).`,
        'Niektoré jurisdikcie nepovoľujú určité obmedzenia; v takých prípadoch je naša zodpovednosť obmedzená na minimum povolené zákonom.',
      ],
    },
    {
      heading: '8. Odškodnenie',
      paragraphs: [
        'Súhlasíte s tým, že odškodníte a ochránite Webium LLC a pridružené subjekty pred nárokmi vyplývajúcimi z vášho používania služby, vášho obsahu, porušenia týchto Podmienok alebo platného práva.',
      ],
    },
    {
      heading: '9. Rozhodné právo a spory',
      paragraphs: [
        `Tieto Podmienky sa riadia právom štátu Wyoming a príslušným federálnym právom USA bez ohľadu na kolízne normy.`,
        'Ak ste spotrebiteľ v Európskej únii alebo Spojenom kráľovstve, môžu sa na vás vzťahovať aj kogentné ochrany vašej krajiny pobytu. Nič v týchto Podmienkach neobmedzuje práva, ktoré nie je možné podľa práva EÚ alebo UK vzdať sa.',
        'Spory najprv oznámte na kontakt nižšie. Kde je to prípustné, súhlasíte s pokusom o neformálne riešenie pred formálnym konaním.',
      ],
    },
    {
      heading: '10. Zmeny a kontakt',
      paragraphs: [
        'Podmienky môžeme aktualizovať. Podstatné zmeny zverejníme na tejto stránke s novým dátumom. Pokračovanie v používaní po zmene znamená súhlas, kde to zákon povoľuje.',
        `Otázky: ${CONTACT_EMAIL}. Prevádzkovateľ: ${OPERATOR}, ${OPERATOR_JURISDICTION}.`,
      ],
    },
  ],
};

const privacySk: LegalDocumentContent = {
  title: 'Zásady ochrany osobných údajov',
  metaDescription:
    'Zásady ochrany osobných údajov a informácie podľa GDPR pre satflux.io prevádzkované spoločnosťou Webium LLC.',
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Návrh šablóny na revíziu kvalifikovaným právnikom a prípadne poverencom pre ochranu údajov.',
  sections: [
    {
      heading: '1. Kto sme',
      paragraphs: [
        `${OPERATOR} (${OPERATOR_JURISDICTION}) je prevádzkovateľ osobných údajov spracúvaných prostredníctvom ${SERVICE_NAME}, pokiaľ v samostatnej zmluve o spracúvaní údajov neuvádzame inak.`,
        `${OPERATOR_ADDRESS}. EIN: ${OPERATOR_EIN}.`,
        `Kontakt pre ochranu údajov: ${PRIVACY_EMAIL}. Všeobecný kontakt: ${CONTACT_EMAIL}.`,
      ],
    },
    {
      heading: '2. Aké údaje spracúvame',
      paragraphs: [
        'Údaje účtu: e-mail, meno (ak je uvedené), hash hesla, jazyková preferencia, stav predplatného.',
        'Údaje o používaní: logy, IP adresa, typ prehliadača, navštívené stránky, API požiadavky (bezpečnosť a prevádzka).',
        'Obsah obchodníka: nastavenia obchodu, faktúry a obchodné dokumenty vrátane údajov zákazníkov/kontaktov na vystavených dokladoch.',
        'Metadáta platieb: identifikátory BTCPay faktúr a stav vyrovnania (neprijímame vaše on-chain súkromné kľúče).',
      ],
    },
    {
      heading: '3. Účely a právne základy (GDPR)',
      paragraphs: [
        'Poskytovanie služby - zmluva (čl. 6 ods. 1 písm. b) GDPR).',
        'Bezpečnosť, prevencia podvodov a zneužitia - oprávnený záujem (čl. 6 ods. 1 písm. f), vyvážený voči vašim právam.',
        'Analytika a zlepšovanie produktu - oprávnený záujem alebo súhlas, kde je vyžadovaný.',
        'Právne a daňové povinnosti - právna povinnosť (čl. 6 ods. 1 písm. c), kde sa uplatní.',
        'Marketingové e-maily - súhlas, kde je vyžadovaný; môžete sa kedykoľvek odhlásiť.',
      ],
    },
    {
      heading: '4. Sprostredkovatelia',
      paragraphs: [
        'Používame poskytovateľov infraštruktúry (hosting, e-mail, monitoring), ktorí spracúvajú údaje podľa našich pokynov.',
        'BTCPay Server (vlastná alebo spravovaná inštancia) spracúva platobné údaje podľa vašej konfigurácie.',
        'Aktuálny zoznam kategórií sprostredkovateľov poskytneme na požiadanie na kontakte vyššie.',
      ],
    },
    {
      heading: '5. Medzinárodné prenosy',
      paragraphs: [
        `${OPERATOR} sídli v Spojených štátoch. Ak ste v EHP, UK alebo Švajčiarsku, vaše údaje môžu byť prenesené mimo vášho regiónu.`,
        'Kde je to potrebné, spoliehame sa na vhodné záruky, napr. štandardné zmluvné doložky a doplňujúce opatrenia.',
      ],
    },
    {
      heading: '6. Uchovávanie',
      paragraphs: [
        'Údaje účtu uchovávame počas aktivity účtu a primeranú dobu potom pre právne, daňové a sporné účely.',
        'Vystavené obchodné dokumenty môžu uchovávať snapshoty kupujúceho podľa dokumentácie produktu a nastavení retencie.',
        'Môžete požiadať o vymazanie s ohľadom na zákonné povinnosti uchovávania.',
      ],
    },
    {
      heading: '7. Vaše práva',
      paragraphs: [
        'Podľa lokality môžete mať právo na prístup, opravu, vymazanie, obmedzenie, prenosnosť, námietku a odvolanie súhlasu.',
        'Používatelia v EHP/UK môžu podať sťažnosť u dozorného úradu.',
        'Obyvatelia Kalifornie môžu mať ďalšie práva podľa CCPA/CPRA, kde sa uplatnia.',
        `Práva uplatňujte na ${PRIVACY_EMAIL}. Môžeme overiť vašu totožnosť.`,
      ],
    },
    {
      heading: '8. Cookies',
      paragraphs: [
        'Používame nevyhnutné cookies pre autentifikáciu (Laravel session, CSRF) a voliteľné preferencie (napr. jazyk).',
        'V prihlásenej aplikácii nepoužívame reklamné cookies tretích strán. Verejné stránky môžu používať privacy-friendly analytiku, ak je zapnutá.',
      ],
    },
    {
      heading: '9. Deti',
      paragraphs: [
        'Služba nie je určená deťom mladším ako 16 rokov. Vedome ich údaje nezhromažďujeme.',
      ],
    },
    {
      heading: '10. Aktualizácie',
      paragraphs: [
        'Zmeny zverejníme na tejto stránke s novým dátumom. Významné zmeny môžeme oznámiť e-mailom alebo v aplikácii.',
      ],
    },
  ],
};

const termsEs: LegalDocumentContent = {
  ...termsEn,
  title: 'Términos del servicio',
  metaDescription:
    'Términos del servicio de satflux.io operado por Webium LLC, una LLC estadounidense.',
  draftNotice:
    'Borrador para revisión por asesoría legal cualificada. No use este texto como asesoramiento legal definitivo.',
  sections: termsEn.sections.map((section, index) => {
    if (index === 0) {
      return {
        heading: '1. Acuerdo y operador',
        paragraphs: [
          `Estos Términos del servicio ("Términos") regulan el acceso y uso de ${SERVICE_NAME}, el panel de control para comerciantes Bitcoin y Lightning operado por ${OPERATOR}, una limited liability company constituida en ${OPERATOR_JURISDICTION} ("nosotros").`,
          'Al crear una cuenta o usar el servicio, acepta estos Términos. Si actúa en nombre de una empresa, declara tener autoridad para vincularla.',
        ],
      };
    }
    return section;
  }),
};

const privacyEs: LegalDocumentContent = {
  ...privacyEn,
  title: 'Política de privacidad',
  metaDescription:
    'Política de privacidad e información GDPR para satflux.io operado por Webium LLC.',
  draftNotice:
    'Borrador para revisión por asesoría legal y, si aplica, delegado de protección de datos.',
};

const catalog: Record<LegalLocale, Record<LegalDocId, LegalDocumentContent>> = {
  en: { terms: termsEn, privacy: privacyEn, imprint: imprintEn, dpa: dpaEn },
  sk: { terms: termsSk, privacy: privacySk, imprint: imprintSk, dpa: dpaSk },
  es: { terms: termsEs, privacy: privacyEs, imprint: imprintEs, dpa: dpaEs },
};

export function getLegalDocument(
  docId: LegalDocId,
  locale: string,
): LegalDocumentContent {
  const loc = (locale in catalog ? locale : 'en') as LegalLocale;
  return catalog[loc][docId] ?? catalog.en[docId];
}

export const LEGAL_NAV: { id: LegalDocId; path: string; labelKey: string }[] = [
  { id: 'terms', path: '/legal/terms', labelKey: 'legal.nav.terms' },
  { id: 'privacy', path: '/legal/privacy', labelKey: 'legal.nav.privacy' },
  { id: 'imprint', path: '/legal/imprint', labelKey: 'legal.nav.imprint' },
  { id: 'dpa', path: '/legal/dpa', labelKey: 'legal.nav.dpa' },
];
