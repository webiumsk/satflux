# Independent QA: exact Tatra Platba compatibility

Reviewer: /root/bank_mime_diagnostic_qa. Date:2026-11-01.
Disposition: ACCEPT frozen consumer source and bounded compatibility scope. No verified QA finding or unresolved test blocker. Independent security's final exact-literal decision is separate; this report does not authorize publication, merge, deployment or payment replay.

## Repositories and frozen scope

Consumer: webiumsk/satflux, fix/tatra-ascii-wire-profile, basea29b727aae0751dfe554a4cb86da3738f7c5d375. Reviewed uncommitted one-literal parser delta plus three test files. Source/runtime/docs/env/dependencies/Git read-only; only private/tmp probes/results written. Source worktree was clean before author changes; unrelated primary work preserved.

| Frozen file | SHA-256 |
|---|---|
| app/Services/Invoicing/BankImport/TatraBankSepaEvidenceParser.php | 1e24a3eaf5ef207bed4c0159515cab7a3350256bd120092d5f383bbc1da66991 |
| tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php | 9bb63933532427590f2ec05a64b6f612f37b8062cd57ad7dae0829306e387edf |
| tests/Feature/SepaBmailTest.php | cb99e3fb74ba24b043280bdaf17d28b20148a6ce492140bf29184cc839a45575 |
| tests/Feature/BankMimeConfirmationTest.php | 0227f583448b82271981e1a2ebbfe58ee7733a220183df6471dfe1d3e8f78a55 |

Hashes matched before/after executions. The only production change adds exact case-sensitive Platba to CCINT|CSO alternatives, keeping four-digit bank code,1..6prefix/1..10account digits and full anchoring. No identity normalization/schema, provider, API payload, alias list, retention, capture, parser fallback or financial policy changed.

Provider: webiumsk/BTCPayServerPluginsWebium, qualified btcpay-247-plugins worktree, clean e8d7fbcdfd2bda91b5a3a8145a708dcb9dd7201e. Exact BTCPay2.4.7 host2868deedd9d911b715fec7306ea9cada36d86bff verified by submodule status and `bash scripts/verify-btcpay-host.sh`, both before/after tests. Registry2.4.5 metadata is historical and was not used as the execution pin. Qualified SEPA source/tests are byte-identical to c1912bfcdcbc7237b79d9ddc6f057cf519c1dcba; e8d7fbc changes other plugins only.

Actual shipped/tested SEPA0.8.2.0 assembly SHA25677ccab768653972bec65f24f404154fa09e789d298d39d2728add0276ea902af, archive SHA25692720c92fcf459924894fb153ec6e0847e665270de6fa58e036934cf450ac189, assembly informational revision0.8.2.0+c1912bfcdcbc7237b79d9ddc6f057cf519c1dcba. Existing qualified test assembly SHAdac73dfc0b49fa60f315a08b1f5c53bc128a0d299c44b6424e9ee38b041cb904. Binary hashes and qualified source/host cleanliness remained unchanged afterward.

Root/project state, relevant target instructions and integration-testing/architecture/cross-repository/BTCPay skills read. Scoped graphs absent and main query stale; no graph/tool rebuild or installation. Provider source, source build outputs, packages and Git stayed read-only. No real bank values/message, production database/credentials/API, wallet, funds, installed plugin or deployment used. All description/reference/amount/account/date values are explicitly synthetic fixtures; no private original values were inspected.

## Baseline and focused independent parser checks

`/tmp/tatra-platba-qa/run-baseline.sh php /tmp/tatra-platba-qa/record-baseline.php`

PASS:three immutable a29b synthetic label cases. Old CCINT/CSO full evidence and identity accepted; Platba null/stage transaction_description. Class name alone changed in the private baseline copy; immutable parser SHA155e8fac2621b791225ee83a01c9305972c3bc88bd596ca3653fc5cb918a9243. Golden JSON SHAd17a9dc03c92faea2b8dcff80857dbe09ad9e896e3236b5519832c2fe48672c6.

`/tmp/tatra-platba-qa/run-php.sh php /tmp/tatra-platba-qa/check-profile.php`

