import { Form, Link } from "@inertiajs/react";
import { FileSpreadsheet, FileText, Search } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { index as studentIndex } from "@/routes/student/kompen-respon";
import type { FilterOptions, Filters } from "../types";
import { queueExport } from "../../export/export-progress-panel";

export function FilterPanel({
    activeTab,
    isAdmin,
    filters,
    filterOptions,
}: {
    activeTab: "students" | "details";
    isAdmin: boolean;
    filters: Filters;
    filterOptions: FilterOptions;
}): React.JSX.Element {
    const [tingkat, setTingkat] = useState(filters.tingkat?.toString() ?? "");
    const [kelas, setKelas] = useState(filters.kelas ?? "");
    const [periode, setPeriode] = useState(filters.periode_semester ?? "");
    const [perPage, setPerPage] = useState(
        filters.per_page?.toString() ?? "15",
    );
    const indexAction = isAdmin ? adminIndex : studentIndex;

    return (
        <Form
            {...indexAction.form()}
            className="grid grid-cols-2 gap-3.5 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl lg:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(140px,auto))]"
        >
            <input name="tab" type="hidden" value={activeTab} />
            <input name="tingkat" type="hidden" value={tingkat} />
            <input name="kelas" type="hidden" value={kelas} />
            <input name="periode_semester" type="hidden" value={periode} />
            <input name="per_page" type="hidden" value={perPage} />

            {/* Search Input */}
            <Input
                name="search"
                placeholder="Cari nama atau NIM..."
                defaultValue={filters.search}
                className="col-span-2 rounded-2xl border-[#8AAEE0] bg-white/90 py-2.5 text-xs text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886] lg:col-span-1"
            />

            {/* Select Tingkat */}
            <Select
                value={tingkat || undefined}
                onValueChange={(value) =>
                    setTingkat(value === "all" ? "" : value)
                }
            >
                <SelectTrigger className="w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                    <SelectValue placeholder="Semua tingkat" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua tingkat
                    </SelectItem>
                    {filterOptions.tingkat.map((option) => (
                        <SelectItem
                            key={option}
                            value={option.toString()}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Tingkat {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Select Kelas */}
            <Select
                value={kelas || undefined}
                onValueChange={(value) =>
                    setKelas(value === "all" ? "" : value)
                }
            >
                <SelectTrigger className="w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                    <SelectValue placeholder="Semua kelas" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua kelas
                    </SelectItem>
                    {filterOptions.kelas.map((option) => (
                        <SelectItem
                            key={option}
                            value={option}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Select Periode */}
            <Select
                value={periode || undefined}
                onValueChange={(value) =>
                    setPeriode(value === "all" ? "" : value)
                }
            >
                <SelectTrigger className="col-span-2 w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white lg:col-span-1">
                    <SelectValue placeholder="Semua periode upload" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua periode upload
                    </SelectItem>
                    {filterOptions.periode_semester.map((option) => (
                        <SelectItem
                            key={option}
                            value={option}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Controls Row */}
            <div className="col-span-2 flex gap-2 lg:col-span-1">
                <Select value={perPage} onValueChange={setPerPage}>
                    <SelectTrigger className="w-24 rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        {[15, 30, 50, 100].map((option) => (
                            <SelectItem
                                key={option}
                                value={option.toString()}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {option} data
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="submit"
                    className="grow rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] active:scale-95"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
            </div>

            {/* Footer Row Actions */}
            <div className="col-span-full flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] pt-2">
                <Link
                    href={indexAction.url({ query: { tab: activeTab } })}
                    className="text-xs font-bold text-[#395886] underline decoration-[#8AAEE0] underline-offset-4 transition-colors hover:text-[#628ECB]"
                >
                    Reset filter
                </Link>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-xs font-semibold text-[#395886]/70">
                        Tanpa filter berarti seluruh data.
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => queueExport(activeTab, "xlsx", filters)}
                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs hover:bg-[#395886] hover:text-white"
                    >
                        <FileSpreadsheet className="mr-1.5 size-3.5" />
                        Buat XLSX
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => queueExport(activeTab, "pdf", filters)}
                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs hover:bg-[#395886] hover:text-white"
                    >
                        <FileText className="mr-1.5 size-3.5" />
                        Buat PDF
                    </Button>
                </div>
            </div>
        </Form>
    );
}
