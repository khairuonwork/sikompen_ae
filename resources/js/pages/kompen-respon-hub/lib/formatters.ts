export function number(value: string): string {
    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 2,
    }).format(Number(value));
}

export function datetimeLocalValue(value: string, timeZone: string): string {
    const dateParts = new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
    }).formatToParts(new Date(value));
    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        dateParts.find((item) => item.type === type)?.value ?? "";

    return `${part("year")}-${part("month")}-${part("day")}T${part("hour")}:${part("minute")}`;
}
