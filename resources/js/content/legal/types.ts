export interface LegalSection {
  heading: string;
  paragraphs: string[];
}

export interface LegalDocumentContent {
  title: string;
  metaDescription: string;
  lastUpdated: string;
  draftNotice: string;
  sections: LegalSection[];
}

export type LegalDocId = 'terms' | 'privacy' | 'imprint' | 'dpa';

export type LegalLocale = 'en' | 'sk' | 'es';
