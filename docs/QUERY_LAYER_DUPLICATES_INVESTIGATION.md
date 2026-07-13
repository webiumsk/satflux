# Vyšetrenie duplicít v Evolu query vrstve (P2 fáza 5)

Verzie: `@evolu/common` 7.4.1, `@evolu/web` 2.4.0, `@evolu/vue` 1.4.0.

## 1. Symptóm a história

Produkcia 2026-07-13 (P1, PR #126): zoznam firiem zobrazoval ten istý
fyzický riadok viackrát - **identické id** (overené používateľom: obe karty
"Webium" viedli na rovnaké URL id). Výskyt koreloval s aktívnou relay
subscription; po refreshi sa menil. Aplikačná ochrana `dedupeRowsById`
(zoznam, detekcia duplicít, merge) symptóm odstránila; príčina zostala
otvorená ako P2 otázka.

Poznámka: toto NIE JE prípad "paralelných kópií" s rôznymi id (tie boli
reálne CRDT dáta z éry mŕtveho relay transportu a vyriešil ich merge).
Tu ide o opakovanie toho istého riadku v jednom výsledku query.

## 2. Čo audit vylúčil

- **SQL multiplikáciu**: `allCompaniesQuery`/`allCompaniesDetailQuery` sú
  single-table selecty nad `company` (PRIMARY KEY id), bez JOINov -
  `sqlite.exec` nemôže vrátiť to isté id dvakrát.
- **Aplikačné mapovanie**: `mapEvoluCompanies` je 1:1 map; duplicita bola
  pozorovaná priamo v `useQuery` výstupe.
- **@evolu/vue useQuery**: triviálny wrapper - `rows.value =
  evolu.getQueryRows(query)`; nič nespája ani nekumuluje.
- **Dve Evolu inštancie**: `Instances.ensure` drží singleton per name.

## 3. Architektúra: stavový, index-based patch protokol

Reaktívne výsledky queries netečú ako celé polia, ale ako **diff patche**
proti cache na oboch stranách:

1. **Worker** (`Query.js: loadQueries`): drží `queryRowsCache` per **tabId**
   (náhodné id per page load). Po vykonaní SQL spočíta
   `makePatches(previousRows, nextRows)`:
   - iná dĺžka alebo neznáma predchádzajúca → `replaceAll`,
   - rovnaká dĺžka → `replaceAt {index, value}` pre zmenené indexy.
2. **Klient** (`Evolu.js: onQueryPatches`): aplikuje patche na SVOJU cache
   (`rowsStore`): `replaceAt` = `Array.toSpliced(index, 1, row)`.

**Invariant korektnosti: klientove riadky === workerove previousRows v
momente patchu.** Protokol nemá sekvenčné čísla, checksumy ani potvrdenia -
invariant drží len vďaka FIFO doručovaniu správ v rámci page lifetime.

## 4. Mechanizmus materializácie duplicity

Ak sa cache rozídu pri ROVNAKEJ dĺžke poľa, `replaceAt` splicuje proti
nesprávnemu základu. Deterministická demonštrácia (test
`resources/js/__tests__/queryLayerDuplicates.test.ts`, beží proti reálnym
`makePatches`/`applyPatches` z balíka):

- worker verí, že predchádzajúci výsledok je `[B, C, A]`; klient drží
  `[A, B, C]` (ušiel mu skorší reorder patch),
- sync upraví riadok A → worker pošle `replaceAt(2, A')`,
- klient: `[A, B, C]` → **`[A, B, A']`** - id "A" dvakrát, riadok C
  stratený. Presne pozorovaný fantóm.
- Bonus: `toSpliced` s indexom za koncom poľa APPENDUJE - kratší klientsky
  základ tiež vyrába duplicitu.
- Zmena dĺžky sa lieči sama (`replaceAll`), preto boli fantómy prechodné
  (ďalší insert/delete/refresh ich zmazal).

## 5. Kde sa môže invariant zlomiť (transport)

`@evolu/web` nepoužíva natívny SharedWorker: `createSharedWebWorker` =
dedicated Worker vo "vlastníckom" tabe (Web Locks) + **BroadcastChannel**
pre všetky ostatné taby (`SharedWebWorker.js`). Identifikované krehké okná:

- **Stale `ownerReady` pri handoveri vlastníka**: keď vlastnícky tab
  zanikne, ostatné taby majú `ownerReady === true` (nikdy sa neresetuje) a
  posielajú `to-worker` správy do kanála, ktorý ešte nikto nefor warduje -
  requesty sa stratia. Strata REQUESTU je "len" liveness (stale UI).
- **Strata RESPONSE v rámci page lifetime**: worker spracuje "query"
  (aktualizuje svoju cache pre tabId), ale `onQueryPatches` sa k tabu
  nedostane (BroadcastChannel doručovanie pri freeze/bfcache správaní
  prehliadača, alebo handover presne medzi spracovaním a forwardom) →
  trvalý desync cache pre daný tab. Ďalší patch s rovnakou dĺžkou =
  duplicita podľa bodu 4.
- Smrť workera (zánik vlastníckeho tabu) je paradoxne BEZPEČNÁ: nová
  worker cache je prázdna → `replaceAll`.

Prečo korelácia s relay subscription: sync receive → broadcast
`refreshQueries` → každý tab znovu žiada všetky subscribed queries →
búrka query/patch round-tripov počas syncu → výrazne širšie okná pre
stratu odpovede.

## 6. Repro pokus

- Deterministický unit repro mechanizmu: ÁNO (4 testy, bod 4).
- End-to-end repro straty správy v prehliadači: NEDOSIAHNUTÝ v rozumnom
  čase - vyžaduje multi-tab freeze/owner-handover timing (headless
  chromium tab freezing sa nedá spoľahlivo vynútiť). Mechanizmus je však
  jediná zostávajúca cesta po vylúčeniach v bode 2 a symptómu presne
  zodpovedá (prechodnosť, korelácia so sync aktivitou, same-id).

## 7. Ochrana aplikácie (zostáva v platnosti)

`dedupeRowsById` pri zdroji zoznamu, v detekcii duplicít aj v merge
(`useInvoicingCompaniesLocal.ts`, `duplicateCompanies.ts`) - aplikácia je
voči fantómom odolná bez ohľadu na upstream. Nezavádzame ďalšie vrstvy;
prípadné rozšírenie na iné zoznamy (dokumenty, kontakty) len ak sa symptóm
reálne objaví - mechanizmus je rovnaký a rovnako prechodný.

## 8. Upstream issue draft (EN, pripravené na odoslanie do evoluhq/evolu)

> **Title:** Index-based query patches can materialize duplicate rows when
> client/worker row caches desync
>
> **Versions:** @evolu/common 7.4.1, @evolu/web 2.4.0, @evolu/vue 1.4.0
>
> **Summary:** Reactive query results are delivered as stateful index-based
> patches: the DB worker diffs against a per-tab `queryRowsCache`
> (`makePatches`) and the client applies `replaceAt` patches to its own
> cached rows via `toSpliced` (`applyPatches`). The protocol carries no
> sequence number, baseline checksum, or ack, so correctness depends on the
> client rows being byte-identical to the worker's `previousRows` whenever a
> patch arrives. If any `onQueryPatches` message is lost within a page's
> lifetime (the BroadcastChannel-based `createSharedWebWorker` has such
> windows, e.g. the stale `ownerReady` flag during owner-tab handover, or
> browser tab freezing), the caches desync silently. A subsequent same-length
> diff then splices against the wrong baseline and the SAME row id appears at
> two indexes while another row vanishes - we observed exactly this in
> production (single-table primary-key query, id rendered twice, transient,
> correlated with relay sync activity).
>
> **Deterministic demonstration** (against the real exports):
> ```ts
> const workerPrev = [B, C, A]; // client missed an earlier reorder
> const clientRows = [A, B, C];
> const patches = makePatches(workerPrev, [B, C, editedA]);
> // => [{ op: "replaceAt", index: 2, value: editedA }]
> applyPatches(patches, clientRows);
> // => [A, B, editedA]  - id "A" twice, C lost
> ```
> Also `toSpliced(index, 1, row)` past the end of a shorter client array
> APPENDS, which duplicates as well.
>
> **Suggestions (any one closes the corruption window):**
> 1. Version the per-tab baseline: include a monotonically increasing
>    revision (or a cheap hash/length of `previousRows`) in
>    `onQueryPatches`; on mismatch the client discards the patch and
>    re-requests a `replaceAll`.
> 2. Reconcile by row id instead of array index for `replaceAt`.
> 3. Reset `ownerReady` on owner-lock release and re-handshake in
>    `createSharedWebWorker`, so requests/responses cannot be silently
>    dropped mid-lifetime.
>
> Happy to provide the failing-invariant unit test as a PR.

## 9. Záver

- Koreňová príčina fantómových duplicít: **stavový index-based patch
  protokol bez ochrany invariantu** + reálne okná straty správ v
  BroadcastChannel transporte. Nie je to chyba aplikácie ani SQL.
- Aplikácia je chránená (`dedupeRowsById`), upstream draft pripravený.
- Nadväznosť: pri upgrade Evolu overiť changelog `Query.js`/`
  SharedWebWorker.js`; ak upstream pridá revíziu/checksum, ochranu možno
  zjednodušiť (ponechať ako poistku).
