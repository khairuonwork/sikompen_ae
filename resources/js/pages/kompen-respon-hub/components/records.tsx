import { Form, Link } from "@inertiajs/react";
import {
    FileSpreadsheet,
    FileText,
    PencilLine,
    Save,
    Search,
    X,
} from "lucide-react";
import { useState } from "react";
import {
    storeDetailOverride,
    storeProgress,
    storeSummaryOverride,
} from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { index as studentIndex } from "@/routes/student/kompen-respon";
import type { Detail, FilterOptions, Filters, Student } from "../types";
import { queueExport } from "./tasks";

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

export function EditModeControl({
    enabled,
    onChange,
}: {
    enabled: boolean;
    onChange: (enabled: boolean) => void;
}): React.JSX.Element {
    return (
        <Button
            type="button"
            variant="outline"
            onClick={() => onChange(!enabled)}
            className={cn(
                "rounded-2xl border-[#8AAEE0] bg-white px-4 text-xs font-bold text-[#395886]",
                enabled &&
                    "border-[#395886] bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white",
            )}
        >
            <PencilLine className="mr-2 size-4" />
            {enabled ? "Mode perbaikan aktif" : "Mode perbaikan data"}
        </Button>
    );
}

export function StudentCorrectionPanel({
    student,
    onClose,
}: {
    student: Student;
    onClose: () => void;
}): React.JSX.Element {
    return (
        <section className="grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                        Mahasiswa dipilih
                    </p>
                    <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                        {student.nama_mahasiswa} · {student.nim}
                    </h3>
                    <p className="mt-1 text-xs text-[#395886]/70">
                        Simpan setiap koreksi dengan alasan agar jejak audit
                        tetap lengkap.
                    </p>
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={onClose}
                    className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]"
                    aria-label="Tutup panel perbaikan"
                >
                    <X className="size-4" />
                </Button>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <Form
                    {...storeProgress.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Progres pengerjaan
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="kompensasi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_kompensasi_dikerjakan_jam
                                    }
                                    placeholder="Jam Kompen"
                                />
                                <Input
                                    name="responsi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_responsi_dikerjakan_jam
                                    }
                                    placeholder="Jam Responsi"
                                />
                            </div>
                            <Input
                                name="last_worked_at"
                                type="datetime-local"
                                required
                                defaultValue={
                                    student.last_worked_at?.slice(0, 16) ?? ""
                                }
                            />
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan perubahan"
                            />
                            {errors.kompensasi_dikerjakan_jam ||
                            errors.last_worked_at ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.kompensasi_dikerjakan_jam ??
                                        errors.last_worked_at}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan progres
                            </Button>
                        </>
                    )}
                </Form>
                <Form
                    {...storeSummaryOverride.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Koreksi total Kompen / Responsi
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="total_kompensasi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_kompensasi_jam
                                    }
                                    placeholder="Total Kompen"
                                />
                                <Input
                                    name="total_responsi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_responsi_jam
                                    }
                                    placeholder="Total Responsi"
                                />
                            </div>
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan koreksi"
                            />
                            {errors.total_kompensasi_jam ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.total_kompensasi_jam}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan koreksi
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}

export function DetailCorrectionPanel({
    detail,
    onClose,
}: {
    detail: Detail;
    onClose: () => void;
}): React.JSX.Element {
    return (
        <Form
            {...storeDetailOverride.form(detail.id)}
            className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm md:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    <div className="flex items-start justify-between gap-4 md:col-span-2">
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                                Detail dipilih
                            </p>
                            <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                                {detail.nama_mahasiswa} · {detail.mata_kuliah}
                            </h3>
                        </div>
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={onClose}
                            className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]"
                            aria-label="Tutup panel perbaikan"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                    <Input
                        name="tanggal"
                        type="date"
                        required
                        defaultValue={detail.tanggal}
                    />
                    <Input
                        name="mata_kuliah"
                        required
                        maxLength={100}
                        defaultValue={detail.mata_kuliah}
                    />
                    <Input
                        name="nama_dosen"
                        required
                        maxLength={100}
                        defaultValue={detail.nama_dosen}
                    />
                    <Input
                        name="jenis_pertemuan"
                        required
                        maxLength={20}
                        defaultValue={detail.jenis_pertemuan}
                    />
                    <Input
                        name="presensi"
                        required
                        maxLength={20}
                        defaultValue={detail.presensi}
                    />
                    <Input
                        name="menit_keterlambatan"
                        type="number"
                        min="0"
                        defaultValue={detail.menit_keterlambatan}
                    />
                    <Input
                        name="jam_kompensasi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_kompensasi}
                    />
                    <Input
                        name="jam_responsi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_responsi}
                    />
                    <Input
                        name="keterangan"
                        className="md:col-span-2"
                        maxLength={2000}
                        defaultValue={detail.keterangan ?? ""}
                        placeholder="Keterangan"
                    />
                    <Input
                        name="reason"
                        className="md:col-span-2"
                        required
                        minLength={5}
                        maxLength={1000}
                        placeholder="Alasan koreksi"
                    />
                    {errors.tanggal ? (
                        <p className="text-xs font-bold text-rose-600">
                            {errors.tanggal}
                        </p>
                    ) : null}
                    <Button
                        disabled={processing}
                        type="submit"
                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                    >
                        <Save className="mr-2 size-4" />
                        Simpan koreksi detail
                    </Button>
                </>
            )}
        </Form>
    );
}
