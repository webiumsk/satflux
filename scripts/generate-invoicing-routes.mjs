import fs from "fs";

const src = fs.readFileSync("resources/js/router/index.ts", "utf8");
const start = src.indexOf("path: '/invoicing',");
const end = src.indexOf("path: '/stores',");
const block = src.slice(start, end);

const routes = [];
const re =
    /path: '([^']+)',\s*\n\s*name: '([^']+)',\s*\n\s*component: \(\) => import\('([^']+)'\),\s*\n\s*meta: (\{[^}]+\})/g;

let m;
while ((m = re.exec(block)) !== null) {
    const path = m[1].replace(/^\/invoicing\/?/, "") || "";
    routes.push({ path, name: m[2], component: m[3], meta: m[4] });
}

const lines = routes.map(
    (r) => `    {
        path: '${r.path}',
        name: '${r.name}',
        component: () => import('${r.component}'),
        meta: ${r.meta},
    }`,
);

const out = `import type { RouteRecordRaw } from 'vue-router';

/** Invoicing child routes (mounted under InvoicingEvoluLayout). */
export const invoicingRoutes: RouteRecordRaw[] = [
${lines.join(",\n")}
];
`;

fs.writeFileSync("resources/js/router/invoicingRoutes.ts", out);
console.log(`Wrote ${routes.length} invoicing routes`);
