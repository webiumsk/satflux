import { enableAutoUnmount, flushPromises, mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import type { TurnstileApi, TurnstileOptions } from "../services/turnstile";

enableAutoUnmount(afterEach);
const mocks = vi.hoisted(() => ({ load: vi.fn() }));
vi.mock("../services/turnstile", () => ({ loadTurnstile: mocks.load }));
vi.mock("vue-i18n", async (importOriginal) => ({
    ...await importOriginal<typeof import("vue-i18n")>(),
    useI18n: () => ({ t: (key: string) => key, locale: ref("sk") }),
}));
import TurnstileChallenge from "../components/auth/TurnstileChallenge.vue";

let options: TurnstileOptions[];
let api: TurnstileApi;
beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    options = [];
    api = {
        render: vi.fn((_container: HTMLElement, settings: TurnstileOptions) => { options.push(settings); return `synthetic-widget-${options.length}`; }),
        remove: vi.fn(), ready: callback => callback(),
    };
    mocks.load.mockResolvedValue(api);
});
afterEach(() => {
    vi.useRealTimers();
    delete window.turnstile;
    document.querySelectorAll("script[data-satflux-turnstile]").forEach(script => script.remove());
});

describe("registration verification widget", () => {
    it("sends only public widget settings and emits one token per attempt", async () => {
        const wrapper = mount(TurnstileChallenge, { props: { siteKey: "synthetic-site-key" } });
        await flushPromises();
        expect(options[0]).toMatchObject({ sitekey: "synthetic-site-key", action: "register", size: "compact", theme: "dark", language: "sk", retry: "never" });
        expect(options[0]).not.toHaveProperty("cData");
        expect(options[0]).not.toHaveProperty("secret");
        options[0]!.callback("synthetic-token");
        options[0]!.callback("synthetic-second-token");
        expect(wrapper.emitted("verified")).toEqual([["synthetic-token"]]);
    });

    it("requires manual retry after expiry and ignores callbacks from the removed challenge", async () => {
        const wrapper = mount(TurnstileChallenge, { props: { siteKey: "synthetic-site-key" } });
        await flushPromises();
        options[0]!["expired-callback"]();
        await flushPromises();
        expect(wrapper.text()).toContain("auth.turnstile_expired");
        options[0]!.callback("synthetic-expired-token");
        expect(wrapper.emitted("verified")).toBeUndefined();
        await wrapper.get("button").trigger("click");
        await flushPromises();
        expect(api.remove).toHaveBeenCalledWith("synthetic-widget-1");
        options[0]!.callback("synthetic-late-token");
        expect(wrapper.emitted("verified")).toBeUndefined();
        options[1]!.callback("synthetic-fresh-token");
        expect(wrapper.emitted("verified")).toEqual([["synthetic-fresh-token"]]);
    });

    it("reports script failure and retries explicitly without emitting verification", async () => {
        mocks.load.mockRejectedValueOnce(new Error("Synthetic script failure"));
        const wrapper = mount(TurnstileChallenge, { props: { siteKey: "synthetic-site-key" } });
        await flushPromises();
        expect(wrapper.text()).toContain("auth.turnstile_error");
        expect(wrapper.emitted("verified")).toBeUndefined();
        await wrapper.get("button").trigger("click");
        await flushPromises();
        expect(options).toHaveLength(1);
    });

    it("bounds the challenge lifetime and suppresses late success after unmount", async () => {
        const wrapper = mount(TurnstileChallenge, { props: { siteKey: "synthetic-site-key" } });
        await flushPromises();
        await vi.advanceTimersByTimeAsync(300_000);
        expect(wrapper.text()).toContain("auth.turnstile_expired");
        wrapper.unmount();
        options[0]!.callback("synthetic-late-token");
        expect(wrapper.emitted("verified")).toBeUndefined();
        expect(api.remove).toHaveBeenCalledWith("synthetic-widget-1");
    });

    it("never renders after script loading completes on an unmounted component", async () => {
        let ready!: (api: TurnstileApi) => void;
        mocks.load.mockReturnValue(new Promise(resolve => { ready = resolve; }));
        const wrapper = mount(TurnstileChallenge, { props: { siteKey: "synthetic-site-key" } });
        wrapper.unmount();
        ready(api);
        await flushPromises();
        expect(api.render).not.toHaveBeenCalled();
    });
});

describe("fixed challenge script loader", () => {
    it("keeps an early SDK global behind the shared ready promise", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        let ready!: () => void;
        const earlyApi = { ...api, ready: vi.fn((callback: () => void) => { ready = callback; }) };
        window.turnstile = earlyApi;
        const second = loadTurnstile();
        expect(second).toBe(first);
        document.querySelector("script[data-satflux-turnstile]")!.dispatchEvent(new Event("load"));
        let resolved = false;
        void second.then(() => { resolved = true; });
        await flushPromises();
        expect(resolved).toBe(false);
        expect(loadTurnstile()).toBe(first);
        ready();
        await expect(second).resolves.toBe(earlyApi);
    });

    it("shares concurrent requests and loads only the fixed Cloudflare script", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const second = loadTurnstile();
        expect(first).toBe(second);
        const script = document.querySelector<HTMLScriptElement>("script[data-satflux-turnstile]")!;
        expect(script.src).toBe("https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit");
        expect(document.querySelectorAll("script[data-satflux-turnstile]")).toHaveLength(1);
        window.turnstile = api;
        script.dispatchEvent(new Event("load"));
        await expect(first).resolves.toBe(api);
        await expect(loadTurnstile()).resolves.toBe(api);
    });

    it("times out script loading and permits a fresh explicit retry", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        await vi.advanceTimersByTimeAsync(15_000);
        await rejection;
        expect(document.querySelector("script[data-satflux-turnstile]")).toBeNull();
        const second = loadTurnstile();
        const script = document.querySelector<HTMLScriptElement>("script[data-satflux-turnstile]")!;
        window.turnstile = api;
        script.dispatchEvent(new Event("load"));
        await expect(second).resolves.toBe(api);
    });

    it("rejects a script error without caching a failed load", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        document.querySelector("script[data-satflux-turnstile]")!.dispatchEvent(new Event("error"));
        await rejection;
        const second = loadTurnstile();
        const script = document.querySelector<HTMLScriptElement>("script[data-satflux-turnstile]")!;
        window.turnstile = api;
        script.dispatchEvent(new Event("load"));
        await expect(second).resolves.toBe(api);
    });
});
