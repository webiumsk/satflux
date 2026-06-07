import { LEGAL_OPERATOR, operatorAddressBlock } from './operator';
import type { LegalDocumentContent } from './types';

const LAST_UPDATED = '2026-06-02';
const { name, jurisdiction, ein, serviceName, contactEmail, privacyEmail } = LEGAL_OPERATOR;
const address = operatorAddressBlock();

export const imprintEn: LegalDocumentContent = {
  title: 'Imprint',
  metaDescription: `Legal notice and operator details for ${serviceName}.`,
  lastUpdated: LAST_UPDATED,
  draftNotice: '',
  sections: [
    {
      heading: 'Service operator',
      paragraphs: [
        address,
        `United States / ${jurisdiction}`,
        `EIN: ${ein}`,
      ],
    },
    {
      heading: 'Contact',
      paragraphs: [
        `General: ${contactEmail}`,
        `Privacy: ${privacyEmail}`,
        `Support: ${LEGAL_OPERATOR.supportEmail}`,
      ],
    },
    {
      heading: 'Responsible for content',
      paragraphs: [
        `${name} is responsible for content on ${serviceName} unless otherwise stated for user-generated merchant content.`,
      ],
    },
    {
      heading: 'Dispute resolution',
      paragraphs: [
        'The European Commission provides an OS platform for online dispute resolution (ODR): https://ec.europa.eu/consumers/odr/',
        'We are not obliged to participate in consumer arbitration boards unless required by mandatory law.',
      ],
    },
    {
      heading: 'Liability notice',
      paragraphs: [
        'We carefully check external links at the time of linking. We are not liable for external content; the respective provider is responsible.',
      ],
    },
  ],
};

export const imprintSk: LegalDocumentContent = {
  title: 'Impressum / Identifikačné údaje',
  metaDescription: `Právne informácie a údaje prevádzkovateľa ${serviceName}.`,
  lastUpdated: LAST_UPDATED,
  draftNotice: '',
  sections: [
    {
      heading: 'Prevádzkovateľ služby',
      paragraphs: [
        address,
        `Spojené štáty / ${jurisdiction}`,
        `EIN: ${ein}`,
      ],
    },
    {
      heading: 'Kontakt',
      paragraphs: [
        `Všeobecné: ${contactEmail}`,
        `Ochrana údajov: ${privacyEmail}`,
        `Podpora: ${LEGAL_OPERATOR.supportEmail}`,
      ],
    },
    {
      heading: 'Zodpovednosť za obsah',
      paragraphs: [
        `${name} zodpovedá za obsah na ${serviceName}, pokiaľ nejde o obsah vložený používateľmi-obchodníkmi.`,
      ],
    },
    {
      heading: 'Riešenie sporov',
      paragraphs: [
        'Európska komisia poskytuje platformu ODR: https://ec.europa.eu/consumers/odr/',
        'Nie sme povinní sa zúčastňovať spotrebiteľského arbitráže, pokiaľ to nevyžaduje kogentný zákon.',
      ],
    },
    {
      heading: 'Upozornenie na zodpovednosť',
      paragraphs: [
        'Externé odkazy kontrolujeme pri vložení. Za obsah externých stránok zodpovedajú ich prevádzkovatelia.',
      ],
    },
  ],
};

const dpaBodyEn = (customerLabel: string) => [
  {
    heading: '1. Parties and scope',
    paragraphs: [
      `This Data Processing Agreement ("DPA") forms part of the agreement between ${customerLabel} ("Customer", data controller) and ${name} ("Processor") for ${serviceName} Enterprise or custom B2B services.`,
      'It applies when Processor processes personal data on behalf of Customer in the course of providing the service.',
    ],
  },
  {
    heading: '2. Subject matter and duration',
    paragraphs: [
      'Processing covers account, store, payment metadata, and merchant-entered customer data as configured by Customer.',
      'Processing lasts for the term of the main service agreement and any statutory retention period thereafter.',
    ],
  },
  {
    heading: '3. Processor obligations',
    paragraphs: [
      'Processor shall process personal data only on documented instructions from Customer, including regarding transfers.',
      'Processor implements appropriate technical and organizational measures (Art. 32 GDPR).',
      'Processor assists Customer with data subject requests and DPIAs where reasonably possible.',
      'Processor notifies Customer without undue delay after becoming aware of a personal data breach.',
    ],
  },
  {
    heading: '4. Sub-processors',
    paragraphs: [
      'Customer authorizes Processor to engage sub-processors for hosting, email, monitoring, and BTCPay infrastructure.',
      `Processor maintains a list of sub-processor categories available on request at ${privacyEmail}.`,
      'Processor will inform Customer of intended changes to sub-processors with reasonable notice.',
    ],
  },
  {
    heading: '5. International transfers',
    paragraphs: [
      `Processor is established in the United States (${jurisdiction}). Where EU/UK data is transferred, Standard Contractual Clauses and supplementary measures apply.`,
    ],
  },
  {
    heading: '6. Deletion and return',
    paragraphs: [
      'Upon termination, Processor deletes or returns personal data per Customer instructions, subject to legal retention obligations.',
      'Backups may persist for a limited technical window before automatic purge.',
    ],
  },
  {
    heading: '7. Audit',
    paragraphs: [
      'Customer may request reasonable information to demonstrate compliance. On-site audits require 30 days notice and occur at most once per year unless mandated by a supervisory authority.',
    ],
  },
  {
    heading: '8. Contact',
    paragraphs: [
      `DPA and privacy questions: ${privacyEmail}. Operator address: ${address.replace(/\n/g, ', ')}.`,
    ],
  },
];

