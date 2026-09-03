/**
 * Regenerates resources/data/currencies.json — the ISO 4217 codes a ticket
 * may be priced in — from the ICU data in the Node runtime, minus currencies
 * that ICU still carries but nobody prices a ticket in any more.
 *
 *   node scripts/generate-currency-list.mjs
 *
 * PHP (App\Support\Currency) and the browser (useCurrency.js) both read the
 * resulting file, so this is the one place the list is decided.
 */
import { writeFileSync } from 'node:fs';

// Withdrawn or replaced, or an accounting unit rather than money:
const RETIRED = new Set([
    'ANG', // Netherlands Antillean guilder — replaced by XCG, 2025
    'CUC', // Cuban convertible peso — withdrawn 2021
    'HRK', // Croatian kuna — euro since 2023
    'SLL', // old Sierra Leonean leone — replaced by SLE, 2022
    'ZWL', // Zimbabwean dollar 2009–2024 — replaced by ZWG
    'XDR', // IMF Special Drawing Rights
    'XSU', // ALBA Sucre
]);

const codes = Intl.supportedValuesOf('currency').filter((code) => !RETIRED.has(code));

writeFileSync(
    new URL('../resources/data/currencies.json', import.meta.url),
    JSON.stringify(codes, null, 0).replace(/,/g, ',\n').replace('[', '[\n').replace(']', '\n]') + '\n',
);

console.log(`${codes.length} currencies written (${RETIRED.size} retired codes left out).`);
