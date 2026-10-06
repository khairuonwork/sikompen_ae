import { Form, Link } from "@inertiajs/react";
import { Search } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import type { Filters } from "../shared/types";

const monthOptions = [
    { value: "01", label: "Januari" },
    { value: "02", label: "Februari" },
    { value: "03", label: "Maret" },
    { value: "04", label: "April" },
    { value: "05", label: "Mei" },
    { value: "06", label: "Juni" },
    { value: "07", label: "Juli" },
    { value: "08", label: "Agustus" },
    { value: "09", label: "September" },
    { value: "10", label: "Oktober" },
    { value: "11", label: "November" },
    { value: "12", label: "Desember" },
] as const;

function parseImportMonth(value?: string): { year: string; month: string } {
    const match = value?.match(/^(\d{4})-(0[1-9]|1[0-2])$/);

    return match ? { year: match[1], month: match[2] } : { year: "", month: "" };
}

export function ImportAuditFilterPanel({
    filters,
    periods,
    years,
}: {
    filters: Filters;
    periods: string[];
    years: number[];
}): React.JSX.Element {
    const initialDate = parseImportMonth(filters.import_month);
    const [selectedPeriod, setSelectedPeriod] = useState(
        filters.periode_semester ?? "",
    );
    const [selectedYear, setSelectedYear] = useState(
        initialDate.year || String(filters.import_year ?? ""),
    );
    const [selectedMonth, setSelectedMonth] = useState(initialDate.month);

    useEffect(() => {
        const selectedDate = parseImportMonth(filters.import_month);

        setSelectedPeriod(filters.periode_semester ?? "");
        setSelectedYear(
            selectedDate.year || String(filters.import_year ?? ""),
        );
        setSelectedMonth(selectedDate.month);
    }, [filters.import_month, filters.import_year, filters.periode_semester]);

    const importMonth =
        selectedYear !== "" && selectedMonth !== ""
            ? `${selectedYear}-${selectedMonth}`
            : "";

    return (
        <Form
            {...adminIndex.form()}
            className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(180px,1fr)_minmax(140px,0.65fr)_minmax(160px,0.75fr)_auto_auto] md:items-end"
        >
            <input name="tab" type="hidden" value="imports" />
            <input
                name="periode_semester"
                type="hidden"
                value={selectedPeriod}
            />
            <input name="import_month" type="hidden" value={importMonth} />
            <input name="import_year" type="hidden" value={selectedYear} />
            <div className="grid gap-1.5">
                <Label
                    htmlFor="import-audit-period"
                    className="text-xs font-bold text-[#395886]"
                >
                    Periode workbook
                </Label>
                <Select
                    value={selectedPeriod || "all"}
                    onValueChange={(value) =>
                        setSelectedPeriod(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger
                        id="import-audit-period"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                    >
                        <SelectValue placeholder="Semua periode" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua periode
                        </SelectItem>
                        {periods.map((period) => (
                            <SelectItem
                                key={period}
                                value={period}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {period}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor="import-audit-year"
                    className="text-xs font-bold text-[#395886]"
                >
                    Tahun aktivitas
                </Label>
                <Select
                    value={selectedYear || "all"}
                    onValueChange={(value) => {
                        const year = value === "all" ? "" : value;

                        setSelectedYear(year);
                        if (year === "") {
                            setSelectedMonth("");
                        }
                    }}
                >
                    <SelectTrigger
                        id="import-audit-year"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                    >
                        <SelectValue placeholder="Semua tahun" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua tahun
                        </SelectItem>
                        {years.map((year) => (
                            <SelectItem
                                key={year}
                                value={String(year)}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {year}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor="import-audit-month"
                    className="text-xs font-bold text-[#395886]"
                >
                    Bulan aktivitas
                </Label>
                <Select
                    disabled={selectedYear === ""}
                    value={selectedMonth || "all"}
                    onValueChange={(value) =>
                        setSelectedMonth(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger
                        id="import-audit-month"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white disabled:cursor-not-allowed disabled:bg-[#F0F3FA] disabled:text-[#395886]/50"
                    >
                        <SelectValue
                            placeholder={
                                selectedYear === ""
                                    ? "Pilih tahun terlebih dahulu"
                                    : "Semua bulan"
                            }
                        />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua bulan
                        </SelectItem>
                        {monthOptions.map((month) => (
                            <SelectItem
                                key={month.value}
                                value={month.value}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {month.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <Button
                type="submit"
                className="rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:text-white active:scale-95"
            >
                <Search className="mr-1.5 size-4" />
                Terapkan
            </Button>
            <Button
                asChild
                type="button"
                variant="outline"
                className="rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
            >
                <Link href={adminIndex.url({ query: { tab: "imports" } })}>
                    Reset
                </Link>
            </Button>
        </Form>
    );
}
