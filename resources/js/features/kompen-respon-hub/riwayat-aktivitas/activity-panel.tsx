import { Form, Link } from "@inertiajs/react";
import { Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import type { ActivityLog, FilterOptions, Filters, Pagination } from "../shared/types";
import { Pager } from "../shared/components/pagination";

export function activityLabel(eventType: string): string {
    const descriptions: Record<string, string> = {
        "import.completed": "Impor selesai",
        "import.rolled_back": "Rollback impor",
        "import.version_restored": "Versi impor dipulihkan",
        "progress.updated": "Progres diperbarui",
        "summary.override_updated": "Koreksi total jam",
        "detail.override_updated": "Koreksi detail",
        "cutoff.updated": "Cutoff diperbarui",
        "period.closed": "Periode ditutup",
        "warning.drafted": "Draft SP dibuat",
        "warning.issued": "SP diterbitkan",
        "warning.cancelled": "SP dibatalkan",
        "warning.archived": "SP diarsipkan",
        "warning.classification_fixed": "Status SP diselaraskan",
        "warning.classification_temporary": "Status SP diselaraskan",
        "warning.resolution_updated": "Penyelesaian SP diperbarui",
        "export.completed": "Ekspor selesai",
    };

    return descriptions[eventType] ?? "Aktivitas sistem";
}

export function activityDescription(log: ActivityLog): string {
    return activityLabel(log.event_type);
}

export function ActivityFilterPanel({
    filters,
    filterOptions,
    activityFilterOptions,
}: {
    filters: Filters;
    filterOptions: FilterOptions;
    activityFilterOptions: { event_types: string[]; actor_emails: string[] };
}): React.JSX.Element {
    return (
        <Form
            {...adminIndex.form()}
            className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(190px,1fr)_minmax(170px,0.85fr)_minmax(170px,0.85fr)_auto_auto] md:items-end"
        >
            <input name="tab" type="hidden" value="activity" />
            <div className="grid flex-1 gap-1.5">
                <Label
                    htmlFor="activity-period"
                    className="text-xs font-bold text-[#395886]"
                >
                    Periode aktivitas
                </Label>
                <select
                    id="activity-period"
                    name="periode_semester"
                    defaultValue={filters.periode_semester ?? ""}
                    className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
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
                    htmlFor="activity-event"
                    className="text-xs font-bold text-[#395886]"
                >
                    Jenis aktivitas
                </Label>
                <select
                    id="activity-event"
                    name="activity_event"
                    defaultValue={filters.activity_event ?? ""}
                    className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                >
                    <option value="">Semua aktivitas</option>
                    {activityFilterOptions.event_types.map((eventType) => (
                        <option key={eventType} value={eventType}>
                            {activityLabel(eventType)}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor="activity-actor"
                    className="text-xs font-bold text-[#395886]"
                >
                    Admin pelaksana
                </Label>
                <select
                    id="activity-actor"
                    name="activity_actor"
                    defaultValue={filters.activity_actor ?? ""}
                    className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                >
                    <option value="">Semua admin</option>
                    {activityFilterOptions.actor_emails.map((email) => (
                        <option key={email} value={email}>
                            {email}
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
                <Link href={adminIndex.url({ query: { tab: "activity" } })}>
                    Reset
                </Link>
            </Button>
        </Form>
    );
}

export function ActivityLogTable({
    data,
}: {
    data: Pagination<ActivityLog>;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[750px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Waktu</th>
                            <th className="px-4 py-3">Aktivitas</th>
                            <th className="px-4 py-3">Subjek</th>
                            <th className="px-4 py-3">Aktor</th>
                            <th className="px-4 py-3">Alasan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((log) => (
                            <tr key={log.id}>
                                <td className="px-4 py-3 text-xs">
                                    {new Date(log.occurred_at).toLocaleString(
                                        "id-ID",
                                        { timeZone: "Asia/Jakarta" },
                                    )}
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold text-[#395886]">
                                    {activityDescription(log)}
                                </td>
                                <td className="px-4 py-3 text-xs">
                                    {log.subject_name ? (
                                        <>
                                            <p className="font-semibold text-[#395886]">
                                                {log.subject_name}
                                            </p>
                                            {log.nim ? (
                                                <p className="font-mono text-[11px] text-[#628ECB]">
                                                    {log.nim}
                                                </p>
                                            ) : null}
                                        </>
                                    ) : (
                                        (log.nim ?? log.subject_type)
                                    )}
                                </td>
                                <td className="px-4 py-3 text-xs">
                                    {log.actor_name ?? "Sistem"}
                                </td>
                                <td className="max-w-xs px-4 py-3 text-xs text-[#395886]/70">
                                    {log.reason ?? "—"}
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