PASS:39 explicit checks across2old identity/evidence goldens,5Platba reference/numeric/rendering/note variants and10malformed label/numeric/reference/tail cases. CCINT/CSO bytes unchanged. Same Platba identity survives changed invoice reference/free note and shortest permitted numeric representations; new literal identity differs from old label keys without claiming label equivalence. Case variants, substring/suffix prose, bank-width/prefix/account overflow, bad reference and unknown tail reject. The fixture uses only the provided synthetic Platba numeric structure and QRaaaaaaaa reference plus existing repository synthetic fields; no broad mutation corpus was added.

## Verified disposable consumer execution

Own runner `/tmp/tatra-platba-qa/run-php.sh` derives from main runner with /app read-only, public.env.example masking.env, locked dependencies read-only, --pull=never and unique prefixbank_mime_capture_platba_qa_. Preflight PASS testing/uncached/SQLite memory/array cache-session-mail/sync queue/null logs/synthetic provider/loopback Redis. Assigned Redis networknone, bindloopback, no persistence/ports. PHPUnit --do-not-cache-result. No restart/flush; main owns lifecycle.

`/tmp/tatra-platba-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result tests/Feature/BankMimeConfirmationTest.php tests/Feature/BankCompanyMimeImportTest.php tests/Feature/BankInboundEmailTest.php tests/Feature/SepaBmailTest.php tests/Unit/TatraBankEmailParserTest.php tests/Unit/SepaBankEvidenceTest.php tests/Feature/BankMimeCaptureTest.php tests/Unit/BankMimeDiagnosticSummaryTest.php tests/Unit/TatraBankSepaEvidenceDiagnosticsTest.php tests/Feature/SepaCreditEvidenceDiagnosticsTest.php`

PASS:338 tests /1,904 assertions, zero failures/skips,66.599seconds,78.50MiB. Includes all existing bank/proof/ASCII/capture/diagnostic cases and frozen new label cases, normal native/MIME/report paths, malformed complete-frame and trust/provider guards. No historical result counted as a fresh pass.

`/tmp/tatra-platba-qa/run-php.sh php vendor/bin/phpunit --do-not-cache-result /tmp/tatra-platba-qa/PlatbaIngressQaTest.php`

PASS:3 tests /17 assertions, zero failures/skips,5.060seconds. Independent native trusted intake, actual fully signed MIME and actual fixed-ASCII Node/Mailgun flow all produce exactly the same full report tuple and stable literal-tagged key. Body uses only existing counterparty/footer/URL/note/advice tail grammar. Contractv1 performs only settings lookup, no financial POST. Untrusted native origin cannot promote the new frame and produces no provider/import activity. Provider responses are synthetic stubs here; actual matching behavior is separately tested below.

## Actual qualified provider checks

Main provisioned NEW disposable PostgreSQL17 namespace satflux-platba-provider-postgres-test: networknone/no ports, loopback listen, tmpfs data, synthetic credentials, expected database/user identity verified by SQL and again by the existing test fixture. No old/unknown DB or production state was used. All provider executions used the existing SDK10.0.403, .NET/ASP.NET10.0.14 image with --pull=never. Source/shipped assemblies/old package cache read-only; test working directory/results/obj/bin/CLI home only /tmp/tatra-platba-provider-qa. No package downloads/new packages: private project has no PackageReference and NuGet sources explicitly cleared.

`/tmp/tatra-platba-provider-qa/run-dotnet.sh --info`

Verified versions above. VSTest emitted a preexisting workload-verification warning, but all tests executed; no workload update/install attempted.

`/tmp/tatra-platba-provider-qa/run-dotnet.sh vstest /qualified/BTCPayServer.Plugins.SepaInstantQr.Tests.dll '--TestCaseFilter:FullyQualifiedName~Duplicate_delivery_records_once_and_keeps_the_first_confirmation|FullyQualifiedName~Concurrent_deliveries_of_the_same_reference_record_exactly_once|FullyQualifiedName~A_retry_after_external_success_uses_the_same_invoice_payment_identity' --ResultsDirectory:/qa/results --Logger:trx`

PASS:3 existing shipped-assembly cases, zero failed/skipped,2seconds. TRX counters independently parsed: total/executed/passed3, all failure/timeout/aborted/not-executed counts0. Those existing cases reuse the same event key; they are not alone sufficient for cross-label changed-key evidence.

`/tmp/tatra-platba-provider-qa/run-dotnet.sh restore /qa/PlatbaProviderQa.csproj --configfile /qa/NuGet.Config`
`/tmp/tatra-platba-provider-qa/run-dotnet.sh build /qa/PlatbaProviderQa.csproj --no-restore --configuration Release`
`/tmp/tatra-platba-provider-qa/run-dotnet.sh /qa/bin/Release/net10.0/PlatbaProviderQa.dll`

