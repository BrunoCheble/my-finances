// Spoken sentences -> movement fields. Run with: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseSpokenMovement } from '../../resources/js/voice-movement.js';

const wallets = [
    { id: '1', name: 'ActivoBank' },
    { id: '2', name: 'Poupança' },
    { id: '3', name: 'Cartão Continente' },
    { id: '4', name: 'CGD' },
    { id: '5', name: 'Revolut' },
];
const today = new Date(2026, 10, 6); // 6 Nov 2026
const parse = (text) => parseSpokenMovement(text, wallets, today);

const cases = [
    ['Gastei 12,40 no Pingo Doce ontem no ActivoBank', { type: 'expense', date: '2026-11-05', amount: 12.4, walletId: '1', description: 'Pingo Doce' }],
    ['gastei doze euros e quarenta no pingo doce', { type: 'expense', amount: 12.4, description: 'Pingo doce' }],
    ['paguei a luz 45 euros dia 3 na CGD', { type: 'expense', date: '2026-11-03', amount: 45, walletId: '4', description: 'Luz' }],
    ['recebi o salário 1500 euros hoje no ActivoBank', { type: 'income', date: '2026-11-06', amount: 1500, walletId: '1', description: 'Salário' }],
    ['transferi 200 euros do ActivoBank para a Poupança', { type: 'transfer', amount: 200, walletId: '1', transferToWalletId: '2' }],
    ['comprei pão 2,35 € anteontem com o cartão continente', { type: 'expense', date: '2026-11-04', amount: 2.35, walletId: '3', description: 'Pão' }],
    ['cento e vinte e cinco euros de supermercado dia 28', { date: '2026-10-28', amount: 125, description: 'Supermercado' }],
    ['gastei vinte e três vírgula cinco no café dia 5 de outubro', { type: 'expense', date: '2026-10-05', amount: 23.5, description: 'Café' }],
    // pauses written as punctuation
    ['despesa, 12,40, Pingo Doce', { type: 'expense', amount: 12.4, description: 'Pingo Doce' }],
    ['Despesa. 12.40. Pingo Doce', { type: 'expense', amount: 12.4, description: 'Pingo Doce' }],
    ['receita, 1500, salário', { type: 'income', amount: 1500, description: 'Salário' }],
    // euros and cents said with "e", or written as a time
    ['despesa, 12:48, Pingo Doce', { type: 'expense', amount: 12.48, description: 'Pingo Doce' }],
    ['despesa 12 e 48 Pingo Doce', { type: 'expense', amount: 12.48, description: 'Pingo Doce' }],
    ['despesa doze e quarenta e oito pingo doce', { type: 'expense', amount: 12.48, description: 'Pingo doce' }],
    ['paguei 5 e comprei pão', { type: 'expense', amount: 5, description: 'Comprei pão' }],
    // part of the wallet's name
    ['despesa, 12,40, Pingo Doce, Activo Bank', { type: 'expense', amount: 12.4, walletId: '1', description: 'Pingo Doce' }],
    ['despesa 12,40 Pingo Doce no activo', { type: 'expense', amount: 12.4, walletId: '1', description: 'Pingo Doce' }],
    ['transferi 100 do activo para a poupança', { type: 'transfer', amount: 100, walletId: '1', transferToWalletId: '2' }],
    ['despesa 9,99 netflix revolut', { type: 'expense', amount: 9.99, walletId: '5', description: 'Netflix' }],
];

for (const [text, expected] of cases) {
    test(text, () => assert.deepEqual(parse(text), expected));
}
