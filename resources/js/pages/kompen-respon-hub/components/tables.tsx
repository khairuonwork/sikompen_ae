import { Form, Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, Download, RotateCcw } from "lucide-react";
import { downloadUploadedWorkbook } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { restore as restoreImportVersion } from "@/actions/App/Http/Controllers/KompenResponHubImportRollbackController";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { number } from "../lib/formatters";
import type { Detail, ImportAuditLog, Pagination, Student } from "../types";

export function Pager<T>({ data }: { data: Pagination<T> }): React.JSX.Element {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] bg-white/40 px-5 py-3.5 text-xs font-medium text-[#395886]/80 md:text-sm">
            <span>
                Halaman{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.current_page}
                </span>{" "}
                dari{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.last_page}
                </span>{" "}
                ·{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.total}
                </span>{" "}
                total data
            </span>
            <div className="flex gap-2">
                {data.links.prev ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.prev}
                            className="flex items-center gap-1"
                        >
                            <ChevronLeft className="size-4" />
                            Sebelumnya
                        </Link>
                    </Button>
                ) : null}
                {data.links.next ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.next}
                            className="flex items-center gap-1"
                        >
                            Berikutnya
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

export function StudentTable({
    data,
    isEditMode = false,
    onSelect,
    onOpenProfile,
}: {
    data: Pagination<Student>;
    isEditMode?: boolean;
    onSelect?: (student: Student) => void;
    onOpenProfile?: (student: Student) => void;
}): React.JSX.Element {
    const headings = [
        "Tingkat",
        "NIM",
        "Nama",
        "Kelas",
        "Periode",
        "Status",
        "T[j]",
        "S[j]",
        "I[j]",
        "B[j]",
        "Kompen[j]",
        "Responsi[j]",
        "Total[j]",
        "Komp. selesai[j]",
        "Resp. selesai[j]",
        "Sisa[j]",
        ...(onOpenProfile ? ["Profil"] : []),
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1150px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr
                                key={student.id}
                                className={cn(
                                    "transition-colors duration-150 hover:bg-[#B1C9EF]/10",
                                    isEditMode &&
                                        "cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]",
                                )}
                                onClick={() =>
                                    isEditMode && onSelect?.(student)
                                }
                            >
                                <td className="px-4 py-3.5 font-semibold text-[#395886]">
                                    {student.tingkat}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {student.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {student.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">
                                    {student.kelas}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {student.periode_semester}
                                </td>
                                <td className="px-4 py-3.5">
                                    <ProgressStatusBadge
                                        status={student.progress_status}
                                    />
                                </td>
                                {[
                                    student.total_jam_terlambat,
                                    student.total_jam_sakit,
                                    student.total_jam_izin,
                                    student.total_jam_bolos,
                                    student.effective_total_kompensasi_jam,
                                    student.effective_total_responsi_jam,
                                    student.effective_total_hutang_jam,
                                    student.effective_kompensasi_dikerjakan_jam,
                                    student.effective_responsi_dikerjakan_jam,
                                    student.effective_sisa_hutang_jam,
                                ].map((value, valueIndex) => (
                                    <td
                                        key={`${student.id}-${valueIndex}`}
                                        className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums"
                                    >
                                        {number(value)}
                                    </td>
                                ))}
                                {onOpenProfile ? (
                                    <td className="px-4 py-3.5">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onOpenProfile(student);
                                            }}
                                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                        >
                                            Lihat
                                        </Button>
                                    </td>
                                ) : null}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

export function ProgressStatusBadge({
    status,
}: {
    status: Student["progress_status"];
}): React.JSX.Element | null {
    const labels: Record<Student["progress_status"], string> = {
        completed: "Selesai",
        warning_active: "SP aktif",
        none: "",
    };
    const tones: Record<Student["progress_status"], string> = {
        completed: "bg-emerald-100 text-emerald-800",
        warning_active: "bg-rose-100 text-rose-800",
        none: "",
    };

    if (status === "none") {
        return null;
    }

    return (
        <span
            className={cn(
                "inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black",
                tones[status],
            )}
        >
            {labels[status]}
        </span>
    );
}

export function DetailTable({
    data,
    isEditMode = false,
    onSelect,
}: {
    data: Pagination<Detail>;
    isEditMode?: boolean;
    onSelect?: (detail: Detail) => void;
}): React.JSX.Element {
    const headings = [
        "Tanggal",
        "NIM",
        "Nama",
        "Kelas",
        "Mata Kuliah",
        "Dosen",
        "Pertemuan",
        "Presensi",
        "Terlambat",
        "Kompen[j]",
        "Responsi[j]",
        "Keterangan",
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((detail) => (
                            <tr
                                key={detail.id}
                                className={cn(
                                    "transition-colors duration-150 hover:bg-[#B1C9EF]/10",
                                    isEditMode &&
                                        "cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]",
                                )}
                                onClick={() => isEditMode && onSelect?.(detail)}
                            >
                                <td className="px-4 py-3.5 text-xs font-semibold whitespace-nowrap text-[#395886]">
                                    {detail.tanggal}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {detail.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {detail.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">
                                    {detail.kelas}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]">
                                    {detail.mata_kuliah}
                                </td>
                                <td className="px-4 py-3.5 text-xs text-[#395886]/80">
                                    {detail.nama_dosen}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-medium text-[#628ECB]">
                                    {detail.jenis_pertemuan}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#395886]">
                                    {detail.presensi}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {detail.menit_keterlambatan}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_kompensasi)}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_responsi)}
                                </td>
                                <td className="max-w-60 truncate px-4 py-3.5 text-xs text-[#395886]/70">
                                    {detail.keterangan ?? "—"}
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

export function ImportAuditLogTable({
    data,
}: {
    data: Pagination<ImportAuditLog>;
}): React.JSX.Element {
    const headings = [
        "Aksi",
        "Status Versi",
        "Waktu",
        "Admin Pelaksana",
        "Periode",
        "Nama File",
        "File",
        "Kelas",
        "Mahasiswa",
        "Detail",
        "Validasi",
        "Pulihkan",
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1320px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((auditLog) => (
                            <tr
                                key={auditLog.id}
                                className="transition-colors duration-150 hover:bg-[#B1C9EF]/10"
                            >
                                <td className="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        className={cn(
                                            "rounded-full px-2.5 py-1 text-[10px] font-black tracking-wider uppercase",
                                            auditLog.event_type === "upload"
                                                ? "border border-emerald-200 bg-emerald-100 text-emerald-800"
                                                : "border border-rose-200 bg-rose-100 text-rose-800",
                                        )}
                                    >
                                        {auditLog.event_type === "upload"
                                            ? "Upload"
                                            : auditLog.event_type === "restore"
                                              ? "Pemulihan"
                                              : "Rollback"}
                                    </span>
                                </td>
                                <td className="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        className={cn(
                                            "rounded-full px-2.5 py-1 text-[10px] font-black tracking-wider uppercase",
                                            auditLog.version_status ===
                                                "active" &&
                                                "border border-emerald-200 bg-emerald-100 text-emerald-800",
                                            auditLog.version_status ===
                                                "partially_active" &&
                                                "border border-amber-200 bg-amber-100 text-amber-800",
                                            auditLog.version_status ===
                                                "archived" &&
                                                "border border-slate-200 bg-slate-100 text-slate-700",
                                            auditLog.version_status ===
                                                "unavailable" &&
                                                "border border-rose-200 bg-rose-100 text-rose-800",
                                        )}
                                    >
                                        {auditLog.version_status === "active"
                                            ? "Aktif"
                                            : auditLog.version_status ===
                                                "partially_active"
                                              ? "Sebagian aktif"
                                              : auditLog.version_status ===
                                                  "archived"
                                                ? "Arsip"
                                                : "File tidak tersedia"}
                                    </span>
                                </td>
                                <td className="px-4 py-3.5 text-xs whitespace-nowrap text-[#395886]">
                                    {new Intl.DateTimeFormat("id-ID", {
                                        dateStyle: "medium",
                                        timeStyle: "short",
                                    }).format(new Date(auditLog.occurred_at))}
                                </td>
                                <td className="px-4 py-3.5">
                                    <p className="text-xs font-bold text-[#395886]">
                                        {auditLog.actor_name ?? "—"}
                                    </p>
                                    <p className="text-[11px] text-[#395886]/60">
                                        {auditLog.actor_email ?? "—"}
                                    </p>
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {auditLog.periode_semester}
                                </td>
                                <td className="max-w-64 truncate px-4 py-3.5 font-mono text-xs font-semibold text-[#395886]">
                                    <p>{auditLog.original_filename}</p>
                                    {auditLog.event_type === "rollback" ? (
                                        <p className="mt-1 whitespace-normal font-sans text-[11px] font-medium text-[#395886]/70">
                                            {auditLog.metadata?.restored
                                                ? `Dipulihkan ke: ${auditLog.metadata.restored_imports?.map((importItem) => importItem.original_filename).join(", ")}`
                                                : "Tidak ada versi sebelumnya; data aktif dibatalkan."}
                                        </p>
                                    ) : null}
                                    {auditLog.event_type === "restore" ? (
                                        <p className="mt-1 whitespace-normal font-sans text-[11px] font-medium text-[#395886]/70">
                                            Menggantikan:{" "}
                                            {auditLog.metadata?.displaced_imports
                                                ?.map(
                                                    (importItem) =>
                                                        importItem.original_filename,
                                                )
                                                .join(", ") ||
                                                "tidak ada versi aktif sebelumnya"}
                                        </p>
                                    ) : null}
                                </td>
                                <td className="px-4 py-3.5">
                                    {auditLog.can_download_file &&
                                    auditLog.source_import_id !== null ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                                        >
                                            <a
                                                href={downloadUploadedWorkbook.url(
                                                    auditLog.source_import_id,
                                                )}
                                                className="flex items-center gap-1.5"
                                            >
                                                <Download className="size-3.5" />
                                                Unduh
                                            </a>
                                        </Button>
                                    ) : (
                                        <span className="text-xs text-[#395886]/40 italic">
                                            Tidak tersedia
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.class_count}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.student_count}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.detail_count}
                                </td>
                                <td className="min-w-64 px-4 py-3.5">
                                    {auditLog.quality_report?.checks.length ? (
                                        <div className="grid gap-1">
                                            {auditLog.quality_report.checks.map(
                                                (check) => (
                                                    <p
                                                        key={check.label}
                                                        className="text-[11px] leading-tight text-[#395886]/75"
                                                    >
                                                        <span className="font-bold text-emerald-700">
                                                            ✓ {check.label}
                                                        </span>
                                                        <span className="block">
                                                            {check.detail}
                                                        </span>
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-xs text-[#395886]/40 italic">
                                            Tidak tersedia untuk unggahan lama
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3.5">
                                    {auditLog.can_restore_version &&
                                    auditLog.source_import_id !== null ? (
                                        <Form
                                            {...restoreImportVersion.form(
                                                auditLog.source_import_id,
                                            )}
                                            onBefore={() =>
                                                window.confirm(
                                                    `Jadikan ${auditLog.original_filename} sebagai data aktif? Hanya kelas yang ada di file ini yang akan diganti.`,
                                                )
                                            }
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                    className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                                >
                                                    <RotateCcw className="mr-1.5 size-3.5" />
                                                    {processing
                                                        ? "Memulihkan…"
                                                        : "Jadikan aktif"}
                                                </Button>
                                            )}
                                        </Form>
                                    ) : (
                                        <span className="text-xs text-[#395886]/40 italic">
                                            {auditLog.version_status ===
                                            "active"
                                                ? "Sedang aktif"
                                                : "—"}
                                        </span>
                                    )}
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
