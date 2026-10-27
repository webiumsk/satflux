import api from './api';

export const API_DOCUMENT_IDS = ['satflux', 'woocommerce', 'webhooks'] as const;
export type ApiDocumentId = typeof API_DOCUMENT_IDS[number];
export interface ApiDocument {
    id: ApiDocumentId;
    title: string;
    description: string;
    url: string;
}
export interface ApiProvider {
    id: string;
    title: string;
    description: string;
    url: string;
}
export interface ApiDocumentationCatalog {
    documents: ApiDocument[];
    providers: ApiProvider[];
}
const providerUrls = new Set([
    'https://github.com/webiumsk/chorala/blob/main/packages/sdk/openapi.json',
    'https://docs.btcpayserver.org/API/Greenfield/v1/',
    'https://developer.woocommerce.com/docs/apis/rest-api/',
]);

export function apiDocumentPath(id: ApiDocumentId): string {
    if (!API_DOCUMENT_IDS.includes(id)) throw new Error('Unknown API document');
    return `/api/admin/api-docs/specs/${id}`;
}

export async function loadApiDocumentationCatalog(): Promise<ApiDocumentationCatalog> {
    const { data } = await api.get<ApiDocumentationCatalog>('/admin/api-docs/catalog', { skipErrorFlash: true });
    if (!Array.isArray(data.documents) || !data.documents.length || !Array.isArray(data.providers)) {
        throw new Error('Invalid API documentation catalog');
    }
    const seen = new Set<string>();
    for (const document of data.documents) {
        if (!API_DOCUMENT_IDS.includes(document.id) || document.url !== apiDocumentPath(document.id)
            || seen.has(document.id) || typeof document.title !== 'string' || typeof document.description !== 'string') {
            throw new Error('Invalid API document');
        }
        seen.add(document.id);
    }
    for (const provider of data.providers) {
        if (!providerUrls.has(provider.url) || typeof provider.title !== 'string' || typeof provider.description !== 'string') {
            throw new Error('Invalid API provider');
        }
    }
    return data;
}

function assertLocalReferences(value: unknown): void {
    if (!value || typeof value !== 'object') return;
    for (const [key, child] of Object.entries(value)) {
        if (key === '$ref' && (typeof child !== 'string' || !child.startsWith('#/'))) {
            throw new Error('External API document references are disabled');
        }
        assertLocalReferences(child);
    }
}

export async function loadApiDocument(id: ApiDocumentId): Promise<Record<string, unknown>> {
    // Never pass a catalog URL to Axios: its credentialed instance must stay on this fixed local path.
    const { data } = await api.get<Record<string, unknown>>(apiDocumentPath(id).slice(4), { skipErrorFlash: true });
    if (typeof data !== 'object' || data === null || typeof data.openapi !== 'string' || !data.openapi.startsWith('3.1.')) {
        throw new Error('Invalid OpenAPI document');
    }
    assertLocalReferences(data);
    return data;
}

export function apiDocumentationRendererOptions(content: Record<string, unknown>) {
    return {
        content: JSON.stringify(content),
        hideTestRequestButton: true,
        hideClientButton: true,
        persistAuth: false,
        telemetry: false,
        withDefaultFonts: false,
        agent: { disabled: true },
        mcp: { disabled: true },
        showDeveloperTools: 'never' as const,
        proxyUrl: '',
        // Supply documents in memory; block document/API-client transports. Independent upload features are disabled above.
        customFetch: () => Promise.reject(new Error('API reference requests are disabled')),
    };
}
