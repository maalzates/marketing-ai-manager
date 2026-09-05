export function formatMoney(amount, currency) {
    if (amount == null || amount === '') {
        return '—';
    }

    return new Intl.NumberFormat(undefined, currency ? { style: 'currency', currency } : {}).format(Number(amount));
}
