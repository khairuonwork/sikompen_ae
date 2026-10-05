import { Form, router } from "@inertiajs/react";
import {
    CalendarClock,
    ChevronDown,
    Clock3,
    FileSpreadsheet,
    FileText,
    Save,
    Search,
    Archive,
    Trash2,
    X,
} from "lucide-react";
import { useState } from "react";
import {
    destroyWarning,
    finalizeCutoff,
    storeCutoff,
    updateWarning,
} from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { datetimeLocalValue, number } from "../shared/lib/formatters";
import type {
    Cutoff,
    FilterOptions,
    Filters,
    Pagination,
    Student,
    Warning,
} from "../shared/types";
import { Pager } from "../shared/components/pagination";
import { queueExport } from "../export/export-progress-panel";

export function WarningStatusBadge({
    warning,
}: {
    warning: Warning;
}): React.JSX.Element {
    const label =
        warning.resolution === "completed" &&
        warning.letter_status === "not_created"
            ? "Kompen selesai · SP tidak diterbitkan"
            : warning.resolution === "completed"
              ? "Kompen selesai setelah SP"
              : warning.letter_status === "issued"
                ? "SP terbit"
                : warning.letter_status === "draft"
                  ? "Draft SP"
                  : warning.letter_status === "cancelled"
                    ? "SP di-rollback"
                    : "Belum dibuat";
    return (
        <span
            className={cn(
                "inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold",
                warning.resolution === "completed"
                    ? "bg-emerald-100 text-emerald-700"
                    : warning.letter_status === "issued"
                      ? "bg-rose-100 text-rose-700"
                      : warning.letter_status === "cancelled"
                        ? "bg-slate-100 text-slate-700"
                        : warning.letter_status === "draft"
                          ? "bg-amber-100 text-amber-700"
                          : "bg-[#B1C9EF]/40 text-[#395886]",
            )}
        >
            {label}
        </span>
    );
}

