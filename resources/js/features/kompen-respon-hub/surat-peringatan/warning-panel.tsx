import { TableGuide } from "../shared/components/feedback";
import { CorrectionField } from "../shared/components/correction-field";
import { ConfirmForm, useConfirmAction } from "../shared/components/confirm-action";
import { Form, router } from "@inertiajs/react";
import {
    Save,
    Search,
    Archive,
    Eye,
    EyeOff,
    CalendarClock,
    CircleAlert,
    CircleCheck,
    Trash2,
    Undo2,
    X,
} from "lucide-react";
import { useEffect, useState } from "react";
import {
    destroyWarning,
    finalizeCutoff,
    rollbackCutoffFinalization,
    selectWarningPeriod,
    storeCutoff,
    updateWarning,
} from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import { ClassSelectContent } from "../shared/components/class-select-content";
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
    WarningPeriodOption,
    WarningRecipient,
} from "../shared/types";
import { Pager } from "../shared/components/pagination";

function formatCutoffDateTime(value: string, timezone: string): string {
    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "full",
        timeStyle: "short",
        timeZone: timezone,
    }).format(new Date(value));
}

function CutoffTimingNotice({
    cutoff,
}: {
    cutoff: Cutoff;
}): React.JSX.Element {
    const formattedDeadline = formatCutoffDateTime(
        cutoff.deadline_at,
        cutoff.timezone,
    );
    const isFinalized = cutoff.finalization_status === "finalized";
    const isRevising = cutoff.finalization_status === "revising";
    const state = isFinalized
        ? {
            icon: CircleCheck,
            title: "Periode sudah final",
            description: "Artifact XLSX resmi sudah diterbitkan. Mulai koreksi bila data perlu diperbaiki.",
            className: "border-emerald-300 bg-emerald-50 text-emerald-950",
            iconClassName: "bg-emerald-600 text-white",
        }
        : isRevising
          ? {
              icon: CircleAlert,
              title: "Koreksi sedang dibuka",
              description: "SP sementara tidak tersedia di API. Setelah data benar, finalisasi ulang untuk menerbitkan revisi XLSX.",
              className: "border-amber-300 bg-amber-50 text-amber-950",
              iconClassName: "bg-amber-600 text-white",
          }
          : cutoff.has_passed
            ? {
                icon: CircleAlert,
                title: "Cutoff sudah terlewati",
                description: "Periksa kandidat SP lalu finalisasi. Waktu cutoff dan data masih dapat diperbaiki selama periode belum final.",
                className: "border-rose-300 bg-rose-50 text-rose-950",
                iconClassName: "bg-rose-600 text-white",
            }
            : {
                icon: CalendarClock,
                title: "Cutoff masih berjalan",
                description: "Gunakan waktu ini untuk memeriksa data. Pratinjau menunjukkan penerima bila finalisasi dilakukan sekarang.",
                className: "border-[#8AAEE0] bg-[#F0F3FA] text-[#1E293B]",
                iconClassName: "bg-[#395886] text-white",
            };
    const Icon = state.icon;

    return (
        <section
            aria-live="polite"
            className={cn("rounded-2xl border p-4", state.className)}
        >
            <div className="flex items-start gap-3">
                <span
                    aria-hidden="true"
                    className={cn(
                        "grid size-9 shrink-0 place-items-center rounded-xl shadow-sm",
                        state.iconClassName,
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-extrabold">{state.title}</p>
                    <p className="mt-1 text-xs leading-relaxed font-medium">
                        {state.description}
                    </p>
                    <p className="mt-2 text-xs font-bold">
                        Batas waktu: {formattedDeadline} WIB
                    </p>
                </div>
            </div>
        </section>
    );
}

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
    onSelect?: (warning: Warning) => void;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="data-table-scroll">
                <table className="data-table min-w-[1040px]">
                    <colgroup>
                        {["18rem", "10rem", "13rem", "8rem", "22rem"].map((width, index) => (
                            <col key={`${width}-${index}`} style={{ width }} />
                        ))}
                        {onSelect && <col style={{ width: "9rem" }} />}
                    </colgroup>
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-5 py-4">Mahasiswa</th>
                            <th className="px-5 py-4">Kelas</th>
                            <th className="px-5 py-4">Status</th>
                            <th className="px-5 py-4">Sisa[j]</th>
                            <th className="px-5 py-4">Catatan</th>
                            {onSelect && <th className="px-5 py-4">Tindakan</th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((warning) => (
                            <tr
                                key={warning.id}
                                className="hover:bg-[#B1C9EF]/10"
                            >
                                <td className="px-5 py-4">
                                    <p className="break-words font-bold text-[#395886]">
                                        {warning.nama_mahasiswa}
                                    </p>
                                    <p className="font-mono text-xs text-[#628ECB]">
                                        {warning.nim}
                                    </p>
                                </td>
                                <td className="px-5 py-4 text-xs font-semibold">
                                    {warning.kelas}
                                </td>
                                <td className="px-5 py-4">
                                    <WarningStatusBadge warning={warning} />
                                </td>
                                <td className="px-5 py-4 text-right font-mono text-xs">
                                    {number(
                                        String(
                                            warning.snapshot.sisa_hutang_jam,
                                        ),
                                    )}
                                </td>
                                <td className="px-5 py-4 text-xs text-[#395886]/70">
                                    {warning.reason ?? "—"}
                                </td>
                                {onSelect && <td className="px-5 py-4">
                                    <Button type="button" size="sm" variant="outline" onClick={() => onSelect(warning)}
                                        aria-label={`Kelola SP ${warning.nama_mahasiswa}`}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#395886] hover:text-white">Kelola SP</Button>
                                </td>}
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
    data, preview = false,
}: {
    data: Pagination<WarningRecipient>;
    preview?: boolean;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-amber-200 bg-white/80 shadow-sm">
            <div className="border-b border-amber-100 bg-amber-50 px-4 py-3">
                <p className="text-sm font-extrabold text-amber-900">{preview ? "Pratinjau penerima finalisasi" : "Kandidat SP"}</p>
                <p className="mt-0.5 text-xs text-amber-800/80">
                    {preview ? "Jika difinalisasi sekarang, mahasiswa berikut akan tercantum dalam XLSX. Pratinjau ini belum menerbitkan SP atau mengubah API." : "Cutoff sudah lewat. Kandidat diperiksa sebelum finalisasi semester dan penerbitan XLSX resmi."}
                </p>
            </div>
            <div className="data-table-scroll">
                <table className="data-table min-w-[900px]">
                    <colgroup>
                        {["18rem", "10rem", "12rem", "10rem", "14rem"].map((width, index) => (
                            <col key={`${width}-${index}`} style={{ width }} />
                        ))}
                    </colgroup>
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-5 py-4">Mahasiswa</th>
                            <th className="px-5 py-4">Kelas</th>
                            <th className="px-5 py-4">Periode</th>
                            <th className="px-5 py-4 text-right">Sisa jam</th>
                            <th className="px-5 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {!data.data.length && <tr><td colSpan={5} className="px-5 py-4 text-sm text-[#395886]">Tidak ada penerima yang sesuai dengan filter.</td></tr>}
                        {data.data.map((student) => (
                            <tr key={student.id} className="hover:bg-amber-50/50">
                                <td className="px-5 py-4">
                                    <p className="break-words font-bold text-[#395886]">{student.nama_mahasiswa}</p>
                                    <p className="font-mono text-xs text-[#628ECB]">{student.nim}</p>
                                </td>
                                <td className="px-5 py-4 text-xs font-semibold text-[#395886]">{student.kelas}</td>
                                <td className="px-5 py-4 text-xs font-semibold text-[#395886]">{student.periode_semester}</td>
                                <td className="px-5 py-4 text-right font-mono text-xs font-bold text-[#395886]">{number(student.effective_sisa_hutang_jam)}</td>
                                <td className="px-5 py-4">
                                    <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-extrabold text-amber-800">{preview ? "Pratinjau" : "Kandidat SP"}</span>
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
    warningPeriods,
    selectableWarningPeriods,
    warningPeriodOptions,
    warnings,
    warningCandidates,
    warningPreview,
    rolledBackWarnings,
    selectedWarning: selection,
    onSelectWarning,
    onCloseWarning,
}: {
    cutoffs: Cutoff[];
    filterOptions: FilterOptions;
    filters: Filters;
    warningPeriods: string[];
    selectableWarningPeriods: string[];
    warningPeriodOptions: WarningPeriodOption[];
    warnings: Pagination<Warning> | null;
    warningCandidates: Pagination<Student> | null;
    warningPreview: Pagination<WarningRecipient> | null;
    rolledBackWarnings: Pagination<Warning> | null;
    selectedWarning: Warning | null;
    onSelectWarning: (warning: Warning) => void;
    onCloseWarning: () => void;
}): React.JSX.Element {
    const selectedWarningPeriod = filters.warning_period ?? "";
    const selectedListPeriod = filters.list_period ?? selectedWarningPeriod;
    const isInspectionMode = selectedListPeriod !== selectedWarningPeriod;
    const [selectedFilterClass, setSelectedFilterClass] = useState(
        filters.kelas ?? "",
    );
    const [selectedFilterLevel, setSelectedFilterLevel] = useState(
        filters.tingkat?.toString() ?? "",
    );
    const [isOtherPeriodPickerOpen, setIsOtherPeriodPickerOpen] = useState(
        isInspectionMode,
    );
    const [selectedInspectionPeriod, setSelectedInspectionPeriod] = useState(
        selectedListPeriod,
    );
    const selectedCutoff = cutoffs.find(
        (cutoff) => cutoff.periode_semester === selectedWarningPeriod,
    );
    const isSelectedCutoffFinalized =
        selectedCutoff?.finalization_status === "finalized";
    const isSelectedCutoffRevising =
        selectedCutoff?.finalization_status === "revising";
    const hasNoFinalizationRecipients =
        selectedCutoff?.projected_recipient_count === 0;
    const canManage = !!selectedCutoff?.is_available && !isInspectionMode;
    const selectedWarning = warnings?.data.find((item) => item.id === selection?.id)
        ?? rolledBackWarnings?.data.find((item) => item.id === selection?.id);
    const canEditWarning = canManage && !isSelectedCutoffFinalized;
    const { confirm, dialog, processing: actionPending } = useConfirmAction();
    const periodLabel = (period: string) => {
        const option = warningPeriodOptions.find((item) => item.periode_semester === period);
        if (!option) return "Belum diatur";
        const labels: Record<WarningPeriodOption["status"], string> = {
            finalized: "SP final", revising: "Sedang dibenarkan", cutoff_active: "Cutoff aktif",
            ready_to_finalize: "Cutoff terlewati", unconfigured: "Belum diatur",
        };
        return labels[option.status] + (option.is_active_workbook ? "" : " · workbook historis");
    };

    useEffect(() => {
        setSelectedFilterClass(filters.kelas ?? "");
        setSelectedFilterLevel(filters.tingkat?.toString() ?? "");
        setIsOtherPeriodPickerOpen(isInspectionMode);
        setSelectedInspectionPeriod(selectedListPeriod);
    }, [filters.kelas, filters.tingkat, isInspectionMode, selectedListPeriod]);

    function selectManagedWarningPeriod(period: string): void {
        if (period === selectedWarningPeriod) {
            return;
        }

        confirm({
            title: "Atur semester SP",
            description: `Kelola SP untuk ${period}? Riwayat semester lain tetap tersimpan.`,
            submit: (onFinish) => router.post(selectWarningPeriod.url(), { periode_semester: period }, { preserveScroll: true, onFinish }),
        });
    }

    function returnToManagedPeriod(): void {
        router.get(
            adminIndex.url(),
            { tab: "warnings", warning_period: selectedWarningPeriod },
            { preserveScroll: true },
        );
    }

    return (
        <section className="grid gap-4">
            {dialog}
            <TableGuide title="Panduan Surat Peringatan" items={[
                "Pilih semester dari workbook Kompen dan Responsi yang digunakan. Roster mengatur akses login mahasiswa.",
                "Cutoff bersifat opsional. Sebelum batas waktu, pratinjau menunjukkan penerima bila difinalisasi sekarang. Setelah cutoff, periksa daftar kandidat SP.",
                "Finalisasi menetapkan penerima, menyimpan XLSX resmi di List File dan menerbitkan status SP melalui API. Jika jumlahnya nol, konfirmasi untuk membuat XLSX kosong.",
                "Mulai koreksi untuk menarik SP semester tersebut dari API. Setelah diperbaiki, finalisasi ulang membuat revisi XLSX baru dan menyimpan riwayat sebelumnya.",
            ]} />
            <section className="order-2 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-base font-extrabold text-[#395886]">
                            Daftar mahasiswa
                        </h2>
                        <p className="mt-0.5 text-xs text-[#395886]/70">
                            Pilih filter untuk memeriksa penerima pada semester yang ditampilkan.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                if (isInspectionMode) {
                                    returnToManagedPeriod();

                                    return;
                                }

                                setIsOtherPeriodPickerOpen((isOpen) => !isOpen);
                            }}
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            {isInspectionMode ? (
                                <EyeOff className="mr-1.5 size-3.5" />
                            ) : (
                                <Eye className="mr-1.5 size-3.5" />
                            )}
                            {isInspectionMode
                                ? "Kembali ke periode SP"
                                : "Lihat periode lain"}
                        </Button>
                    </div>
                </div>
                <Form
                    {...adminIndex.form()}
                    key={`${selectedWarningPeriod}:${selectedListPeriod}:${filters.kelas ?? ""}:${filters.search ?? ""}`}
                    className={cn(
                        "mt-4 grid gap-3 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 p-3 md:items-end",
                        isOtherPeriodPickerOpen
                            ? "md:grid-cols-2 xl:grid-cols-[minmax(190px,1fr)_minmax(130px,0.55fr)_minmax(150px,0.7fr)_minmax(180px,0.85fr)_auto]"
                            : "md:grid-cols-2 xl:grid-cols-[minmax(190px,1fr)_minmax(130px,0.55fr)_minmax(150px,0.7fr)_auto]",
                    )}
                >
                    <input name="tab" type="hidden" value="warnings" />
                    <input
                        name="warning_period"
                        type="hidden"
                        value={selectedWarningPeriod}
                    />
                    <input
                        name="tingkat"
                        type="hidden"
                        value={selectedFilterLevel}
                    />
                    <input
                        name="kelas"
                        type="hidden"
                        value={selectedFilterClass}
                    />
                    <input
                        name="list_period"
                        type="hidden"
                        value={
                            isOtherPeriodPickerOpen
                                ? selectedInspectionPeriod
                                : ""
                        }
                    />
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        maxLength={100}
                        placeholder="Cari nama atau NIM"
                        className="h-10 rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                    />
                    <Select
                        value={selectedFilterLevel || "all"}
                        onValueChange={(value) =>
                            setSelectedFilterLevel(value === "all" ? "" : value)
                        }
                    >
                        <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                            <SelectValue placeholder="Semua tingkat" />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            <SelectItem
                                value="all"
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                Semua tingkat
                            </SelectItem>
                            {filterOptions.tingkat.map((tingkat) => (
                                <SelectItem
                                    key={tingkat}
                                    value={tingkat.toString()}
                                    className="cursor-pointer hover:bg-[#B1C9EF]/20"
                                >
                                    Tingkat {tingkat}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={selectedFilterClass || "all"}
                        onValueChange={(value) =>
                            setSelectedFilterClass(value === "all" ? "" : value)
                        }
                    >
                        <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                            <SelectValue placeholder="Semua kelas" />
                        </SelectTrigger>
                        <ClassSelectContent>
                            <SelectItem
                                value="all"
                                className="col-span-full cursor-pointer hover:bg-[#B1C9EF]/20"
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
                        </ClassSelectContent>
                    </Select>
                    {isOtherPeriodPickerOpen ? (
                        <Select
                            value={selectedInspectionPeriod || "placeholder"}
                            onValueChange={(value) =>
                                setSelectedInspectionPeriod(
                                    value === "placeholder" ? "" : value,
                                )
                            }
                        >
                            <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                                <SelectValue placeholder="Pilih periode untuk dilihat" />
                            </SelectTrigger>
                            <SelectContent className="rounded-2xl text-xs font-semibold">
                                {warningPeriods.map((period) => (
                                    <SelectItem
                                        key={period}
                                        value={period}
                                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                                    >
                                        {period} · {periodLabel(period)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : null}
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
                            Semester Surat Peringatan
                        </h2>
                        <p className="mt-1 text-xs text-[#395886]/70">
                            Tentukan semester yang sedang diatur. Cutoff,
                            finalisasi, artifact, dan riwayat SP mengikuti
                            semester ini.
                        </p>
                    </div>
                    {selectedCutoff ? (
                        <span
                            className={cn(
                                "w-fit rounded-full px-3 py-1 text-xs font-bold",
                                isSelectedCutoffFinalized
                                    ? "bg-emerald-100 text-emerald-800"
                                    : isSelectedCutoffRevising
                                      ? "bg-amber-100 text-amber-800"
                                      : "bg-[#B1C9EF]/40 text-[#395886]",
                            )}
                        >
                            {periodLabel(selectedWarningPeriod)}
                        </span>
                    ) : null}
                </div>

                {selectableWarningPeriods.length ? (
                    <div className="grid gap-1.5 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 p-3 md:max-w-xl">
                        <Label className="text-xs font-bold text-[#395886]">
                            Semester yang diatur
                        </Label>
                        <Select
                            value={selectedWarningPeriod || "placeholder"}
                            onValueChange={(value) => {
                                if (value !== "placeholder") {
                                    selectManagedWarningPeriod(value);
                                }
                            }}
                        >
                            <SelectTrigger className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                                <SelectValue placeholder="Pilih periode SP" />
                            </SelectTrigger>
                            <SelectContent className="rounded-2xl text-xs font-semibold">
                                {selectableWarningPeriods.map((period) => (
                                    <SelectItem key={period} value={period}>{period} · {periodLabel(period)}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="flex flex-wrap gap-2 pt-1">
                            {selectableWarningPeriods.slice(0, 2).map((period, index) => (
                                <Button
                                    key={period}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => selectManagedWarningPeriod(period)}
                                    className="h-8 rounded-xl border-[#8AAEE0] bg-white text-[11px] font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                >
                                    {index === 0 ? "Terbaru tersedia" : "Sebelumnya tersedia"}: {period}
                                </Button>
                            ))}
                        </div>
                        <p className="text-xs text-[#395886]/70">
                            Status semester ditentukan dari cutoff dan artifact,
                            bukan dari roster mahasiswa aktif.
                        </p>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/60 p-4 text-xs text-[#395886]/75">
                        Belum ada workbook Kompen dan Respon yang digunakan.
                        Pilih versi workbook terlebih dahulu melalui List File.
                    </div>
                )}

                {selectedCutoff ? (
                    <div className="flex flex-col justify-between gap-4 rounded-2xl border border-[#8AAEE0] bg-[#B1C9EF]/20 p-4 sm:flex-row sm:items-center">
                        <div>
                            <p className="text-[11px] font-black tracking-[0.14em] text-[#628ECB] uppercase">
                                Semester yang sedang diatur
                            </p>
                            <p className="mt-1 text-base font-extrabold text-[#395886]">
                                {selectedCutoff.periode_semester}
                            </p>
                            <p className="mt-1 text-sm font-semibold text-[#395886]/75">
                                {selectedCutoff.is_available ? "Finalisasi mencatat kondisi workbook saat diterbitkan." : "Workbook semester ini sudah dilepas. Riwayat SP dapat dilihat."}
                            </p>
                            <p className="mt-2 text-xs font-bold text-[#395886]">
                                {selectedCutoff.projected_recipient_count !== null && `${isSelectedCutoffFinalized ? "Penerima pada XLSX resmi" : "Penerima bila difinalisasi sekarang"}: ${selectedCutoff.projected_recipient_count}.`}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/60 p-4 text-xs text-[#395886]/75">
                        Pilih salah satu periode workbook aktif untuk mulai
                        mengelola Surat Peringatan.
                    </div>
                )}

                {selectedCutoff ? <CutoffTimingNotice cutoff={selectedCutoff} /> : null}

                {selectedCutoff && canEditWarning ? (
                    <Form
                        key={selectedCutoff.id}
                        {...storeCutoff.form()}
                        className="grid gap-3 rounded-2xl border border-[#D5DEEF] bg-[#F7FAFF] p-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    name="periode_semester"
                                    type="hidden"
                                    value={selectedCutoff.periode_semester}
                                />
                                {errors.periode_semester && <p className="text-xs font-bold text-rose-700">{errors.periode_semester}</p>}
                                <div>
                                    <p className="text-sm font-extrabold text-[#395886]">
                                        Batas waktu periode
                                    </p>
                                    <p className="mt-1 text-xs leading-relaxed text-[#395886]/70">
                                        Gunakan waktu WIB (Asia/Jakarta). Batas
                                        waktu dapat diperbarui selama SP belum
                                        final, termasuk saat periode sedang
                                        dikoreksi.
                                    </p>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                    <div className="grid gap-1.5">
                                        <CorrectionField
                                            label="Tanggal dan waktu batas cutoff"
                                            error={errors.deadline_at}
                                            id={`cutoff-${selectedCutoff.id}`}
                                            name="deadline_at"
                                            type="datetime-local"
                                            required
                                            defaultValue={datetimeLocalValue(
                                                selectedCutoff.deadline_at, selectedCutoff.timezone,
                                            )}
                                            className="h-10 rounded-xl border-[#8AAEE0] bg-white text-sm font-semibold text-[#1E293B] [color-scheme:light]"
                                        />
                                    </div>
                                    <Button
                                        disabled={processing}
                                        type="submit"
                                        className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                    >
                                        <Save className="mr-1.5 size-3.5" />
                                        Simpan cutoff
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : null}

                {selectedCutoff && canManage ? (
                    <div className="flex flex-col justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center">
                        <div>
                            <p className="text-xs leading-relaxed font-medium text-amber-900">
                                {isSelectedCutoffFinalized
                                    ? `SP ${selectedCutoff.periode_semester} sudah final. Data dan catatan SP dikunci agar sesuai dengan artifact XLSX resmi.`
                                    : isSelectedCutoffRevising
                                      ? `Koreksi untuk ${selectedCutoff.periode_semester} sedang dibuka. Finalisasi ulang untuk menerbitkan revisi XLSX baru.`
                                      : `${selectedCutoff.periode_semester} siap difinalisasi. Periksa ${warningPreview ? "pratinjau penerima" : "kandidat SP"}, lalu finalisasi ketika data sudah benar.`}
                            </p>
                            {isSelectedCutoffFinalized ? (
                                <p className="mt-1 text-xs text-amber-800/80">
                                    Mulai koreksi akan menarik SP periode ini dari API. Artifact sebelumnya dan seluruh riwayat audit tetap tersimpan.
                                </p>
                            ) : null}
                        </div>
                        {isSelectedCutoffFinalized ? (
                            <ConfirmForm
                                {...rollbackCutoffFinalization.form(
                                    selectedCutoff.id,
                                )}
                                className="flex shrink-0 flex-wrap items-center gap-2"
                                title="Mulai koreksi" description={"Mulai koreksi seluruh SP pada periode ini? Mahasiswa akan segera hilang dari API SP. Artifact dan riwayat audit tetap tersimpan."}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <CorrectionField
                                            label="Alasan membuka koreksi"
                                            error={errors.reason}
                                            name="reason"
                                            required
                                            minLength={5}
                                            maxLength={1000}
                                            placeholder="Alasan membuka koreksi"
                                            className="h-9 min-w-52 bg-white text-xs"
                                        />
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                            variant="outline"
                                            className="rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                        >
                                            <Undo2 className="mr-1.5 size-3.5" />
                                            Mulai koreksi
                                        </Button>
                                    </>
                                )}
                            </ConfirmForm>
                        ) : (
                            <Button
                                type="button"
                                disabled={actionPending}
                                onClick={() => {
                                    const confirmationMessage = isSelectedCutoffRevising
                                        ? `Finalisasi ulang periode ini untuk ${selectedCutoff.projected_recipient_count} penerima? Artifact XLSX resmi akan diperbarui berdasarkan hasil koreksi terbaru.`
                                        : hasNoFinalizationRecipients
                                          ? "Belum ada penerima SP untuk semester ini. Finalisasi tetap membuat artifact XLSX resmi tanpa penerima. Lanjutkan?"
                                          : `Finalisasi SP periode ini untuk ${selectedCutoff.projected_recipient_count} penerima? Daftar penerima disimpan dalam XLSX resmi dan status SP tersedia melalui API.`;

                                    confirm({
                                        title: isSelectedCutoffRevising ? "Finalisasi ulang SP" : "Finalisasi SP",
                                        description: confirmationMessage,
                                        submit: (onFinish) => router.post(finalizeCutoff.url(selectedCutoff.id), {
                                            confirm_empty_finalization: hasNoFinalizationRecipients,
                                        }, { onFinish }),
                                    });
                                }}
                                className="shrink-0 rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                            >
                                <Archive className="mr-1.5 size-3.5" />
                                {isSelectedCutoffRevising
                                    ? "Finalisasi ulang SP"
                                    : "Finalisasi SP"}
                            </Button>
                        )}
                    </div>
                ) : null}
            </section>
            {selectedWarning && selectedWarning.periode_semester === selectedWarningPeriod && canEditWarning ? (
                <section key={selectedWarning.id} className="order-4 grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white p-5 shadow-sm">
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
                            {({ errors, processing }) => (
                                <>
                                    <p className="text-sm font-bold text-[#395886]">
                                        Perbarui catatan SP
                                    </p>
                                    <CorrectionField
                                        label="Catatan atau alasan perubahan SP"
                                        error={errors.reason}
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
                        <ConfirmForm
                            {...destroyWarning.form(selectedWarning.id)}
                            className="grid gap-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-4"
                            title="Rollback SP" description={"Rollback SP ini? Riwayat surat dan aktivitas tetap tersimpan, tetapi mahasiswa tidak lagi tampil sebagai SP aktif atau pada API SP."}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <p className="text-sm font-bold text-rose-800">
                                        Rollback SP
                                    </p>
                                    <p className="text-xs text-rose-700">
                                        Rollback tidak menghapus jejak audit.
                                        Riwayat surat dan aktivitas tetap dapat
                                        ditinjau dari profil mahasiswa.
                                    </p>
                                    <CorrectionField
                                        label="Alasan rollback SP"
                                        error={errors.reason}
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
                        </ConfirmForm>
                    </div>
                </section>
            ) : null}
            <div className="order-4">
                {isInspectionMode ? (
                    <div className="mb-4 rounded-2xl border border-[#8AAEE0] bg-[#B1C9EF]/20 px-4 py-3 text-xs leading-relaxed text-[#395886]">
                        <span className="font-extrabold">Mode lihat data.</span>{" "}
                        {selectedListPeriod} · {periodLabel(selectedListPeriod)}. Kembali ke semester yang dikelola untuk melakukan perubahan.
                    </div>
                ) : null}
                {warningPreview && <WarningCandidateTable data={warningPreview} preview />}
                {warningCandidates?.data.length ? (
                    <WarningCandidateTable data={warningCandidates} />
                ) : null}
                {warnings?.data.length ? (
                    <div className={cn(warningCandidates?.data.length ? "mt-4" : "")}>
                        <WarningTable
                            data={warnings}
                            onSelect={
                                canEditWarning ? onSelectWarning : undefined
                            }
                        />
                    </div>
                ) : warningPreview || warningCandidates?.data.length ? null : (
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
                        onSelect={
                            canEditWarning ? onSelectWarning : undefined
                        }
                    />
                </section>
            ) : null}
        </section>
    );
}
