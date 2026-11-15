import { beforeEach, describe, expect, it, vi } from 'vitest';

const get = vi.hoisted(() => vi.fn());
vi.mock('../services/api', () => ({ default: { get } }));

beforeEach(() => {
  vi.resetModules();
  get.mockReset();
});

describe('directional eFaktura server compatibility', () => {
  it.each([undefined, false, 'true'])('does not enable separate connections for an old or ambiguous capability (%s)', async (capability) => {
    get.mockResolvedValue({ data: { efaktura_enabled: true, efaktura_directional_connections: capability } });
    const { useEfakturaFeature } = await import('../composables/useEfakturaFeature');
    const feature = useEfakturaFeature();
    expect(feature.directionalConnections.value).toBe(false);
    expect(await feature.load()).toBe(true);
    expect(feature.directionalConnections.value).toBe(false);
  });

  it('allows separate connections only after a compatible config response', async () => {
    get.mockResolvedValue({ data: { efaktura_enabled: true, efaktura_directional_connections: true } });
    const { useEfakturaFeature } = await import('../composables/useEfakturaFeature');
    const feature = useEfakturaFeature();
    expect(feature.directionalConnections.value).toBe(false);
    await feature.load();
    expect(feature.directionalConnections.value).toBe(true);
    expect(get).toHaveBeenCalledExactlyOnceWith('/config');
  });
});
