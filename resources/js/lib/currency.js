const localeByCurrency = {
    IDR: 'id-ID',
    USD: 'en-US',
    GBP: 'en-GB',
    EUR: 'de-DE',
    EURO: 'de-DE',
};

export function formatCurrency(amount, currencyCode = 'USD') {
    const numericAmount = Number(amount || 0);
    const normalizedCurrency = String(currencyCode || 'USD').toUpperCase();
    const intlCurrencyCode = normalizedCurrency === 'EURO' ? 'EUR' : normalizedCurrency;

    return new Intl.NumberFormat(localeByCurrency[normalizedCurrency] || 'en-US', {
        style: 'currency',
        currency: intlCurrencyCode,
    }).format(numericAmount);
}
