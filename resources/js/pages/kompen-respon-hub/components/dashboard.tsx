import { Form, Link } from "@inertiajs/react";
import {
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Clock3,
    Search,
    ShieldAlert,
    Users,
    X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { number } from "../lib/formatters";
import type {
    Dashboard,
    FilterOptions,
    Filters,
    StudentOverview,
} from "../types";
import { activityDescription } from "./activity";

export function DashboardPanel({
    dashboard,
    filters,
    filterOptions,
}: {
    dashboard: Dashboard;
    filters: Filters;
    filterOptions: FilterOptions;
}): React.JSX.Element {
    const warningQuery = {
        tab: "warnings",
        ...(filters.tingkat ? { tingkat: filters.tingkat } : {}),
        ...(filters.kelas ? { kelas: filters.kelas } : {}),
        ...(filters.periode_semester
            ? { periode_semester: filters.periode_semester }
            : {}),
    };
    const cards = [
        {
            label: "Mahasiswa terpantau",
            value: dashboard.summary.total_students,
            detail: "Sesuai periode dan filter aktif",
            icon: Users,
        },
        {
            label: "Masih memiliki sisa jam",
            value: dashboard.summary.outstanding_students,
            detail: `${number(String(dashboard.summary.outstanding_hours))} jam perlu ditindaklanjuti`,
            icon: Clock3,
        },
        {
            label: "Kompen selesai",
            value: dashboard.summary.completed_students,
            detail: "Tidak memiliki sisa jam efektif",
            icon: CheckCircle2,
        },
        {
            label: "SP aktif",
            value: dashboard.summary.warning_count,
            detail: `${dashboard.summary.issued_warning_count} telah diterbitkan`,
            icon: ShieldAlert,
        },
        {
            label: "Cutoff terdekat",
            value: dashboard.summary.nearest_cutoff
                ? `${dashboard.summary.nearest_cutoff.days_remaining} hari`
                : "—",
            detail: dashboard.summary.nearest_cutoff
                ? `${dashboard.summary.nearest_cutoff.periode_semester} · ${new Date(dashboard.summary.nearest_cutoff.deadline_at).toLocaleDateString("id-ID", { timeZone: "Asia/Jakarta" })}`
                : "Belum ada cutoff mendatang",
            icon: CalendarClock,
        },
    ];

    return (
        <section className="grid gap-5">
            <Form
                {...adminIndex.form()}
                className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(160px,1fr)_minmax(120px,0.7fr)_minmax(140px,0.8fr)_auto_auto] md:items-end"
            >
                <input name="tab" type="hidden" value="dashboard" />
                <div className="grid flex-1 gap-1.5">
                    <Label
                        htmlFor="dashboard-period"
                        className="text-xs font-bold text-[#395886]"
                    >
                        Ringkas periode
                    </Label>
                    <select
                        id="dashboard-period"
                        name="periode_semester"
                        defaultValue={filters.periode_semester ?? ""}
                        className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm font-medium text-[#395886]"
                    >
                        <option value="">Semua periode</option>
                        {filterOptions.periode_semester.map((period) => (
                            <option key={period} value={period}>
                                {period}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor="dashboard-level"
                        className="text-xs font-bold text-[#395886]"
                    >
                        Tingkat
                    </Label>
                    <select
                        id="dashboard-level"
                        name="tingkat"
                        defaultValue={filters.tingkat?.toString() ?? ""}
                        className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm font-medium text-[#395886]"
                    >
                        <option value="">Semua tingkat</option>
                        {filterOptions.tingkat.map((level) => (
                            <option key={level} value={level}>
                                Tingkat {level}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor="dashboard-class"
                        className="text-xs font-bold text-[#395886]"
                    >
                        Kelas
                    </Label>
                    <select
                        id="dashboard-class"
                        name="kelas"
                        defaultValue={filters.kelas ?? ""}
                        className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm font-medium text-[#395886]"
                    >
                        <option value="">Semua kelas</option>
                        {filterOptions.kelas.map((classCode) => (
                            <option key={classCode} value={classCode}>
                                {classCode}
                            </option>
                        ))}
                    </select>
                </div>
                <Button
                    type="submit"
                    className="rounded-xl bg-[#395886] text-xs font-bold"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
                <Button
                    asChild
                    type="button"
                    variant="outline"
                    className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
                >
                    <Link
                        href={adminIndex.url({ query: { tab: "dashboard" } })}
                    >
                        Reset
                    </Link>
                </Button>
            </Form>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {cards.map((card) => {
                    const Icon = card.icon;

                    return (
                        <Card
                            key={card.label}
                            className="border-white/80 bg-white/85 shadow-sm"
                        >
                            <CardContent className="flex items-start justify-between gap-4 p-5">
                                <div>
                                    <p className="text-xs font-semibold text-[#395886]/70">
                                        {card.label}
                                    </p>
                                    <p className="mt-2 text-3xl font-black tabular-nums text-[#395886]">
                                        {typeof card.value === "number"
                                            ? number(String(card.value))
                                            : card.value}
                                    </p>
                                    <p className="mt-1 text-[11px] text-[#395886]/60">
                                        {card.detail}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-[#B1C9EF]/35 p-3 text-[#395886]">
                                    <Icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <div className="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
                <Card className="border-white/80 bg-white/85 shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-[#395886]">
                            <CalendarClock className="size-4" /> Status periode
                        </CardTitle>
                        <CardDescription>
                            Batas waktu menentukan kapan SP dapat dibuat.
                            Periode terkunci hanya setelah ditutup oleh admin.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {dashboard.summary.periods.length ? (
                            dashboard.summary.periods.map((period) => (
                                <div
                                    key={period.periode_semester}
                                    className="flex items-center justify-between rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 px-4 py-3"
                                >
                                    <div>
                                        <p className="text-sm font-bold text-[#395886]">
                                            {period.periode_semester}
                                        </p>
                                        <p className="text-xs text-[#395886]/65">
                                            {period.deadline_at
                                                ? new Date(
                                                      period.deadline_at,
                                                  ).toLocaleString("id-ID", {
                                                      timeZone: "Asia/Jakarta",
                                                  })
                                                : "Batas waktu belum ditetapkan"}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            "rounded-full px-2.5 py-1 text-[10px] font-black uppercase",
                                            period.status === "open" &&
                                                "bg-emerald-100 text-emerald-800",
                                            period.status === "cutoff_passed" &&
                                                "bg-amber-100 text-amber-800",
                                            period.status === "locked" &&
                                                "bg-slate-200 text-slate-700",
                                        )}
                                    >
                                        {period.status === "open"
                                            ? "Berjalan"
                                            : period.status === "cutoff_passed"
                                              ? "Lewat cutoff"
                                              : "Terkunci"}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <p className="rounded-2xl border border-dashed border-[#8AAEE0] p-4 text-xs text-[#395886]/70">
                                Belum ada batas waktu periode. Tetapkan pada
                                Surat Peringatan.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-white/80 bg-white/85 shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-[#395886]">
                            <ClipboardList className="size-4" /> Daftar kerja
                            admin
                        </CardTitle>
                        <CardDescription>
                            Prioritas yang membutuhkan keputusan atau tindak
                            lanjut.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <DashboardWorklistLink
                            title="Belum memiliki SP"
                            count={dashboard.worklist.fixed_candidates.length}
                            description="Masih memiliki sisa jam setelah cutoff."
                        />
                        <DashboardWorklistLink
                            title="SP perlu ditindaklanjuti"
                            count={
                                dashboard.worklist.warnings_to_follow_up.length
                            }
                            description="Draft atau surat aktif pada periode terpilih."
                        />
                        <Button
                            asChild
                            variant="outline"
                            className="mt-1 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <Link
                                href={adminIndex.url({ query: warningQuery })}
                            >
                                Buka Surat Peringatan
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

export function DashboardWorklistLink({
    title,
    count,
    description,
}: {
    title: string;
    count: number;
    description: string;
}): React.JSX.Element {
    return (
        <div className="flex items-start justify-between gap-3 rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 px-4 py-3">
            <div>
                <p className="text-sm font-bold text-[#395886]">{title}</p>
                <p className="mt-0.5 text-[11px] leading-relaxed text-[#395886]/65">
                    {description}
                </p>
            </div>
            <span className="rounded-xl bg-[#395886] px-2.5 py-1 text-xs font-black tabular-nums text-white">
                {count}
            </span>
        </div>
    );
}

export function StudentOverviewPanel({
    overview,
    onClose,
}: {
    overview: StudentOverview;
    onClose: () => void;
}): React.JSX.Element {
    const { summary, source } = overview;

    return (
        <section className="overflow-hidden rounded-3xl border border-[#8AAEE0] bg-white shadow-lg">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-[#D5DEEF] bg-[#F0F3FA]/65 px-5 py-4">
                <div>
                    <p className="text-[10px] font-black tracking-[0.16em] text-[#628ECB] uppercase">
                        Profil mahasiswa
                    </p>
                    <h2 className="mt-1 text-lg font-black text-[#395886]">
                        {summary.nama_mahasiswa}
                    </h2>
                    <p className="font-mono text-xs text-[#395886]/70">
                        {summary.nim} · {summary.kelas} ·{" "}
                        {summary.periode_semester}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onClose}
                    className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                >
                    <X className="mr-1.5 size-4" /> Tutup
                </Button>
            </div>
            <div className="grid gap-4 p-5 xl:grid-cols-[1fr_1fr_1.2fr]">
                <div className="rounded-2xl border border-[#D5DEEF] p-4">
                    <p className="text-xs font-black tracking-wide text-[#395886] uppercase">
                        Data efektif
                    </p>
                    <dl className="mt-3 grid gap-2 text-xs">
                        <OverviewValue
                            label="Kompen"
                            value={`${number(summary.effective_total_kompensasi_jam)} jam`}
                        />
                        <OverviewValue
                            label="Responsi"
                            value={`${number(summary.effective_total_responsi_jam)} jam`}
                        />
                        <OverviewValue
                            label="Kompen dikerjakan"
                            value={`${number(summary.effective_kompensasi_dikerjakan_jam)} jam`}
                        />
                        <OverviewValue
                            label="Responsi dikerjakan"
                            value={`${number(summary.effective_responsi_dikerjakan_jam)} jam`}
                        />
                        <OverviewValue
                            label="Sisa hutang"
                            value={`${number(summary.effective_sisa_hutang_jam)} jam`}
                            emphasized
                        />
                    </dl>
                    {summary.has_summary_override ? (
                        <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">
                            Nilai efektif menggunakan koreksi admin.
                        </p>
                    ) : null}
                </div>
                <div className="rounded-2xl border border-[#D5DEEF] p-4">
                    <p className="text-xs font-black tracking-wide text-[#395886] uppercase">
                        Sumber workbook
                    </p>
                    <dl className="mt-3 grid gap-2 text-xs">
                        <OverviewValue
                            label="Kompen asal"
                            value={`${number(source.total_kompensasi_jam)} jam`}
                        />
                        <OverviewValue
                            label="Responsi asal"
                            value={`${number(source.total_responsi_jam)} jam`}
                        />
                        <OverviewValue
                            label="Sisa asal"
                            value={`${number(source.sisa_hutang_jam)} jam`}
                        />
                    </dl>
                    <p className="mt-3 text-[11px] leading-relaxed text-[#395886]/65">
                        {source.import_filename ??
                            "Sumber unggahan tidak tersedia"}
                        {source.imported_at
                            ? ` · ${new Date(source.imported_at).toLocaleString("id-ID", { timeZone: "Asia/Jakarta" })}`
                            : ""}
                    </p>
                </div>
                <div className="rounded-2xl border border-[#D5DEEF] p-4">
                    <p className="text-xs font-black tracking-wide text-[#395886] uppercase">
                        Jejak perubahan terakhir
                    </p>
                    <div className="mt-3 grid gap-2">
                        {overview.activities.length ? (
                            overview.activities.slice(0, 4).map((activity) => (
                                <div
                                    key={activity.id}
                                    className="border-l-2 border-[#8AAEE0] pl-3 text-xs"
                                >
                                    <p className="font-semibold text-[#395886]">
                                        {activityDescription(activity)}
                                    </p>
                                    <p className="text-[11px] text-[#395886]/65">
                                        {new Date(
                                            activity.occurred_at,
                                        ).toLocaleString("id-ID", {
                                            timeZone: "Asia/Jakarta",
                                        })}
                                        {activity.reason
                                            ? ` · ${activity.reason}`
                                            : ""}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <p className="text-xs text-[#395886]/65">
                                Belum ada perubahan manual yang tercatat.
                            </p>
                        )}
                    </div>
                    <p className="mt-4 text-[11px] text-[#395886]/65">
                        {overview.details.length} detail Kompen ·{" "}
                        {overview.warnings.length} riwayat SP
                    </p>
                </div>
            </div>
        </section>
    );
}

export function OverviewValue({
    label,
    value,
    emphasized = false,
}: {
    label: string;
    value: string;
    emphasized?: boolean;
}): React.JSX.Element {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-[#395886]/65">{label}</dt>
            <dd
                className={cn(
                    "font-mono font-bold text-[#395886]",
                    emphasized && "text-base",
                )}
            >
                {value}
            </dd>
        </div>
    );
}
