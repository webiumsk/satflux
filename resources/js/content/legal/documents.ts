import type { LegalDocId, LegalDocumentContent, LegalLocale } from './types';
import {
  LEGAL_OPERATOR,
  gdprRepresentativeBlockEu,
  gdprRepresentativeBlockUk,
  operatorAddressBlock,
} from './operator';
import { dpaEn, dpaEs, dpaSk, imprintEn, imprintEs, imprintSk } from './imprintDpa';

const OPERATOR = LEGAL_OPERATOR.name;
const OPERATOR_ADDRESS = operatorAddressBlock();
const OPERATOR_JURISDICTION = LEGAL_OPERATOR.jurisdiction;
const OPERATOR_EIN = LEGAL_OPERATOR.ein;
const SERVICE_NAME = LEGAL_OPERATOR.serviceName;
const CONTACT_EMAIL = LEGAL_OPERATOR.contactEmail;
const PRIVACY_EMAIL = LEGAL_OPERATOR.privacyEmail;
const LAST_UPDATED = '2026-06-15';

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
        `2.1 What we provide. ${SERVICE_NAME} provides software tools to deploy and manage BTCPay Server stores, generate Bitcoin and Lightning payment requests, monitor settlement status, and (on paid plans) issue business invoices and related accounting features. We host the BTCPay Server software infrastructure on your behalf and orchestrate connections to wallets you choose. Features may change. Beta or preview features are provided as-is and may be modified or withdrawn.`,
        '2.2 Non-custodial by design. SatFlux is a non-custodial software service. We never hold, take possession of, pool, or have the ability to spend, transfer, or withdraw your funds, and we never hold or have access to your private keys or wallet seed. Bitcoin and Lightning payments flow directly to the wallet you connect; they never pass through us as value.',
        '2.3 Wallet connections. Where you connect a third-party wallet provider (for example, Blink via a receive/read-scoped API credential), any custody of funds is solely a relationship between you and that provider. We are not a party to that relationship and assume no responsibility for it, including for that provider\'s availability, security, or solvency. Any API credential you supply is used only to generate payment requests and read settlement status; our systems have no technical capability to withdraw or move your funds. Where you use a self-custodial wallet (for example, Aqua), you retain full control of your keys and funds at all times.',
        '2.4 Software only; not a financial service. We provide software tools only. We are not a money services business, money transmitter, payment processor, payment institution, exchange, broker, or virtual asset service provider, and we do not provide custody, exchange, fiat on-ramp or off-ramp, or stablecoin services. We do not transmit, convert, or take possession of funds or virtual assets on your behalf.',
        '2.5 Your responsibilities. You are solely responsible for your own legal, regulatory, tax, accounting, and anti-money-laundering obligations arising from your acceptance of Bitcoin or Lightning payments, including any licensing, reporting, or record-keeping required in your jurisdiction. Nothing in the service constitutes financial, legal, or tax advice.',
        '2.6 Local-first business invoicing (Pro). Where enabled, your business invoicing register (company profiles, customer contacts, invoices, expenses, and related settings) is stored primarily on your devices using end-to-end encrypted sync via a relay operator. SatFlux does not persist that register in our application database. When you use server-assisted features (for example PDF export, outbound email, structured export, or BTCPay checkout helpers), document data is transmitted only for the duration of that request and is not stored by us as issued business documents. You are responsible for safeguarding your recovery phrase and device backups.',
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
      heading: '5a. Sanctions, export controls, and prohibited jurisdictions',
      paragraphs: [
        '5a.1 Compliance. You must comply with all applicable economic sanctions and export-control laws, including those administered by the US Office of Foreign Assets Control (OFAC), the US Department of Commerce, the European Union, and the United Nations.',
        '5a.2 Your representations. You represent and warrant that you, and any person on whose behalf you use the service, are not: (a) located in, organized under the laws of, or ordinarily resident in any country or territory subject to comprehensive sanctions (including, without limitation, Cuba, Iran, North Korea, Syria, and the Crimea, Donetsk, and Luhansk regions of Ukraine); (b) identified on any sanctions or restricted-party list, including the OFAC Specially Designated Nationals (SDN) List, the EU Consolidated Sanctions List, or any equivalent list; or (c) owned or controlled by, or acting on behalf of, any such person.',
        '5a.3 No prohibited use. You will not use the service to facilitate any transaction involving a sanctioned person, sanctioned jurisdiction, or otherwise prohibited activity, and you will not use the service to evade or circumvent any sanctions or export-control law.',
        '5a.4 Screening and enforcement. We may screen accounts, registration data, and connection metadata against sanctions and restricted-party lists, and may use third-party screening providers for this purpose. We may refuse, restrict, suspend, or terminate access at any time, without liability, where we reasonably believe such action is necessary to comply with sanctions or export-control laws.',
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
      heading: '1a. EU/UK representative (GDPR Article 27)',
      paragraphs: [
        'As we are established outside the European Union and the United Kingdom but offer services to data subjects in those regions, we have appointed representatives under Article 27 of the EU GDPR and the UK GDPR.',
        gdprRepresentativeBlockEu(),
        gdprRepresentativeBlockUk(),
        'You may contact our representative(s) on any matter relating to our processing of your personal data, in addition to or instead of contacting us directly.',
      ],
    },
    {
      heading: '2. Data we collect',
      paragraphs: [
        'Account data: email (including aliases used for registration), optional name, password hash, language preference, subscription status, and a recovery public key for seed-first accounts.',
        'Usage data: logs, IP address, browser type, pages visited, and API requests (for security and operations).',
        'BTCPay Stores: store settings and payment invoice metadata (we do not receive your wallet private keys or seed).',
        'Local-first business invoicing (Pro): company profiles, customer contacts, issued invoices, expenses, and related settings are stored primarily in your browser and synchronized via end-to-end encryption through a relay operator. SatFlux does not store this invoicing register in our application database.',
        'Ephemeral server processing: when you request PDF export, outbound email, structured file export, BTCPay checkout helpers, or similar features, relevant document data is sent to our servers only for the duration of that request and is not persisted as business documents afterward.',
        'WooCommerce integration (if enabled): new order payloads may be queued briefly on our servers until you import them into local invoicing.',
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
        'We use infrastructure and service providers that process data on our instructions.',
        'Infrastructure providers include hosting (Hetzner, EU), email delivery, and monitoring.',
        'BTCPay Server: we host and operate the BTCPay Server software infrastructure on your behalf. It processes payment metadata (such as invoice identifiers and settlement status) under your store configuration. We never receive, hold, or have access to your private keys, wallet seed, or funds.',
        'E2EE sync relay (for example Evolu): encrypted payloads for local-first invoicing sync between your devices. SatFlux does not receive plaintext invoicing content from the relay; the relay operator processes encrypted blobs under its own terms.',
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
        'Local-first invoicing content is not retained on SatFlux servers after ephemeral processing completes.',
        'Legacy server-mode invoicing data created before local-first rollout, if any remains on your account, may retain buyer snapshots as described in our product documentation until you delete it.',
        'Ephemeral bridge metadata (for example BTCPay checkout linkage or e-faktura submission status) is kept only as long as needed for the feature.',
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
        'When the Chorala feedback widget is enabled, it may set a functional cookie to attribute votes and submissions to your session.',
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
        `2.1 Čo poskytujeme. ${SERVICE_NAME} poskytuje softvérové nástroje na nasadenie a správu obchodov BTCPay Server, generovanie platobných požiadaviek Bitcoin a Lightning, sledovanie stavu vyrovnania a (v platených plánoch) vystavovanie obchodných faktúr a súvisiacich účtovných funkcií. Softvérovú infraštruktúru BTCPay Server hostujeme vo vašom mene a orchestrujeme pripojenia k peňaženkám, ktoré si zvolíte. Funkcie sa môžu meniť. Beta alebo náhľadové funkcie sú poskytované tak, ako sú, a môžu byť upravené alebo stiahnuté.`,
        '2.2 Nekustódialny dizajn. SatFlux je nekustódialna softvérová služba. Nikdy nedržíme, nepreberáme, nepoolujeme ani nemáme možnosť míňať, prevádzať alebo vyberať vaše prostriedky a nikdy nedržíme ani nemáme prístup k vašim súkromným kľúčom alebo seedu peňaženky. Platby Bitcoinom a Lightning smerujú priamo do peňaženky, ktorú pripojíte; ako hodnota cez nás nikdy neprechádzajú.',
        '2.3 Pripojenia peňaženiek. Ak pripojíte poskytovateľa peňaženky tretej strany (napríklad Blink cez API credential s rozsahom receive/read), akákoľvek úschova prostriedkov je výhradne vzťahom medzi vami a týmto poskytovateľom. Nie sme stranou tohto vzťahu a nepreberáme zaň zodpovednosť, vrátane dostupnosti, bezpečnosti alebo solventnosti tohto poskytovateľa. Akýkoľvek API credential, ktorý poskytnete, používame len na generovanie platobných požiadaviek a čítanie stavu vyrovnania; naše systémy nemajú technickú možnosť vyberať ani presúvať vaše prostriedky. Pri samosprávnej (self-custodial) peňaženke (napríklad Aqua) máte vždy plnú kontrolu nad kľúčmi a prostriedkami.',
        '2.4 Iba softvér; nie finančná služba. Poskytujeme výhradne softvérové nástroje. Nie sme money services business, prevodca peňazí, payment processor, platobná inštitúcia, burza, broker ani poskytovateľ služieb s virtuálnymi aktívami a neposkytujeme úschovu, zmenáreň, fiat on-ramp ani off-ramp ani služby so stablecoinmi. Vo vašom mene neprenášame, nekonvertujeme ani nepreberáme prostriedky alebo virtuálne aktíva.',
        '2.5 Vaše povinnosti. Sami zodpovedáte za svoje právne, regulačné, daňové, účtovné a povinnosti v oblasti boja proti praniu špinavých peňazí vyplývajúce z prijímania platieb Bitcoinom alebo Lightning, vrátane licencií, výkazníctva alebo vedenia záznamov požadovaných vo vašej jurisdikcii. Nič v službe nepredstavuje finančné, právne ani daňové poradenstvo.',
        '2.6 Local-first fakturácia (Pro). Ak je zapnutá, váš fakturačný register (profily firiem, kontakty odberateľov, faktúry, náklady a súvisiace nastavenia) sa ukladá primárne vo vašich zariadeniach s end-to-end šifrovanou synchronizáciou cez relay operátora. SatFlux tento register v našej aplikačnej databáze neukladá. Pri serverovo asistovaných funkciách (napr. export PDF, odoslanie e-mailu, štruktúrovaný export alebo BTCPay checkout) sa údaje dokladu prenášajú len na dobu spracovania požiadavky a neukladáme ich ako vystavené obchodné dokumenty. Za ochranu recovery frázy a záloh zariadení zodpovedáte vy.',
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
      heading: '5a. Sankcie, kontrola vývozu a zakázané jurisdikcie',
      paragraphs: [
        '5a.1 Súlad. Musíte dodržiavať všetky platné zákony o ekonomických sankciách a kontrole vývozu, vrátane predpisov spravovaných Úradom pre kontrolu zahraničných aktív USA (OFAC), Ministerstvom obchodu USA, Európskou úniou a Organizáciou Spojených národov.',
        '5a.2 Vaše vyhlásenia. Vyhlasujete a zaručujete, že vy a každá osoba, v mene ktorej službu používate, nie ste: (a) so sídlom, založený podľa právnych predpisov alebo obvykle pobývajúci v krajine alebo na území podliehajúcom komplexným sankciám (vrátane bez obmedzenia Kuby, Iránu, Severnej Kórey, Sýrie a regiónov Krym, Doneck a Luhansk na Ukrajine); (b) uvedení na žiadnom sankčnom alebo zozname obmedzených subjektov, vrátane zoznamu OFAC Specially Designated Nationals (SDN), konsolidovaného zoznamu sankcií EÚ alebo ekvivalentného zoznamu; alebo (c) vlastnení alebo kontrolovaní takou osobou, alebo konajúci v jej mene.',
        '5a.3 Zakázané použitie. Službu nebudete používať na uľahčovanie transakcií so sankcionovanou osobou, sankcionovanou jurisdikciou alebo inak zakázanej činnosti a nebudete ju používať na obchádzanie sankčných alebo vývozných predpisov.',
        '5a.4 Kontrola a vymáhanie. Môžeme kontrolovať účty, registračné údaje a metadáta pripojenia voči sankčným a zoznamom obmedzených subjektov a na tento účel môžeme využívať poskytovateľov kontroly tretích strán. Prístup môžeme kedykoľvek odmietnuť, obmedziť, pozastaviť alebo ukončiť bez zodpovednosti, ak máme dôvodne za to, že je to potrebné na dodržanie sankčných alebo vývozných predpisov.',
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
      heading: '1a. Zástupca pre EÚ/UK (čl. 27 GDPR)',
      paragraphs: [
        'Keďže sme založení mimo Európskej únie a Spojeného kráľovstva, ale ponúkame služby dotknutým osobám v týchto regiónoch, určili sme zástupcov podľa článku 27 nariadenia GDPR EÚ a GDPR Spojeného kráľovstva.',
        gdprRepresentativeBlockEu(),
        gdprRepresentativeBlockUk(),
        'V záležitostiach týkajúcich sa spracúvania vašich osobných údajov môžete kontaktovať našich zástupcov navyše alebo namiesto priameho kontaktu s nami.',
      ],
    },
    {
      heading: '2. Aké údaje spracúvame',
      paragraphs: [
        'Údaje účtu: e-mail (vrátane aliasov použitých pri registrácii), voliteľné meno, hash hesla, jazyková preferencia, stav predplatného a verejný kľúč recovery pre seed-first účty.',
        'Údaje o používaní: logy, IP adresa, typ prehliadača, navštívené stránky a API požiadavky (bezpečnosť a prevádzka).',
        'BTCPay obchody: nastavenia obchodu a metadáta platobných faktúr (neprijímame súkromné kľúče ani seed peňaženky).',
        'Local-first fakturácia (Pro): profily firiem, kontakty odberateľov, vystavené faktúry, náklady a súvisiace nastavenia sa ukladajú primárne vo vašom prehliadači a synchronizujú sa end-to-end šifrovaním cez relay operátora. SatFlux tento fakturačný register v našej aplikačnej databáze neukladá.',
        'Ephemerálne serverové spracovanie: pri exporte PDF, odoslaní e-mailu, štruktúrovanom exporte, BTCPay checkout a podobných funkciách sa relevantné údaje dokladu pošlú na server len na dobu spracovania požiadavky a potom sa u nás neukladajú ako obchodné dokumenty.',
        'WooCommerce integrácia (ak je zapnutá): payloady nových objednávok môžu krátko čakať na serveri, kým ich neimportujete do lokálnej fakturácie.',
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
        'Používame poskytovateľov infraštruktúry a služieb, ktorí spracúvajú údaje podľa našich pokynov.',
        'Medzi infraštruktúrnych poskytovateľov patrí hosting (Hetzner, EÚ), doručovanie e-mailov a monitoring.',
        'BTCPay Server: softvérovú infraštruktúru BTCPay Server hostujeme a prevádzkujeme vo vašom mene. Spracúva metadáta platieb (napríklad identifikátory faktúr a stav vyrovnania) podľa konfigurácie vášho obchodu. Nikdy neprijímame, nedržíme ani nemáme prístup k vašim súkromným kľúčom, seedu peňaženky ani prostriedkom.',
        'E2EE sync relay (napríklad Evolu): šifrované payloady pre synchronizáciu local-first fakturácie medzi zariadeniami. SatFlux od relay operátora neprijíma čitateľný obsah faktúr v plaintexte; relay spracúva šifrované bloby podľa vlastných podmienok.',
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
        'Obsah local-first fakturácie sa na serveroch SatFlux po dokončení ephemerálneho spracovania neuchováva.',
        'Legacy fakturačné údaje zo serverového režimu pred nasadením local-first, ak na účte zostali, môžu obsahovať snapshoty kupujúceho podľa dokumentácie, kým ich nevymažete.',
        'Metadáta ephemerálneho mostu (napr. väzba BTCPay checkout alebo stav odoslania e-faktúry) uchovávame len po dobu potrebnú pre danú funkciu.',
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
        'Ak je zapnutý feedback widget Chorala, môže nastaviť funkčný cookie na priradenie hlasov a podnetov k vašej relácii.',
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
    if (index === 1) {
      return {
        heading: '2. Descripción del servicio',
        paragraphs: [
          `2.1 Qué proporcionamos. ${SERVICE_NAME} ofrece herramientas de software para desplegar y gestionar tiendas BTCPay Server, generar solicitudes de pago Bitcoin y Lightning, supervisar el estado de liquidación y (en planes de pago) emitir facturas comerciales y funciones contables relacionadas. Alojamos la infraestructura de software BTCPay Server en su nombre y orquestamos las conexiones a las carteras que elija. Las funciones pueden cambiar. Las funciones beta o de vista previa se proporcionan tal cual y pueden modificarse o retirarse.`,
          '2.2 No custodial por diseño. SatFlux es un servicio de software no custodial. Nunca retenemos, tomamos posesión, agrupamos ni tenemos la capacidad de gastar, transferir o retirar sus fondos, y nunca retenemos ni tenemos acceso a sus claves privadas o semilla de cartera. Los pagos Bitcoin y Lightning fluyen directamente a la cartera que conecte; nunca pasan por nosotros como valor.',
          '2.3 Conexiones de cartera. Cuando conecta un proveedor de cartera de terceros (por ejemplo, Blink mediante una credencial API de alcance receive/read), cualquier custodia de fondos es exclusivamente una relación entre usted y ese proveedor. No somos parte de esa relación ni asumimos responsabilidad por ella, incluida la disponibilidad, seguridad o solvencia de dicho proveedor. Cualquier credencial API que proporcione se usa solo para generar solicitudes de pago y leer el estado de liquidación; nuestros sistemas no tienen capacidad técnica para retirar o mover sus fondos. Si usa una cartera de autocustodia (por ejemplo, Aqua), conserva el control total de sus claves y fondos en todo momento.',
          '2.4 Solo software; no es un servicio financiero. Proporcionamos únicamente herramientas de software. No somos una empresa de servicios monetarios, transmisor de dinero, procesador de pagos, institución de pago, exchange, bróker ni proveedor de servicios de activos virtuales, y no ofrecemos custodia, cambio, rampas fiat de entrada o salida ni servicios de stablecoin. No transmitimos, convertimos ni tomamos posesión de fondos o activos virtuales en su nombre.',
          '2.5 Sus responsabilidades. Usted es el único responsable de sus obligaciones legales, regulatorias, fiscales, contables y de prevención del blanqueo de capitales derivadas de aceptar pagos Bitcoin o Lightning, incluidas licencias, informes o registros exigidos en su jurisdicción. Nada en el servicio constituye asesoramiento financiero, legal o fiscal.',
          '2.6 Facturación local-first (Pro). Cuando está activada, su registro de facturación (perfiles de empresa, contactos de clientes, facturas, gastos y ajustes relacionados) se almacena principalmente en sus dispositivos con sincronización cifrada de extremo a extremo mediante un operador relay. SatFlux no persiste ese registro en nuestra base de datos de aplicación. Al usar funciones asistidas por el servidor (por ejemplo exportación PDF, envío de correo, exportación estructurada o checkout BTCPay), los datos del documento se transmiten solo durante esa solicitud y no se almacenan como documentos comerciales emitidos. Usted es responsable de proteger su frase de recuperación y las copias de seguridad del dispositivo.',
        ],
      };
    }
    if (index === 5) {
      return {
        heading: '5a. Sanciones, controles de exportación y jurisdicciones prohibidas',
        paragraphs: [
          '5a.1 Cumplimiento. Debe cumplir todas las leyes aplicables de sanciones económicas y control de exportaciones, incluidas las administradas por la Oficina de Control de Activos Extranjeros de EE. UU. (OFAC), el Departamento de Comercio de EE. UU., la Unión Europea y las Naciones Unidas.',
          '5a.2 Sus declaraciones. Usted declara y garantiza que usted y cualquier persona en cuyo nombre use el servicio no están: (a) ubicados, constituidos conforme a las leyes de, o con residencia habitual en, ningún país o territorio sujeto a sanciones integrales (incluidos, sin limitación, Cuba, Irán, Corea del Norte, Siria y las regiones de Crimea, Donetsk y Luhansk de Ucrania); (b) identificados en ninguna lista de sanciones o partes restringidas, incluida la lista SDN de OFAC, la lista consolidada de sanciones de la UE o cualquier lista equivalente; o (c) propiedad o controlados por, o actuando en nombre de, tal persona.',
          '5a.3 Uso prohibido. No usará el servicio para facilitar ninguna transacción que implique a una persona sancionada, jurisdicción sancionada u otra actividad prohibida, ni para eludir o sortear ninguna ley de sanciones o control de exportaciones.',
          '5a.4 Control y aplicación. Podemos revisar cuentas, datos de registro y metadatos de conexión frente a listas de sanciones y partes restringidas, y podemos usar proveedores de control de terceros para ello. Podemos rechazar, restringir, suspender o terminar el acceso en cualquier momento, sin responsabilidad, cuando razonablemente creamos que dicha acción es necesaria para cumplir las leyes de sanciones o control de exportaciones.',
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
  sections: privacyEn.sections.map((section, index) => {
    if (index === 1) {
      return {
        heading: '1a. Representante UE/Reino Unido (artículo 27 GDPR)',
        paragraphs: [
          'Como estamos establecidos fuera de la Unión Europea y del Reino Unido pero ofrecemos servicios a interesados en esas regiones, hemos designado representantes conforme al artículo 27 del GDPR de la UE y del Reino Unido.',
          gdprRepresentativeBlockEu(),
          gdprRepresentativeBlockUk(),
          'Puede contactar a nuestro(s) representante(s) sobre cualquier asunto relacionado con el tratamiento de sus datos personales, además de o en lugar de contactarnos directamente.',
        ],
      };
    }
    if (index === 2) {
      return {
        heading: '2. Datos que recopilamos',
        paragraphs: [
          'Datos de cuenta: correo electrónico (incluidos alias usados en el registro), nombre opcional, hash de contraseña, preferencia de idioma, estado de suscripción y clave pública de recuperación para cuentas seed-first.',
          'Datos de uso: registros, dirección IP, tipo de navegador, páginas visitadas y solicitudes API (seguridad y operaciones).',
          'Tiendas BTCPay: ajustes de tienda y metadatos de facturas de pago (no recibimos sus claves privadas ni la semilla de la cartera).',
          'Facturación local-first (Pro): perfiles de empresa, contactos de clientes, facturas emitidas, gastos y ajustes relacionados se almacenan principalmente en su navegador y se sincronizan con cifrado de extremo a extremo mediante un operador relay. SatFlux no almacena este registro de facturación en nuestra base de datos de aplicación.',
          'Procesamiento efímero en el servidor: al exportar PDF, enviar correo, exportar archivos estructurados, checkout BTCPay u otras funciones similares, los datos relevantes se envían al servidor solo durante esa solicitud y no se conservan como documentos comerciales.',
          'Integración WooCommerce (si está activada): los payloads de pedidos nuevos pueden quedar brevemente en cola en nuestros servidores hasta que los importe a la facturación local.',
        ],
      };
    }
    if (index === 4) {
      return {
        heading: '4. Encargados y subencargados del tratamiento',
        paragraphs: [
          'Utilizamos proveedores de infraestructura y servicios que tratan datos siguiendo nuestras instrucciones.',
          'Los proveedores de infraestructura incluyen hosting (Hetzner, UE), entrega de correo electrónico y monitorización.',
          'BTCPay Server: alojamos y operamos la infraestructura de software BTCPay Server en su nombre. Procesa metadatos de pago (como identificadores de factura y estado de liquidación) según la configuración de su tienda. Nunca recibimos, retenemos ni tenemos acceso a sus claves privadas, semilla de cartera ni fondos.',
          'Relay de sincronización E2EE (por ejemplo Evolu): cargas cifradas para sincronizar la facturación local-first entre sus dispositivos. SatFlux no recibe el contenido de facturación en texto claro desde el relay; el operador del relay procesa blobs cifrados según sus propios términos.',
          'Una lista actualizada de categorías de encargados está disponible previa solicitud en el contacto de privacidad indicado arriba.',
        ],
      };
    }
    if (index === 6) {
      return {
        heading: '6. Conservación',
        paragraphs: [
          'Los datos de cuenta se conservan mientras su cuenta esté activa y durante un período razonable después para fines legales, fiscales y de resolución de disputas.',
          'El contenido de facturación local-first no se conserva en los servidores de SatFlux una vez finalizado el procesamiento efímero.',
          'Los datos de facturación legacy del modo servidor anteriores al despliegue local-first, si permanecen en su cuenta, pueden conservar instantáneas del comprador según la documentación hasta que los elimine.',
          'Los metadatos del puente efímero (por ejemplo vinculación de checkout BTCPay o estado de envío de e-factura) se conservan solo el tiempo necesario para la función.',
          'Puede solicitar la eliminación sujeta a obligaciones legales de conservación.',
        ],
      };
    }
    return section;
  }),
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
