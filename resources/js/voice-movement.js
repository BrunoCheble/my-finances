/**
 * Fills the movement form from a spoken sentence, with rules (no AI):
 * "Gastei 12,40 no Pingo Doce ontem no ActivoBank" -> expense, 12.40, yesterday,
 * that wallet, description "Pingo Doce". Whatever isn't understood is left out.
 */

const strip = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const TYPES = [
    ['transfer', ['transferi', 'transferencia', 'transferir', 'passei', 'mandei', 'enviei']],
    ['income', ['recebi', 'ganhei', 'receita', 'entrou', 'entrada']],
    ['expense', ['gastei', 'paguei', 'comprei', 'despesa', 'gasto', 'pagamento', 'compra']],
];

const UNITS = {
    zero: 0, um: 1, uma: 1, dois: 2, duas: 2, tres: 3, quatro: 4, cinco: 5, seis: 6, sete: 7, oito: 8, nove: 9,
    dez: 10, onze: 11, doze: 12, treze: 13, catorze: 14, quatorze: 14, quinze: 15, dezasseis: 16, dezesseis: 16,
    dezassete: 17, dezessete: 17, dezoito: 18, dezanove: 19, dezenove: 19,
    vinte: 20, trinta: 30, quarenta: 40, cinquenta: 50, sessenta: 60, setenta: 70, oitenta: 80, noventa: 90,
    cem: 100, cento: 100, duzentos: 200, duzentas: 200, trezentos: 300, trezentas: 300, quatrocentos: 400, quatrocentas: 400,
    quinhentos: 500, quinhentas: 500, seiscentos: 600, seiscentas: 600, setecentos: 700, setecentas: 700,
    oitocentos: 800, oitocentas: 800, novecentos: 900, novecentas: 900,
};

