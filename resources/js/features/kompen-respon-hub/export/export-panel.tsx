import { FileSpreadsheet, FileText, Filter, RotateCcw } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { queueExport } from "./export-progress-panel";
import type { ExportTask, FilterOptions, Filters } from "../shared/types";

type ExportResource = ExportTask["resource"];

const resourceLabels: Record<ExportResource, string> = {
    students: "Kompen dan Respon",
    details: "Detail Kompen",
    warnings: "Surat Peringatan",
};

export function ExportPanel({
    defaultResource,
    filterOptions,
    isAdmin,
}: {
    defaultResource: ExportResource;
    filterOptions: FilterOptions;
    isAdmin: boolean;
}): React.JSX.Element {
    const [isOpen, setIsOpen] = useState(false);
    const [resource, setResource] = useState<ExportResource>(defaultResource);
    const [search, setSearch] = useState("");
    const [tingkat, setTingkat] = useState("");
    const [kelas, setKelas] = useState("");
    const [periode, setPeriode] = useState("");

    function filters(): Filters {
        return {
            search: search.trim() || undefined,
            tingkat: tingkat === "" ? undefined : Number(tingkat),
            kelas: kelas || undefined,
            periode_semester: periode || undefined,
        };
    }

    function resetFilters(): void {
        setSearch("");
        setTingkat("");
        setKelas("");
        setPeriode("");
    }

    return (
        <section className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Ekspor data
                    </h2>
                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                        Filter ekspor terpisah dari filter tabel, sehingga data
                        yang sedang Anda cek tidak berubah.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsOpen((open) => !open)}
                    className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                >
                    <Filter className="mr-1.5 size-3.5" />
                    {isOpen ? "Tutup ekspor" : "Atur ekspor"}
                </Button>
            </div>

            {isOpen ? (
                <div className="mt-4 grid gap-3 border-t border-[#D5DEEF] pt-4 lg:grid-cols-2">
                    <Select
                        value={resource}
                        onValueChange={(value) =>
                            setResource(value as ExportResource)
                        }
                    >
                        <SelectTrigger className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            <SelectItem value="students">
                                Kompen dan Respon
                            </SelectItem>
                            <SelectItem value="details">
                                Detail Kompen
                            </SelectItem>
                            {isAdmin ? (
                                <SelectItem value="warnings">
                                    Surat Peringatan
                                </SelectItem>
                            ) : null}
                        </SelectContent>
                    </Select>
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        maxLength={100}
                        placeholder="Cari nama atau NIM untuk ekspor"
                        className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] placeholder:text-[#395886]/40"
                    />
                    <Select
                        value={tingkat || undefined}
                        onValueChange={(value) =>
                            setTingkat(value === "all" ? "" : value)
                        }
                    >
                        <SelectTrigger className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]">
                            <SelectValue placeholder="Semua tingkat" />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            <SelectItem value="all">Semua tingkat</SelectItem>
                            {filterOptions.tingkat.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    Tingkat {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={kelas || undefined}
                        onValueChange={(value) =>
                            setKelas(value === "all" ? "" : value)
                        }
                    >
                        <SelectTrigger className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]">
                            <SelectValue placeholder="Semua kelas" />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            <SelectItem value="all">Semua kelas</SelectItem>
                            {filterOptions.kelas.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={periode || undefined}
                        onValueChange={(value) =>
                            setPeriode(value === "all" ? "" : value)
                        }
                    >
                        <SelectTrigger className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]">
                            <SelectValue placeholder="Semua periode" />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            <SelectItem value="all">Semua periode</SelectItem>
                            {filterOptions.periode_semester.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="flex flex-wrap items-center justify-between gap-2 lg:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={resetFilters}
                            className="h-9 rounded-xl text-xs font-bold text-[#395886] hover:bg-[#B1C9EF]/25"
                        >
                            <RotateCcw className="mr-1.5 size-3.5" />
                            Reset filter ekspor
                        </Button>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => queueExport(resource, "xlsx", filters())}
                                className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                            >
                                <FileSpreadsheet className="mr-1.5 size-3.5" />
                                Buat XLSX
                            </Button>
                            <Button
                                type="button"
                                onClick={() => queueExport(resource, "pdf", filters())}
                                className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                            >
                                <FileText className="mr-1.5 size-3.5" />
                                Buat PDF
                            </Button>
                        </div>
                    </div>
                    <p className="text-xs font-medium text-[#395886]/70 lg:col-span-2">
                        {search || tingkat || kelas || periode
                            ? `Ekspor ${resourceLabels[resource]} hanya memakai filter di panel ini.`
                            : `Ekspor ${resourceLabels[resource]} akan memuat seluruh data yang diizinkan.`}
                    </p>
                </div>
            ) : null}
        </section>
    );
}