export const dpaEn: LegalDocumentContent = {
  title: 'Data Processing Agreement (DPA)',
  metaDescription: `GDPR Data Processing Agreement template for ${serviceName} Enterprise customers.`,
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Template for Enterprise B2B customers. Execute a signed order or MSA referencing this DPA where required.',
  sections: dpaBodyEn('the merchant organization'),
};

export const dpaSk: LegalDocumentContent = {
  title: 'Zmluva o spracúvaní osobných údajov (DPA)',
  metaDescription: `Šablóna DPA podľa GDPR pre Enterprise zákazníkov ${serviceName}.`,
  lastUpdated: LAST_UPDATED,
  draftNotice:
    'Šablóna pre Enterprise B2B zákazníkov. Pri potrebe uzavrite objednávku alebo MSA s odkazom na túto DPA.',
  sections: [
    {
      heading: '1. Zmluvné strany a rozsah',
      paragraphs: [
        `Táto zmluva o spracúvaní osobných údajov ("DPA") je súčasťou dohody medzi organizáciou zákazníka ("zákazník", prevádzkovateľ) a ${name} ("sprostredkovateľ") pre ${serviceName} Enterprise alebo custom B2B služby.`,
        'Uplatní sa, keď sprostredkovateľ spracúva osobné údaje v mene zákazníka pri poskytovaní služby.',
      ],
    },
    {
      heading: '2. Predmet a trvanie',
      paragraphs: [
        'Spracúvanie zahŕňa účet, obchod, metadáta platieb a údaje zákazníkov vložené obchodníkom podľa konfigurácie zákazníka.',
        'Spracúvanie trvá po dobu hlavnej zmluvy a prípadného zákonného obdobia uchovávania.',
      ],
    },
    {
      heading: '3. Povinnosti sprostredkovateľa',
      paragraphs: [
        'Sprostredkovateľ spracúva osobné údaje len podľa dokumentovaných pokynov zákazníka, vrátane prenosov.',
        'Sprostredkovateľ zavádza primerané technické a organizačné opatrenia (čl. 32 GDPR).',
        'Sprostredkovateľ primerane pomáha so žiadosťami dotknutých osôb a DPIA.',
        'Sprostredkovateľ bez zbytočného odkladu informuje zákazníka o porušení ochrany osobných údajov.',
      ],
    },
    {
      heading: '4. Sub-sprostredkovatelia',
      paragraphs: [
        'Zákazník oprávňuje sprostredkovateľa na sub-sprostredkovateľov pre hosting, e-mail, monitoring a BTCPay infraštruktúru.',
        `Zoznam kategórií sub-sprostredkovateľov je na požiadanie na ${privacyEmail}.`,
        'O plánovaných zmenách sub-sprostredkovateľov informujeme s primeraným predstihom.',
      ],
    },
    {
      heading: '5. Medzinárodné prenosy',
      paragraphs: [
        `Sprostredkovateľ sídli v USA (${jurisdiction}). Pri prenose údajov z EÚ/UK sa uplatnia štandardné zmluvné doložky a doplňujúce opatrenia.`,
      ],
    },
    {
      heading: '6. Vymazanie a vrátenie',
      paragraphs: [
        'Po ukončení zmluvy vymažeme alebo vrátime údaje podľa pokynov zákazníka, s ohľadom na zákonné povinnosti.',
        'Zálohy môžu pretrvávať obmedzenú technickú dobu pred automatickým vymazaním.',
      ],
    },
    {
      heading: '7. Audit',
      paragraphs: [
        'Zákazník môže požiadať o primerané informácie na preukázanie súladu. Audity na mieste s 30-dňovou výpoveďou, najviac raz ročne, ak to nevyžaduje dozorný úrad.',
      ],
    },
    {
      heading: '8. Kontakt',
      paragraphs: [
        `DPA a otázky ochrany údajov: ${privacyEmail}. Adresa prevádzkovateľa: ${address.replace(/\n/g, ', ')}.`,
      ],
    },
  ],
};

export const imprintEs: LegalDocumentContent = { ...imprintEn, title: 'Aviso legal' };
export const dpaEs: LegalDocumentContent = { ...dpaEn, title: 'Acuerdo de tratamiento de datos (DPA)' };
