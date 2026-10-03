import { Form, Link, router } from "@inertiajs/react";
import {
    CalendarClock,
    Clock3,
    FileSpreadsheet,
    FileText,
    Save,
    Search,
    ShieldAlert,
    Trash2,
    X,
} from "lucide-react";
import { useState } from "react";
import {
    closeCutoff,
    destroyWarning,
    storeCutoff,
    storeWarning,
    updateWarning,
} from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
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
import { EmptyTableState } from "../shared/components/feedback";
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
                    ? "SP dibatalkan"
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

export function WarningCandidateTable({
    data,
    selectedStudentId,
    onSelect,
}: {
    data: Pagination<Student>;
    selectedStudentId: number | null;
    onSelect: (student: Student) => void;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="flex flex-col justify-between gap-2 border-b border-[#F0F3FA] px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Mahasiswa belum memiliki SP
                    </h2>
                    <p className="mt-0.5 text-xs text-[#395886]/70">
                        Sisa jam masih ada setelah cutoff. Pilih mahasiswa untuk
                        membuat atau memperbarui draft SP.
                    </p>
                </div>
                <span className="w-fit rounded-full bg-[#B1C9EF]/40 px-3 py-1 text-xs font-bold text-[#395886]">
                    Setelah cutoff
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Mahasiswa</th>
                            <th className="px-4 py-3">Kelas</th>
                            <th className="px-4 py-3">Periode</th>
                            <th className="px-4 py-3 text-right">Sisa[j]</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr
                                key={student.id}
                                className={cn(
                                    "transition-colors hover:bg-[#B1C9EF]/10",
                                    selectedStudentId === student.id &&
                                        "bg-[#B1C9EF]/20",
                                )}
                            >
                                <td className="px-4 py-3">
                                    <p className="font-bold text-[#395886]">
                                        {student.nama_mahasiswa}
                                    </p>
                                    <p className="font-mono text-xs text-[#628ECB]">
                                        {student.nim}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold text-[#395886]">
                                    {student.kelas}
                                </td>
                                <td className="px-4 py-3 text-xs text-[#395886]/70">
                                    {student.periode_semester}
                                </td>
                                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-[#395886]">
                                    {number(student.effective_sisa_hutang_jam)}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onSelect(student)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                    >
                                        {selectedStudentId === student.id
                                            ? "Dipilih"
                                            : "Pilih"}
                                    </Button>
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

export function WarningPanel({
    cutoffs,
    filterOptions,
    filters,
    warnings,
    fixedCandidates,
    selectedWarning,
    onSelectWarning,
    onCloseWarning,
}: {
    cutoffs: Cutoff[];
    filterOptions: FilterOptions;
    filters: Filters;
    warnings: Pagination<Warning> | null;
    fixedCandidates: Pagination<Student> | null;
    selectedWarning: Warning | null;
    onSelectWarning: (warning: Warning) => void;
    onCloseWarning: () => void;
}): React.JSX.Element {
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(
        null,
    );
    const [selectedCutoffPeriod, setSelectedCutoffPeriod] = useState(
        filters.periode_semester ?? "",
    );
    const [selectedFilterClass, setSelectedFilterClass] = useState(
        filters.kelas ?? "",
    );
    const [selectedFilterPeriod, setSelectedFilterPeriod] = useState(
        filters.periode_semester ?? "",
    );
    const selectedCutoff = cutoffs.find(
        (cutoff) => cutoff.periode_semester === selectedCutoffPeriod,
    );

    return (
        <section className="grid gap-4">
            <Form
                {...adminIndex.form()}
                className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(220px,1fr)_minmax(150px,0.7fr)_minmax(180px,0.85fr)_auto] md:items-end"
            >
                <input name="tab" type="hidden" value="warnings" />
                <input name="kelas" type="hidden" value={selectedFilterClass} />
                <input
                    name="periode_semester"
                    type="hidden"
                    value={selectedFilterPeriod}
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
                <Select
                    value={selectedFilterPeriod || "all"}
                    onValueChange={(value) =>
                        setSelectedFilterPeriod(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                        <SelectValue placeholder="Semua periode" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua periode
                        </SelectItem>
                        {filterOptions.periode_semester.map((period) => (
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
                <Button
                    type="submit"
                    className="rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:text-white active:scale-95"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
            </Form>
            <div className="grid gap-4 xl:grid-cols-2">
                <Form
                    {...storeCutoff.form()}
                    className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <h2 className="text-lg font-extrabold text-[#395886]">
                                    Batas waktu periode
                                </h2>
                                <p className="mt-1 text-xs text-[#395886]/70">
                                    Timezone operasional: Asia/Jakarta.
                                </p>
                            </div>
                            <select
                                name="periode_semester"
                                required
                                value={selectedCutoffPeriod}
                                onChange={(event) =>
                                    setSelectedCutoffPeriod(event.target.value)
                                }
                                className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                            >
                                <option value="">Pilih periode</option>
                                {filterOptions.periode_semester.map(
                                    (period) => (
                                        <option key={period} value={period}>
                                            {period}
                                        </option>
                                    ),
                                )}
                            </select>
                            <Input
                                key={selectedCutoff?.id ?? "new-cutoff"}
                                name="deadline_at"
                                type="datetime-local"
                                required
                                disabled={selectedCutoff?.closed_at !== null}
                                defaultValue={
                                    selectedCutoff
                                        ? datetimeLocalValue(
                                              selectedCutoff.deadline_at,
                                              selectedCutoff.timezone,
                                          )
                                        : ""
                                }
                            />
                            {errors.deadline_at ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.deadline_at}
                                </p>
                            ) : null}
                            <Button
                                disabled={
                                    processing ||
                                    selectedCutoff?.closed_at !== null
                                }
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                            >
                                <Clock3 className="mr-2 size-4" />
                                Simpan batas waktu
                            </Button>
                            {selectedCutoff ? (
                                <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/50 p-3">
                                    <p className="mr-auto text-xs font-semibold text-[#395886]/75">
                                        {selectedCutoff.closed_at
                                            ? `Periode ditutup pada ${new Date(selectedCutoff.closed_at).toLocaleString("id-ID", { timeZone: selectedCutoff.timezone })}.`
                                            : new Date(
                                                    selectedCutoff.deadline_at,
                                                ).getTime() > Date.now()
                                              ? `Penutupan tersedia setelah ${new Date(selectedCutoff.deadline_at).toLocaleString("id-ID", { timeZone: selectedCutoff.timezone })}.`
                                              : "Tutup periode untuk mengunci perubahan manual dan impor baru."}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            selectedCutoff.closed_at !== null ||
                                            new Date(
                                                selectedCutoff.deadline_at,
                                            ).getTime() > Date.now()
                                        }
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    "Tutup periode ini? Perubahan manual dan impor baru akan dikunci.",
                                                )
                                            ) {
                                                router.post(
                                                    closeCutoff.url(
                                                        selectedCutoff.id,
                                                    ),
                                                );
                                            }
                                        }}
                                        className="rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                    >
                                        <ShieldAlert className="mr-1.5 size-3.5" />
                                        Tutup periode
                                    </Button>
                                </div>
                            ) : null}
                            <div className="text-xs text-[#395886]/70">
                                {cutoffs.map((cutoff) => (
                                    <p key={cutoff.id}>
                                        {cutoff.periode_semester}:{" "}
                                        {new Date(
                                            cutoff.deadline_at,
                                        ).toLocaleString("id-ID", {
                                            timeZone: cutoff.timezone,
                                        })}
                                    </p>
                                ))}
                            </div>
                        </>
                    )}
                </Form>
                <div className="grid content-start gap-2 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm">
                    <h2 className="text-lg font-extrabold text-[#395886]">
                        Buat SP manual
                    </h2>
                    <p className="text-xs leading-relaxed text-[#395886]/70">
                        Pilih mahasiswa dengan sisa jam setelah cutoff. Form
                        pembuatan draft akan muncul setelah dipilih.
                    </p>
                </div>
            </div>
            {selectedStudent ? (
                <Form
                    {...storeWarning.form()}
                    className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white p-5 shadow-sm md:grid-cols-[1fr_minmax(240px,1.5fr)_auto]"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                                    Mahasiswa dipilih
                                </p>
                                <p className="mt-1 text-sm font-extrabold text-[#395886]">
                                    {selectedStudent.nama_mahasiswa} ·{" "}
                                    {selectedStudent.nim}
                                </p>
                                <p className="mt-1 text-xs text-[#395886]/70">
                                    Sisa{" "}
                                    {number(
                                        selectedStudent.effective_sisa_hutang_jam,
                                    )}{" "}
                                    jam.
                                </p>
                            </div>
                            <input
                                name="student_id"
                                type="hidden"
                                value={selectedStudent.id}
                            />
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan pembuatan draft SP"
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    disabled={processing}
                                    type="submit"
                                    className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                >
                                    <ShieldAlert className="mr-2 size-4" />
                                    Buat draft SP
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setSelectedStudent(null)}
                                    className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
                                >
                                    Batal
                                </Button>
                            </div>
                            {errors.student_id || errors.reason ? (
                                <p className="text-xs font-bold text-rose-600 md:col-span-3">
                                    {errors.student_id ?? errors.reason}
                                </p>
                            ) : null}
                        </>
                    )}
                </Form>
            ) : null}
            {fixedCandidates?.data.length ? (
                <WarningCandidateTable
                    data={fixedCandidates}
                    selectedStudentId={selectedStudent?.id ?? null}
                    onSelect={setSelectedStudent}
                />
            ) : (
                <div className="rounded-3xl border border-dashed border-[#8AAEE0] bg-white/80 p-6 text-center text-xs font-semibold text-[#395886]/70">
                    Tidak ada mahasiswa dengan sisa jam setelah cutoff pada
                    filter yang dipilih.
                </div>
            )}
            <div className="flex flex-col justify-between gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm sm:flex-row sm:items-center">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Arsip dan status SP
                    </h2>
                    <p className="mt-0.5 text-xs text-[#395886]/70">
                        Draft dan penerbitan SP setelah cutoff tetap
                        dikendalikan admin.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => queueExport("warnings", "xlsx", filters)}
                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <FileSpreadsheet className="mr-1.5 size-3.5" />
                        Buat XLSX
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => queueExport("warnings", "pdf", filters)}
                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <FileText className="mr-1.5 size-3.5" />
                        Buat PDF
                    </Button>
                </div>
            </div>
            {selectedWarning ? (
                <section className="grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white p-5 shadow-sm">
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
                                        Perbarui status SP
                                    </p>
                                    <select
                                        name="letter_status"
                                        defaultValue={
                                            selectedWarning.letter_status
                                        }
                                        className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                                    >
                                        <option value="draft">Draft SP</option>
                                        <option value="issued">
                                            Terbitkan SP
                                        </option>
                                    </select>
                                    <Input
                                        name="reason"
                                        required
                                        minLength={5}
                                        maxLength={1000}
                                        defaultValue={
                                            selectedWarning.reason ?? ""
                                        }
                                        placeholder="Alasan perubahan status"
                                    />
                                    <Button
                                        disabled={processing}
                                        type="submit"
                                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                    >
                                        <Save className="mr-2 size-4" />
                                        Simpan status
                                    </Button>
                                </>
                            )}
                        </Form>
                        <Form
                            {...destroyWarning.form(selectedWarning.id)}
                            className="grid gap-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4"
                            onBefore={() =>
                                window.confirm(
                                    "Batalkan SP ini? Riwayat audit tetap tersimpan dan mahasiswa kembali ke daftar sisa jam setelah cutoff.",
                                )
                            }
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm font-bold text-rose-800">
                                        Batalkan / hapus dari proses aktif
                                    </p>
                                    <p className="text-xs text-rose-700">
                                        Pembatalan tidak menghapus jejak audit.
                                        Mahasiswa dapat dipilih lagi untuk
                                        membuat draft baru.
                                    </p>
                                    <Input
                                        name="reason"
                                        required
                                        minLength={5}
                                        maxLength={1000}
                                        placeholder="Alasan pembatalan SP"
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
                                        Batalkan SP
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            ) : null}
            {warnings?.data.length ? (
                <WarningTable data={warnings} onSelect={onSelectWarning} />
            ) : (
                <EmptyTableState isAdmin />
            )}
        </section>
    );
}
