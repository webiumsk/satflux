# Embedded PDF access in received UBL

## Approved scope and acceptance

The owner requested viewing and downloading PDFs embedded in received UBL,
including XML already imported into expenses. Extract on demand in the browser
from the preserved original. Do not alter original bytes, create attachment rows,
change custody/acknowledgement, or add provider requests. No backend/API or schema
change is required. Existing encrypted storage and authorized attachment access
remain the source of the XML.

Accept both UBL Invoice and CreditNote with namespace-aware direct attachment
paths. Ignore external references and non-PDF attachments. Reject DTD/entities,
malformed XML/Base64, invalid PDF headers, and oversized inputs. Bound source XML
to 2 MiB, individual PDFs to 1 MiB, aggregate PDF bytes to 2 MiB, and attachments
to ten. Preserve safe filenames while removing paths/control characters.

Display a filename and separate View/Download actions. A lazy PDF.js canvas viewer
renders one bounded page at a time without annotation, scripting or XFA layers.
Use owned same-origin workers, time/resource bounds and cleanup on navigation.
Download the exact decoded bytes. Hide the panel when no embedded PDF exists;
show localized fixed errors for rejected content. Context switches must discard
late attachment reads. Cover EN/SK/ES/DE/CS and keep original XML access intact.

## Integration contract and ownership

Repository: owned Satflux only, branch `feat/efaktura-embedded-pdf`, based on
`f5cb7f0e` (merged PR444). PR443 remains independent. No deployment is authorized.

| Task | Role | Writable ownership | Dependencies and evidence |
| --- | --- | --- | --- |
| PDF-1 | Frontend specialist | `resources/js/utils/ublEmbeddedPdf.ts`, `resources/js/__tests__/ublEmbeddedPdf.test.ts` | Approved extraction contract; synthetic parser/security tests |
| PDF-2 | Coordinator | New `UblEmbeddedPdfPanel.vue` and `UblEmbeddedPdfViewer.vue`; inbox and ExpenseShow integration; component/integration tests; five locales and guides; this note | PDF-1; mounted view/download and navigation checks; lint/typecheck/build/parity |
| PDF-3 | Independent security | No repository writes; private review evidence only | Final PDF-1/PDF-2 source freeze, untrusted input and context/resource review |
| PDF-4 | Independent QA | No repository writes; private synthetic checks only | Final PDF-1/PDF-2 source freeze, inbox/imported expense and download/cleanup checks |

Specialists cannot commit, push, merge, deploy, install dependencies, or access
owner accounts/provider credentials. The coordinator owns integration and Git
publication. Unrelated primary-checkout work and existing previews are preserved.

`extractUblEmbeddedPdfs(xml)` returns `{ pdfs, rejectedCount, error? }`, where PDFs
have stable local `id`, sanitized `filename` and `Uint8Array bytes`; errors are
`invalid_xml`, `too_large` or `unsupported_document` (valid other XML stays hidden). Panel props are `xml: string | null`; viewer props
are `bytes: Uint8Array` and `label: string`. No server identifiers are introduced.

Graphify was queried in the coordinator checkout; its heuristic graph supplied no
useful component references. The isolated feature checkout has no graph, so
targeted file reads were used without installing or rebuilding a graph.

## Validation

Main final validation: 204 tests across ten focused/regression files; full lint
(0 errors / 46 inherited warnings), unchanged typecheck baseline (110 / no new),
five-locale parity, diff whitespace and production build (7,665 modules) pass.

Independent QA accepts 19 private mounted cases and 137 permanent tests. Actual
Chrome verifies two-page PDF.js rendering/pagination, exact 697-byte download,
no page errors and owned-worker termination. Independent security accepts 147
cases across six files, including ten additional security cases, with no blocking
findings. Counts overlap. All five runtime hashes match the final review manifest.
Private reports, manifests, logs and synthetic browser evidence are retained under
`storage/app/private/reviews/efaktura-embedded-pdf`. The owned localhost fixture
was stopped; existing owner previews remain untouched.

Existing locked Node dependencies are reused after verifying lockfile equality;
no packages are added. No PHP/API/schema changes require a new backend test run.
PR creation, exact-head CI and owner merge/deployment remain the next steps.
