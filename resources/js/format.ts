export const money = (minor: number) => new Intl.NumberFormat('en-PK',{ style:'currency',currency:'PKR',maximumFractionDigits:0 }).format(minor/100);
export const compactMoney = (minor: number) => new Intl.NumberFormat('en',{notation:'compact',maximumFractionDigits:1}).format(minor/100);
