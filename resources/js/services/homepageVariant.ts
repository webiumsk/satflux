export type HomepageVariant = 'legacy' | 'showcase';

/** Read the server-selected public enum and fall back safely when bootstrap data is absent or invalid. */
export function readHomepageVariant(element: HTMLElement | null): HomepageVariant {
    return element?.dataset.homepageVariant === 'showcase' ? 'showcase' : 'legacy';
}

// Capture once per loaded document, including visits through the application router.
// Runtime configuration changes take effect on a full reload.
export const homepageVariant = readHomepageVariant(document.getElementById('app'));
