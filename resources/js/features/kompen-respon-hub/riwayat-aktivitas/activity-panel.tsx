import { Form, Link } from "@inertiajs/react";
import { Search } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import type { ActivityLog, FilterOptions, Filters, Pagination } from "../shared/types";
import { Pager } from "../shared/components/pagination";

export function activityLabel(eventType: string): string {
    const descriptions: Record<string, string> = {
        "import.completed": "Impor selesai",
        "import.rolled_back": "Rollback impor",
        "import.version_restored": "Versi impor dipulihkan",
        "import.version_activated": "Versi file diaktifkan",
        "progress.updated": "Progres diperbarui",
        "summary.override_updated": "Koreksi total jam",
        "detail.override_updated": "Koreksi detail",
        "cutoff.updated": "Cutoff diperbarui",
        "period.finalized": "SP periode difinalisasi",
        "warning.issued": "SP diterbitkan",
        "warning.updated": "Catatan SP diperbarui",
        "warning.rolled_back": "SP di-rollback",
        "warning.archived": "Kandidat periode dicatat",
        "warning.classification_fixed": "SP masuk periode cutoff",
        "warning.classification_temporary": "SP kembali ke masa pengerjaan",
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
    const [selectedPeriod, setSelectedPeriod] = useState(
        filters.periode_semester ?? "",
    );
    const [selectedEvent, setSelectedEvent] = useState(
        filters.activity_event ?? "",
    );
    const [selectedActor, setSelectedActor] = useState(
        filters.activity_actor ?? "",
    );

    return (
        <Form
            {...adminIndex.form()}
            className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(190px,1fr)_minmax(170px,0.85fr)_minmax(170px,0.85fr)_auto_auto] md:items-end"
        >
            <input name="tab" type="hidden" value="activity" />
            <input
                name="periode_semester"
                type="hidden"
                value={selectedPeriod}
            />
            <input
                name="activity_event"
                type="hidden"
                value={selectedEvent}
            />
            <input
                name="activity_actor"
                type="hidden"
                value={selectedActor}
            />
            <div className="grid flex-1 gap-1.5">
                <Label
                    htmlFor="activity-period"
                    className="text-xs font-bold text-[#395886]"
                >
                    Periode aktivitas
                </Label>
                <Select
                    value={selectedPeriod || "all"}
                    onValueChange={(value) =>
                        setSelectedPeriod(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger
                        id="activity-period"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                    >
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
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor="activity-event"
                    className="text-xs font-bold text-[#395886]"
                >
                    Jenis aktivitas
                </Label>
                <Select
                    value={selectedEvent || "all"}
                    onValueChange={(value) =>
                        setSelectedEvent(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger
                        id="activity-event"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                    >
                        <SelectValue placeholder="Semua aktivitas" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua aktivitas
                        </SelectItem>
                        {activityFilterOptions.event_types.map((eventType) => (
                            <SelectItem
                                key={eventType}
                                value={eventType}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {activityLabel(eventType)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1.5">
                <Label
                    htmlFor="activity-actor"
                    className="text-xs font-bold text-[#395886]"
                >
                    Admin pelaksana
                </Label>
                <Select
                    value={selectedActor || "all"}
                    onValueChange={(value) =>
                        setSelectedActor(value === "all" ? "" : value)
                    }
                >
                    <SelectTrigger
                        id="activity-actor"
                        className="h-10 w-full rounded-2xl border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white"
                    >
                        <SelectValue placeholder="Semua admin" />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        <SelectItem
                            value="all"
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Semua admin
                        </SelectItem>
                        {activityFilterOptions.actor_emails.map((email) => (
                            <SelectItem
                                key={email}
                                value={email}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {email}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <Button
                type="submit"
                className="rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:text-white active:scale-95"
            >
                <Search className="mr-1.5 size-4" />
                Terapkan
            </Button>
            <Button
                asChild
                type="button"
                variant="outline"
                className="rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
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
