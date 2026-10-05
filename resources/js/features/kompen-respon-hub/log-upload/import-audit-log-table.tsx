import { Download } from "lucide-react";
import { downloadUploadedWorkbook } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Pager } from "../shared/components/pagination";
import type { ImportAuditLog, Pagination } from "../shared/types";

function formatJakartaDateTime(value: string | null): string {
    if (value === null) {
        return "—";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Jakarta",
    }).format(new Date(value));
}

function eventLabel(eventType: ImportAuditLog["event_type"]): string {
    return {
        upload: "Upload",
        activate: "Aktivasi",
        rollback: "Rollback",
        restore: "Pemulihan lama",
    }[eventType];
}

function eventDescription(eventType: ImportAuditLog["event_type"]): string {
    return {
        upload: "Workbook disimpan dan siap dipilih.",
        activate: "Workbook ditetapkan sebagai sumber data periode.",
        rollback: "Data dari workbook dihapus melalui rollback.",
        restore: "Riwayat workbook dipulihkan.",
    }[eventType];
}

export function ImportAuditLogTable({
    data,
}: {
    data: Pagination<ImportAuditLog>;
}): React.JSX.Element {
    const headings = [
        "Aksi",
        "Periode",
        "Workbook",
        "Pelaksana",
        "Waktu tindakan",
        "File",
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-sm">
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
                            <tr key={auditLog.id} className="hover:bg-[#B1C9EF]/10">
                                <td className="px-4 py-3.5 whitespace-nowrap">
                                    <span className={cn(
                                        "rounded-full px-2.5 py-1 text-[10px] font-black tracking-wider uppercase",
                                        auditLog.event_type === "upload" && "border border-emerald-200 bg-emerald-100 text-emerald-800",
                                        auditLog.event_type === "activate" && "border border-blue-200 bg-blue-100 text-blue-800",
                                        auditLog.event_type !== "upload" && auditLog.event_type !== "activate" && "border border-slate-200 bg-slate-100 text-slate-700",
                                    )}>
                                        {eventLabel(auditLog.event_type)}
                                    </span>
                                    <p className="mt-1 max-w-48 text-[11px] leading-relaxed text-[#395886]/70">
                                        {eventDescription(auditLog.event_type)}
                                    </p>
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">{auditLog.periode_semester}</td>
                                <td className="max-w-64 px-4 py-3.5">
                                    <p className="truncate font-mono text-xs font-semibold text-[#395886]" title={auditLog.original_filename}>
                                        {auditLog.original_filename}
                                    </p>
                                    <p className="mt-1 text-[11px] text-[#395886]/60">
                                        Diunggah {formatJakartaDateTime(auditLog.version_imported_at)}
                                    </p>
                                </td>
                                <td className="px-4 py-3.5">
                                    <p className="text-xs font-bold text-[#395886]">{auditLog.actor_name ?? "—"}</p>
                                    <p className="text-[11px] text-[#395886]/60">{auditLog.actor_email ?? "—"}</p>
                                </td>
                                <td className="px-4 py-3.5 text-xs whitespace-nowrap text-[#395886]">{formatJakartaDateTime(auditLog.occurred_at)}</td>
                                <td className="px-4 py-3.5">
                                    {auditLog.can_download_file && auditLog.source_import_id !== null ? (
                                        <Button asChild size="sm" variant="outline" className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                            <a href={downloadUploadedWorkbook.url(auditLog.source_import_id)}>
                                                <Download className="mr-1.5 size-3.5" /> Unduh
                                            </a>
                                        </Button>
                                    ) : (
                                        <span className="text-xs text-[#395886]/40 italic">Tidak tersedia</span>
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