export function WarningTable({
    data,
    onSelect,
}: {
    data: Pagination<Warning>;
    onSelect: (warning: Warning) => void;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[850px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Mahasiswa</th>
                            <th className="px-4 py-3">Kelas</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Sisa[j]</th>
                            <th className="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((warning) => (
                            <tr
                                key={warning.id}
                                onClick={() => onSelect(warning)}
                                className="cursor-pointer hover:bg-[#B1C9EF]/10"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-bold text-[#395886]">
                                        {warning.nama_mahasiswa}
                                    </p>
                                    <p className="font-mono text-xs text-[#628ECB]">
                                        {warning.nim}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold">
                                    {warning.kelas}
                                </td>
                                <td className="px-4 py-3">
                                    <WarningStatusBadge warning={warning} />
                                </td>
                                <td className="px-4 py-3 text-right font-mono text-xs">
                                    {number(
                                        String(
                                            warning.snapshot.sisa_hutang_jam,
                                        ),
                                    )}
                                </td>
                                <td className="max-w-xs px-4 py-3 text-xs text-[#395886]/70">
                                    {warning.reason ?? "—"}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function WarningCandidateTable({
    data,
}: {
    data: Pagination<Student>;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-amber-200 bg-white/80 shadow-sm">
            <div className="border-b border-amber-100 bg-amber-50 px-4 py-3">
                <p className="text-sm font-extrabold text-amber-900">Kandidat SP</p>
                <p className="mt-0.5 text-xs text-amber-800/80">
                    Belum diterbitkan dan belum dikirim melalui API. Finalisasi SP periode untuk menetapkan seluruh kandidat ini sebagai SP aktif.
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Mahasiswa</th>
                            <th className="px-4 py-3">Kelas</th>
                            <th className="px-4 py-3">Periode</th>
                            <th className="px-4 py-3 text-right">Sisa jam</th>
                            <th className="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr key={student.id} className="hover:bg-amber-50/50">
                                <td className="px-4 py-3">
                                    <p className="font-bold text-[#395886]">{student.nama_mahasiswa}</p>
                                    <p className="font-mono text-xs text-[#628ECB]">{student.nim}</p>
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold text-[#395886]">{student.kelas}</td>
                                <td className="px-4 py-3 text-xs font-semibold text-[#395886]">{student.periode_semester}</td>
                                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-[#395886]">{number(student.effective_sisa_hutang_jam)}</td>
                                <td className="px-4 py-3">
                                    <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-extrabold text-amber-800">Kandidat SP</span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

export function WarningPanel({
    cutoffs,
    filterOptions,
    filters,
    warnings,
    warningCandidates,
    rolledBackWarnings,
    selectedWarning,
    onSelectWarning,
    onCloseWarning,
}: {
    cutoffs: Cutoff[];
    filterOptions: FilterOptions;
    filters: Filters;
    warnings: Pagination<Warning> | null;
    warningCandidates: Pagination<Student> | null;
    rolledBackWarnings: Pagination<Warning> | null;
    selectedWarning: Warning | null;
    onSelectWarning: (warning: Warning) => void;
    onCloseWarning: () => void;
}): React.JSX.Element {
    const selectedWarningPeriod = filters.periode_semester ?? "";
    const [editingCutoffPeriod, setEditingCutoffPeriod] = useState(
        selectedWarningPeriod,
    );
    const [isCutoffEditorOpen, setIsCutoffEditorOpen] = useState(
        cutoffs.length === 0,
    );
    const [isCutoffHistoryOpen, setIsCutoffHistoryOpen] = useState(false);
    const [selectedFilterClass, setSelectedFilterClass] = useState(
        filters.kelas ?? "",
    );
    const selectedCutoff = cutoffs.find(
        (cutoff) => cutoff.periode_semester === selectedWarningPeriod,
    );
    const editingCutoff = cutoffs.find(
        (cutoff) => cutoff.periode_semester === editingCutoffPeriod,
    );
    const isSelectedCutoffElapsed =
        selectedCutoff !== undefined &&
        new Date(selectedCutoff.deadline_at).getTime() <= Date.now();
    const isEditingCutoffElapsed =
        editingCutoff !== undefined &&
        new Date(editingCutoff.deadline_at).getTime() <= Date.now();

    function openCutoffEditor(period: string): void {
        setEditingCutoffPeriod(period);
        setIsCutoffEditorOpen(true);
    }

    function selectWarningPeriod(period: string): void {
        router.get(
            adminIndex.url({
                query: {
                    tab: "warnings",
                    periode_semester: period,
                    kelas: filters.kelas,
                    search: filters.search,
                    per_page: filters.per_page,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    }

    return (
        <section className="grid gap-4">
            <section className="order-2 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-base font-extrabold text-[#395886]">
                            List Mahasiswa
                        </h2>
                        <p className="mt-0.5 text-xs text-[#395886]/70">
                            Setelah cutoff lewat, mahasiswa dengan sisa jam akan
                            tampil sebagai kandidat SP. Mereka masuk API hanya
                            setelah Finalisasi SP dilakukan.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                queueExport("warnings", "xlsx", filters)
                            }
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <FileSpreadsheet className="mr-1.5 size-3.5" />
                            Buat XLSX
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                queueExport("warnings", "pdf", filters)
                            }
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <FileText className="mr-1.5 size-3.5" />
                            Buat PDF
                        </Button>
                    </div>
                </div>
                <Form
                    {...adminIndex.form()}
                    className="mt-4 grid gap-3 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 p-3 md:grid-cols-[minmax(220px,1fr)_minmax(150px,0.7fr)_auto] md:items-end"
                >
                <input name="tab" type="hidden" value="warnings" />
                <input name="kelas" type="hidden" value={selectedFilterClass} />
                <input
                    name="periode_semester"
                    type="hidden"
                    value={selectedWarningPeriod}
                />
                <Input
                    name="search"
                    defaultValue={filters.search}
                    maxLength={100}
                    placeholder="Cari nama atau NIM"
                    className="h-10 rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                />
                <Select
                    value={selectedFilterClass || "all"}
                    onValueChange={(value) =>
                        setSelectedFilterClass(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                        <SelectValue placeholder="Semua kelas" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua kelas
                        </SelectItem>
                        {filterOptions.kelas.map((kelas) => (
                            <SelectItem
                                key={kelas}
                                value={kelas}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {kelas}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="submit"
                    className="rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:text-white active:scale-95"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
                </Form>
            </section>
            <section className="order-1 grid gap-4 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h2 className="text-lg font-extrabold text-[#395886]">
                            Batas waktu periode
                        </h2>
                        <p className="mt-1 text-xs text-[#395886]/70">
                            Timezone operasional: Asia/Jakarta.
                        </p>
                    </div>
                    {selectedCutoff ? (
                        <span
                            className={cn(
                                "w-fit rounded-full px-3 py-1 text-xs font-bold",
                                isSelectedCutoffElapsed
                                    ? "bg-amber-100 text-amber-800"
                                    : "bg-emerald-100 text-emerald-700",
                            )}
                        >
                            {isSelectedCutoffElapsed
                                ? "Cutoff telah lewat"
                                : "Cutoff aktif"}
                        </span>
                    ) : null}
                </div>

                {cutoffs.length ? (
                    <div className="grid gap-1.5 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 p-3 sm:max-w-md">
                        <Label className="text-xs font-bold text-[#395886]">
                            Periode SP
                        </Label>
                        <Select
                            value={selectedWarningPeriod}
                            onValueChange={selectWarningPeriod}
                        >
                            <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                                <SelectValue placeholder="Pilih periode SP" />
                            </SelectTrigger>
                            <SelectContent className="rounded-2xl text-xs font-semibold">
                                {cutoffs.map((cutoff) => (
                                    <SelectItem
                                        key={cutoff.id}
                                        value={cutoff.periode_semester}
                                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                                    >
                                        {cutoff.periode_semester}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-[11px] leading-relaxed text-[#395886]/70">
                            Kandidat, SP aktif, riwayat, dan ekspor di bawah
                            selalu memakai periode ini.
                        </p>
                    </div>
                ) : null}

                {selectedCutoff ? (
                    <div className="flex flex-col justify-between gap-4 rounded-2xl border border-[#8AAEE0] bg-[#B1C9EF]/20 p-4 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#395886] text-white">
                                <CalendarClock className="size-4" />
                            </div>
                            <div>
                                <p className="text-[11px] font-black tracking-[0.14em] text-[#628ECB] uppercase">
                                    Periode SP terpilih
                                </p>
                                <p className="mt-1 text-base font-extrabold text-[#395886]">
                                    {selectedCutoff.periode_semester}
                                </p>
                                <p className="mt-1 text-sm font-semibold text-[#395886]/75">
                                    Batas waktu {datetimeLocalValue(selectedCutoff.deadline_at, selectedCutoff.timezone).replace("T", ", ")}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                openCutoffEditor(selectedCutoff.periode_semester)
                            }
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            Ubah batas waktu
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col justify-between gap-3 rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/60 p-4 sm:flex-row sm:items-center">
                        <div>
                            <p className="text-sm font-bold text-[#395886]">
                                Belum ada cutoff aktif
                            </p>
                            <p className="mt-1 text-xs text-[#395886]/70">
                                Tentukan periode dan batas waktu sebelum proses
                                fiksasi dilakukan.
                            </p>
                        </div>
                        <Button
                            type="button"
                            onClick={() => {
                                setEditingCutoffPeriod("");
                                setIsCutoffEditorOpen(true);
                            }}
                            className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                        >
                            Atur batas waktu
                        </Button>
                    </div>
                )}

                {cutoffs.length ? (
                    <section className="rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 p-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setIsCutoffHistoryOpen(
                                    (isOpen) => !isOpen,
                                )
                            }
                            className="h-10 w-full justify-between rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                            aria-expanded={isCutoffHistoryOpen}
                        >
                            Riwayat batas waktu periode ({cutoffs.length})
                            <ChevronDown
                                className={cn(
                                    "size-4 transition-transform duration-200",
                                    isCutoffHistoryOpen && "rotate-180",
                                )}
                            />
                        </Button>
                        {isCutoffHistoryOpen ? (
                            <div className="mt-3 grid gap-2">
                            {cutoffs.map((cutoff) => {
                                const isElapsed =
                                    new Date(cutoff.deadline_at).getTime() <=
                                    Date.now();

                                return (
                                    <div
                                        key={cutoff.id}
                                        className="flex flex-col justify-between gap-3 rounded-xl border border-[#D5DEEF] bg-white p-3 sm:flex-row sm:items-center"
                                    >
                                        <div>
                                            <p className="text-xs font-bold text-[#395886]">
                                                {cutoff.periode_semester}
                                            </p>
                                            <p className="mt-0.5 text-xs text-[#395886]/70">
                                                {datetimeLocalValue(
                                                    cutoff.deadline_at,
                                                    cutoff.timezone,
                                                ).replace("T", ", ")}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={cn("rounded-full px-2.5 py-1 text-[11px] font-bold", isElapsed ? "bg-amber-100 text-amber-800" : "bg-emerald-100 text-emerald-700")}>
                                                {isElapsed
                                                    ? "Cutoff lewat"
                                                    : "Aktif"}
                                            </span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    cutoff.periode_semester ===
                                                    selectedWarningPeriod
                                                }
                                                onClick={() =>
                                                    selectWarningPeriod(
                                                        cutoff.periode_semester,
                                                    )
                                                }
                                                className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                            >
                                                {cutoff.periode_semester ===
                                                selectedWarningPeriod
                                                    ? "Dipilih"
                                                    : "Pilih"}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                            </div>
                        ) : null}
                    </section>
                ) : null}

                {isCutoffEditorOpen ? (
                    <Form
                        {...storeCutoff.form()}
                        className="grid gap-3 rounded-2xl border border-[#8AAEE0]/70 bg-white p-4 md:grid-cols-[minmax(180px,0.8fr)_minmax(220px,1fr)_auto] md:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-bold text-[#395886]">
                                        Periode
                                    </Label>
                                    <input
                                        name="periode_semester"
                                        type="hidden"
                                        value={editingCutoffPeriod}
                                    />
                                    <Select
                                        value={
                                            editingCutoffPeriod ||
                                            "placeholder"
                                        }
                                        onValueChange={(value) =>
                                            setEditingCutoffPeriod(
                                                value === "placeholder"
                                                    ? ""
                                                    : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                                            <SelectValue placeholder="Pilih periode" />
                                        </SelectTrigger>
                                        <SelectContent className="rounded-2xl text-xs font-semibold">
                                            <SelectItem value="placeholder">
                                                Pilih periode
                                            </SelectItem>
                                            {filterOptions.periode_semester.map(
                                                (period) => (
                                                    <SelectItem
                                                        key={period}
                                                        value={period}
                                                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                                                    >
                                                        {period}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs font-bold text-[#395886]">
                                        Tanggal dan waktu batas
                                    </Label>
                                    <Input
                                        key={editingCutoff?.id ?? "new-cutoff"}
                                        name="deadline_at"
                                        type="datetime-local"
                                        required
                                        step={60}
                                        defaultValue={
                                            editingCutoff
                                                ? datetimeLocalValue(
                                                      editingCutoff.deadline_at,
                                                      editingCutoff.timezone,
                                                  )
                                                : ""
                                        }
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        disabled={processing}
                                        type="submit"
                                        className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                    >
                                        <Clock3 className="mr-2 size-4" />
                                        {isEditingCutoffElapsed
                                            ? "Perpanjang cutoff"
                                            : "Simpan batas waktu"}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsCutoffEditorOpen(false)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
                                    >
                                        Batal
                                    </Button>
                                </div>
                                {errors.deadline_at || errors.periode_semester ? (
                                    <p className="text-xs font-bold text-rose-600 md:col-span-3">
                                        {errors.deadline_at ?? errors.periode_semester}
                                    </p>
                                ) : null}
                            </>
                        )}
                    </Form>
                ) : null}

                {selectedCutoff && isSelectedCutoffElapsed ? (
                    <div className="flex flex-col justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center">
                        <p className="text-xs leading-relaxed font-medium text-amber-900">
                            Cutoff {selectedCutoff.periode_semester} telah
                            lewat. Perpanjang bila masa pengerjaan dibuka
                            kembali, atau finalisasi untuk menerbitkan SP bagi
                            seluruh kandidat yang masih memiliki sisa jam.
                        </p>
                        <Button
                            type="button"
                            onClick={() => {
                                if (window.confirm("Finalisasi SP periode ini? Setiap kandidat dengan sisa jam akan menerima status SP aktif dan mulai tersedia pada API. Data serta cutoff tetap dapat diperbaiki setelahnya.")) {
                                    router.post(finalizeCutoff.url(selectedCutoff.id));
                                }
                            }}
                            className="shrink-0 rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                        >
                            <Archive className="mr-1.5 size-3.5" />
                            Finalisasi SP
                        </Button>
                    </div>
                ) : null}
            </section>
            {selectedWarning ? (
                <section className="order-4 grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white p-5 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                                SP dipilih
                            </p>
                            <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                                {selectedWarning.nama_mahasiswa} ·{" "}
                                {selectedWarning.nim}
                            </h3>
                        </div>
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={onCloseWarning}
                            className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]"
                            aria-label="Tutup panel SP"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                    <div className="grid gap-4 xl:grid-cols-2">
                        <Form
                            {...updateWarning.form(selectedWarning.id)}
                            className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm font-bold text-[#395886]">
                                        Perbarui catatan SP
                                    </p>
                                    <Input
                                        name="reason"
                                        required
                                        minLength={5}
                                        maxLength={1000}
                                        defaultValue={
                                            selectedWarning.reason ?? ""
                                        }
                                        placeholder="Catatan atau alasan perubahan SP"
                                    />
                                    <Button
                                        disabled={processing}
                                        type="submit"
                                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                    >
                                        <Save className="mr-2 size-4" />
                                        Simpan catatan
                                    </Button>
                                </>
                            )}
                        </Form>
                        <Form
                            {...destroyWarning.form(selectedWarning.id)}
                            className="grid gap-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4"
                            onBefore={() =>
                                window.confirm(
                                    "Rollback SP ini? Riwayat surat dan aktivitas tetap tersimpan, tetapi mahasiswa tidak lagi tampil sebagai SP aktif atau pada API SP.",
                                )
                            }
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm font-bold text-rose-800">
                                        Rollback SP
                                    </p>
                                    <p className="text-xs text-rose-700">
                                        Rollback tidak menghapus jejak audit.
                                        Riwayat surat dan aktivitas tetap dapat
                                        ditinjau dari profil mahasiswa.
                                    </p>
                                    <Input
                                        name="reason"
                                        required
                                        minLength={5}
                                        maxLength={1000}
                                        placeholder="Alasan rollback SP"
                                    />
                                    <Button
                                        disabled={
                                            processing ||
                                            selectedWarning.letter_status ===
                                                "cancelled"
                                        }
                                        type="submit"
                                        variant="outline"
                                        className="w-fit rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                    >
                                        <Trash2 className="mr-2 size-4" />
                                        Rollback SP
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            ) : null}
            <div className="order-4">
                {warningCandidates?.data.length ? (
                    <WarningCandidateTable data={warningCandidates} />
                ) : null}
                {warnings?.data.length ? (
                    <div className={cn(warningCandidates?.data.length ? "mt-4" : "")}>
                        <WarningTable data={warnings} onSelect={onSelectWarning} />
                    </div>
                ) : warningCandidates?.data.length ? null : (
                    <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-semibold text-[#395886]/70">
                        Belum ada kandidat atau SP aktif pada filter yang dipilih.
                    </div>
                )}
            </div>
            {rolledBackWarnings?.data.length ? (
                <section className="order-5 grid gap-3 rounded-3xl border border-[#D5DEEF] bg-white/80 p-5 shadow-sm">
                    <div>
                        <h2 className="text-base font-extrabold text-[#395886]">
                            Riwayat Surat Peringatan
                        </h2>
                        <p className="mt-0.5 text-xs text-[#395886]/70">
                            SP yang di-rollback tetap tercatat untuk audit,
                            tetapi tidak dihitung sebagai SP aktif atau dikirim
                            melalui API.
                        </p>
                    </div>
                    <WarningTable
                        data={rolledBackWarnings}
                        onSelect={onSelectWarning}
                    />
                </section>
            ) : null}
        </section>
    );
}
