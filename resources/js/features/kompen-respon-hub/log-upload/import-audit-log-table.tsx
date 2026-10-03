import { Form } from "@inertiajs/react";
import { Download, RotateCcw } from "lucide-react";
import { downloadUploadedWorkbook } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { restore as restoreImportVersion } from "@/actions/App/Http/Controllers/KompenResponHubImportRollbackController";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { ImportAuditLog, Pagination } from "../shared/types";
import { Pager } from "../shared/components/pagination";

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
