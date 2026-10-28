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
        remove: vi.fn(), ready: vi.fn(() => { throw new Error("[Cloudflare Turnstile] Remove async/defer before using ready(). Code: 3857"); }),
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

function scriptCallback(script: HTMLScriptElement) {
    const callbackName = new URL(script.src).searchParams.get("onload")!;
    const callback = (window as unknown as Record<string, () => void>)[callbackName]!;
    expect(callback).toBeTypeOf("function");
    return { callbackName, callback };
}

function currentScript() {
    return document.querySelector<HTMLScriptElement>("script[data-satflux-turnstile]")!;
}

describe("fixed challenge script loader", () => {
    it("accepts the asynchronous SDK callback without calling unsupported ready", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const pending = loadTurnstile();
        const script = currentScript();
        window.turnstile = api;
        script.dispatchEvent(new Event("load"));
        scriptCallback(script).callback();
        await expect(pending).resolves.toBe(api);
        expect(api.ready).not.toHaveBeenCalled();
    });

    it("keeps an early SDK global behind the shared callback promise", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        window.turnstile = api;
        const second = loadTurnstile();
        expect(second).toBe(first);
        currentScript().dispatchEvent(new Event("load"));
        let resolved = false;
        void second.then(() => { resolved = true; });
        await flushPromises();
        expect(resolved).toBe(false);
        expect(loadTurnstile()).toBe(first);
        scriptCallback(currentScript()).callback();
        await expect(second).resolves.toBe(api);
        expect(api.ready).not.toHaveBeenCalled();
    });

    it("shares concurrent requests and loads only the fixed Cloudflare script", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        expect(loadTurnstile()).toBe(first);
        const script = currentScript();
        const url = new URL(script.src);
        expect(url.origin + url.pathname).toBe("https://challenges.cloudflare.com/turnstile/v0/api.js");
        expect(url.searchParams.get("render")).toBe("explicit");
        expect([...url.searchParams.keys()]).toEqual(["render", "onload"]);
        const { callbackName, callback } = scriptCallback(script);
        expect(callbackName).toMatch(/^satfluxTurnstileLoaded[0-9]+$/);
        expect(script.async).toBe(true);
        expect(script.defer).toBe(true);
        expect(document.querySelectorAll("script[data-satflux-turnstile]")).toHaveLength(1);
        window.turnstile = api;
        callback();
        await expect(first).resolves.toBe(api);
        expect((window as unknown as Record<string, unknown>)[callbackName]).toBeUndefined();
        await expect(loadTurnstile()).resolves.toBe(api);
        expect(document.querySelectorAll("script[data-satflux-turnstile]")).toHaveLength(1);
    });

    it("times out and ignores a stale callback during a fresh explicit retry", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const oldScript = currentScript();
        const old = scriptCallback(oldScript);
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        window.turnstile = api;
        await vi.advanceTimersByTimeAsync(15_000);
        await rejection;
        expect(currentScript()).toBeNull();
        expect((window as unknown as Record<string, unknown>)[old.callbackName]).toBeUndefined();
        const second = loadTurnstile();
        const script = currentScript();
        const fresh = scriptCallback(script);
        expect(fresh.callbackName).not.toBe(old.callbackName);
        let resolved = false;
        void second.then(() => { resolved = true; });
        old.callback();
        oldScript.dispatchEvent(new Event("load"));
        await flushPromises();
        expect(resolved).toBe(false);
        expect((window as unknown as Record<string, unknown>)[fresh.callbackName]).toBe(fresh.callback);
        expect(loadTurnstile()).toBe(second);
        fresh.callback();
        await expect(second).resolves.toBe(api);
        expect(api.ready).not.toHaveBeenCalled();
    });

    it("rejects a script error without caching a failed load", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const old = scriptCallback(currentScript());
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        currentScript().dispatchEvent(new Event("error"));
        await rejection;
        expect(currentScript()).toBeNull();
        expect((window as unknown as Record<string, unknown>)[old.callbackName]).toBeUndefined();
        const second = loadTurnstile();
        window.turnstile = api;
        scriptCallback(currentScript()).callback();
        await expect(second).resolves.toBe(api);
    });

    it("rejects callback readiness without a usable API and permits retry", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        scriptCallback(currentScript()).callback();
        await rejection;
        expect(currentScript()).toBeNull();
        const second = loadTurnstile();
        window.turnstile = api;
        scriptCallback(currentScript()).callback();
        await expect(second).resolves.toBe(api);
    });

    it("does not overwrite an occupied callback name or delete another owner's replacement", async () => {
        const { loadTurnstile } = await vi.importActual<typeof import("../services/turnstile")>("../services/turnstile");
        const first = loadTurnstile();
        const { callbackName, callback } = scriptCallback(currentScript());
        const callbacks = window as unknown as Record<string, (() => void) | undefined>;
        const occupiedName = `satfluxTurnstileLoaded${Number(callbackName.replace("satfluxTurnstileLoaded", "")) + 1}`;
        const foreign = vi.fn();
        callbacks[occupiedName] = foreign;
        callbacks[callbackName] = foreign;
        const rejection = expect(first).rejects.toThrow("Registration verification is unavailable.");
        await vi.advanceTimersByTimeAsync(15_000);
        await rejection;
        expect(callbacks[callbackName]).toBe(foreign);
        const second = loadTurnstile();
        const fresh = scriptCallback(currentScript());
        expect(fresh.callbackName).not.toBe(occupiedName);
        expect(callbacks[occupiedName]).toBe(foreign);
        window.turnstile = api;
        callback();
        fresh.callback();
        await expect(second).resolves.toBe(api);
        expect(foreign).not.toHaveBeenCalled();
        delete callbacks[occupiedName];
        delete callbacks[callbackName];
    });
});
