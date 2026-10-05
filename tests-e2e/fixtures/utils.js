/**
 * Parse a displayed money string into a number, handling both "1,234.56" and
 * "1.234,56" by treating the last separator as the decimal point.
 * @param {string} text
 * @returns {number}
 */
export function parsePrice(text) {
    const m = (text.match(/[\d.,]+/) || ['0'])[0];
    const sep = Math.max(m.lastIndexOf(','), m.lastIndexOf('.'));
    if (-1 === sep) {
        return parseFloat(m);
    }
    return parseFloat(`${m.slice(0, sep).replace(/[.,]/g, '')}.${m.slice(sep + 1)}`);
}