const MONTHS = ['janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// words that only join the parts of the sentence: dropped at the edges of the description
const FILLERS = new Set(['no', 'na', 'nos', 'nas', 'em', 'de', 'do', 'da', 'dos', 'das', 'com', 'o', 'a', 'os', 'as', 'e', 'pelo', 'pela',
    'para', 'pro', 'pra', 'ao', 'um', 'uma', 'euro', 'euros', 'eur', 'cartao', 'conta', 'banco', 'hoje', 'ontem', 'anteontem', 'dia']);

const iso = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/**
 * Number words in a row ("cento e vinte e cinco") from position i; "e" only joins a smaller value
 * ("vinte e um"), so "doze e quarenta" stops at 12. Returns { value, end } or null.
 */
function wordsNumber(words, i) {
    let total = 0, current = 0, last = Infinity, end = i, found = false;
    for (let j = i; j < words.length; j++) {
        const word = words[j];
        if (word === 'mil') {
            current = (current || 1) * 1000; total += current; current = 0; last = 1000; end = j + 1; found = true;
            continue;
        }
        if (word === 'e' && found && UNITS[words[j + 1]] !== undefined && UNITS[words[j + 1]] < last) continue;
        const value = UNITS[word];
        if (value === undefined || (found && words[j - 1] !== 'e' && value >= last && last !== 1000)) break;
        current += value; last = value; end = j + 1; found = true;
    }
    return found ? { value: total + current, end } : null;
}

/** The amount: "12,40", "12.40 €", "12 euros e 40", "doze euros e quarenta", "doze vírgula quatro". */
function findAmount(words) {
    const number = (i) => {
        const digits = /^\d+([.,]\d{1,2})?$/.test(words[i]) ? { value: parseFloat(words[i].replace(',', '.')), end: i + 1, decimals: /[.,]/.test(words[i]) } : null;
        return digits || wordsNumber(words, i);
    };
    for (let i = 0; i < words.length; i++) {
        const whole = number(i);
        if (!whole) continue;
        let value = whole.value, end = whole.end;
        if (!whole.decimals) {
            const separator = words[end];
            const euros = ['euro', 'euros', 'eur', '€'].includes(separator);
            const at = euros ? end + 1 : end;
            // "12 euros e 40 (cêntimos)", "doze vírgula quarenta"
            if ((euros && words[at] === 'e') || words[at] === 'virgula') {
                const cents = number(at + 1);
                if (cents && cents.value < 100) {
                    value += (cents.value < 10 && words[at] === 'virgula' ? cents.value * 10 : cents.value) / 100;
                    end = cents.end;
                    if (['centimos', 'centavos', 'cents'].includes(words[end])) end++;
                } else end = at;
            } else if (euros) end = at;
        } else if (['euro', 'euros', 'eur', '€'].includes(words[end])) end++;
        // a day number ("dia 5") is not the amount
        if (words[i - 1] === 'dia') continue;
        return { value: Math.round(value * 100) / 100, start: i, end };
    }
    return null;
}

/** The date: hoje, ontem, anteontem, "dia 5", "dia 5 de março", "5/3". */
function findDate(words, today) {
    const day = (d) => { const copy = new Date(today); copy.setDate(copy.getDate() - d); return iso(copy); };
    for (let i = 0; i < words.length; i++) {
        if (words[i] === 'hoje') return { value: day(0), start: i, end: i + 1 };
        if (words[i] === 'ontem') return { value: day(1), start: i, end: i + 1 };
        if (words[i] === 'anteontem') return { value: day(2), start: i, end: i + 1 };
        const slash = /^(\d{1,2})\/(\d{1,2})$/.exec(words[i]);
        if (slash) return { value: dayOf(+slash[1], +slash[2] - 1, today), start: i, end: i + 1 };
        if (words[i] === 'dia') {
            const number = /^\d{1,2}$/.test(words[i + 1]) ? { value: +words[i + 1], end: i + 2 } : wordsNumber(words, i + 1);
            if (!number || number.value < 1 || number.value > 31) continue;
            let month = null, end = number.end;
            if (words[end] === 'de' && MONTHS.includes(words[end + 1])) { month = MONTHS.indexOf(words[end + 1]); end += 2; }
            return { value: dayOf(number.value, month, today), start: i, end };
        }
    }
    return null;
}

// a day (and month) not in the future: "dia 28" said on the 3rd is last month's
function dayOf(day, month, today) {
    const date = new Date(today.getFullYear(), month ?? today.getMonth(), day);
    if (date > today) month === null ? date.setMonth(date.getMonth() - 1) : date.setFullYear(date.getFullYear() - 1);
    return iso(date);
}

/** Wallets said in the sentence, by full name, else by a word of the name only that wallet has. */
function findWallets(words, wallets) {
    const found = [];
    const tokens = wallets.map((wallet) => ({ wallet, words: strip(wallet.name).split(/\s+/).filter(Boolean) }));
    for (const { wallet, words: name } of tokens) {
        for (let i = 0; i + name.length <= words.length; i++) {
            if (name.every((word, k) => words[i + k] === word)) { found.push({ wallet, start: i, end: i + name.length }); break; }
        }
    }
    for (const { wallet, words: name } of tokens) {
        if (found.some((f) => f.wallet === wallet)) continue;
        for (const word of name) {
            if (word.length < 4 || tokens.some((t) => t.wallet !== wallet && t.words.includes(word))) continue;
            const i = words.indexOf(word);
            if (i >= 0 && !found.some((f) => i >= f.start && i < f.end)) { found.push({ wallet, start: i, end: i + 1 }); break; }
        }
    }
    return found.sort((a, b) => a.start - b.start);
}

export function parseSpokenMovement(text, wallets = [], today = new Date()) {
    // keep "12,40" and "5/3" as one word; "€" apart; pauses written as punctuation
    // ("despesa, 12,40, Pingo Doce") don't stick to the words
    const original = String(text).replace(/€/g, ' € ').replace(/(\d)\s*,\s*(\d)/g, '$1,$2').trim().split(/\s+/)
        .map((word) => word.replace(/[,.;:!?]+$/, '')).filter(Boolean);
    const words = original.map((word) => strip(word).replace(/[^\w,./€]/g, ''));
    const used = new Array(words.length).fill(false);
    const take = (start, end) => { for (let i = start; i < end; i++) used[i] = true; };
    const result = {};

    for (const [type, keys] of TYPES) {
        const i = words.findIndex((word) => keys.includes(word));
        if (i >= 0) { result.type = type; take(i, i + 1); break; }
    }

    const date = findDate(words, today);
    if (date) { result.date = date.value; take(date.start, date.end); }

    const amount = findAmount(words.map((word, i) => (used[i] ? '' : word)));
    if (amount) { result.amount = amount.value; take(amount.start, amount.end); }

    const said = findWallets(words, wallets);
    said.forEach((f) => take(f.start, f.end));
    if (said.length) {
        // a transfer: "do X para o Y" -> the one after "para" receives it
        const target = result.type === 'transfer' ? said.find((f) => ['para', 'pro', 'pra', 'ao'].includes(words[f.start - 1]) || ['para', 'pro', 'pra', 'ao'].includes(words[f.start - 2])) : null;
        const source = said.find((f) => f !== target);
        if (source) result.walletId = source.wallet.id;
        if (target) result.transferToWalletId = target.wallet.id;
    }

    // what is left, without the joining words at its edges, is the description
    let rest = original.filter((_, i) => !used[i]);
    const isFiller = (word) => FILLERS.has(strip(word).replace(/[^\w]/g, ''));
    while (rest.length && isFiller(rest[0])) rest.shift();
    while (rest.length && isFiller(rest[rest.length - 1])) rest.pop();
    // fillers left between parts that were taken out ("Pingo Doce no ... no")
    const description = rest.join(' ').replace(/\s+/g, ' ').replace(/[.,;]+$/, '').trim();
    if (description) result.description = description.charAt(0).toUpperCase() + description.slice(1);

    return result;
}

/** Speech recognition of the browser, when there is one (Chrome, Edge, Safari). */
export function speechSupported() {
    return typeof window !== 'undefined' && !!(window.SpeechRecognition || window.webkitSpeechRecognition);
}

export function listen({ lang = 'pt-PT', onResult, onError, onEnd }) {
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new Recognition();
    recognition.lang = lang;
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.onresult = (event) => onResult?.(event.results[0][0].transcript);
    recognition.onerror = (event) => onError?.(event.error);
    recognition.onend = () => onEnd?.();
    recognition.start();
    return recognition;
}