Private no-package harness build PASS zero warnings/errors; all files under/tmp. Runtime PASS:4 cases /22 explicit assertions against the ACTUAL shipped SepaMatchingService plus real isolated PostgreSQL, with a recorder spy only at the external BTCPay side-effect boundary:

1. Same reference and immutable bank/account/amount, three different opaque64-character hashes: one Settled, two Duplicate, recorder once; first DedupKey/ConfirmedAt retained.
2. Concurrent same-reference different hashes, recorder delayed to hold the row lock: one Settled/one Duplicate, recorder once, Confirmed state retained.
3. Response loss after modeled external stable-reference ID: plugin reservation rolls back/Pending, different-hash retry settles, external-ID set remains one Reference, later third hash Duplicate without another recorder call.
4. Untrusted first bmail report with hashA: review/no recorder/no key consumption; corrected trusted hashB settles once; later untrusted hashC at already-confirmed reference cannot record again.

This demonstrates real matcher duplicate/trust/transaction behavior after distinct hashes; it does not assert identical bank keys or rely only on a consumer stub. Existing recorder source explicitly builds PaymentData.Id=request.Reference and recognizes that ID after AddPayment uncertainty. The private retry test models that stable external identity; it does NOT execute full installed-host PaymentService.AddPayment/invoice-event/HTTP reconciliation. The existing non-atomic host/plugin boundary remains a documented limit.

PG window released to main immediately after completion; no further DB tests planned. Main owns retirement. Provider source/host guard/binary hashes rechecked clean/unchanged afterward.

## Artifact and scope checks

`git diff --check` PASS. Frozen4hashes unchanged. Scoped diff againsta29b confirms confirmation service/report DTO/provider client/Tatra importer/verification/capture/Node/config/routes/locks unchanged. No contractv3/alias table/schema/plugin package/version change is introduced.

Initial API --check attempt could not join the already-retired disposable Redis namespace (Docker exit125, no command executed). Completed using the existing read-only --networknone baseline runner without restarting anything:

`/tmp/tatra-platba-qa/run-baseline.sh php artisan api-docs:export --check`

PASS: API contracts/inventory current. Read-only JSON check confirms only source digest update, unchanged routes/scope/generator and every generated hash current. New profile guide SHAf42829119939e291425c6da53914f84c960c0dffad5cf9c6ec01ad05ee713e31 accurately states literal/bounds, existing tail, qualified same-reference guards, residual reference-stability assumption and no replay/deployment permission. Route manifest SHAc8a6757f9659b22dfea36c029334e414760e448ee0e615b18022b7f6be7a8199.

Probe provenance: pure check SHAa791e2143c9c1487488a6a53e79f7346a4b22d0819cdeb2d97d139e770127b83; private PHP SHA5a68a3eae50429e46a5f58f38a7f3f7b47d046558c688f0c45d8e733da87c2de; private provider Program.cs SHA786a9783e2b1522bf61bc09389f5a9b49835ada3660757284909f1f3228ab04f.

## Acceptance limits

Final security adjudication supersedes the early proposed alias-table/v3 hold: differing label-bearing fingerprints alone do not prove a second settlement. Same signed reference is protected by provider row/state and stable recorder ID; tests above qualify the concrete same-reference behavior. A cross-invoice rendition would also require bank-authenticated changed reference or unauthorized reference reassignment; neither capability is established. That reference-stability assumption is informational baseline risk, not a newly demonstrated label-only exploit. No universal bank-issued transaction identity or cross-reference safety proof is claimed.

Actual original bank values/message were not supplied to QA; only lexical label/grammar and tail labels. Synthetics do not establish actual deployment, current full-message bank interoperability, historical settlement or installed loader/HTTP behavior. No new transfer/replay is a test prerequisite. Fresh exact-head CI, source publication and operator deployment remain main-owned separate gates.

Skipped as unnecessary/outside scope: unchanged Node77 rerun (Node/test/dependency bytes matcha29b and normal Node-backed flows pass), full unrelated application/frontend suite, independent full static/style suite and all-provider174suite repetition. Targeted existing3plus private4cases cover the newly adjudicated provider question. No source/host/package writes, tool install, production tests or deployment.
