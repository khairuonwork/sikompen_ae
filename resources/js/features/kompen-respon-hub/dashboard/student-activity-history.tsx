import { History } from "lucide-react";
import { cn } from "@/lib/utils";
import { number } from "../shared/lib/formatters";
import type { ActivityLog, ActivityState } from "../shared/types";
import { activityDescription } from "../riwayat-aktivitas/activity-panel";

const fieldLabels: Record<string, string> = {
    kompensasi_dikerjakan_jam: "Kompen dikerjakan",
    responsi_dikerjakan_jam: "Responsi dikerjakan",
    total_kompensasi_jam: "Total Kompen",
    total_responsi_jam: "Total Responsi",
    total_hutang_jam: "Total hutang",
    sisa_hutang_jam: "Sisa hutang",
    deadline_at: "Batas waktu",
    closed_at: "Waktu penutupan",
    classification: "Kategori kandidat",
    letter_status: "Status SP",
    resolution: "Status penyelesaian",
    issued_at: "Waktu penerbitan",
    cancelled_at: "Waktu pembatalan",
    jam_kompensasi: "Jam Kompen",
    jam_responsi: "Jam Responsi",
    tanggal: "Tanggal kejadian",
    keterangan: "Keterangan",
};

export function StudentActivityHistory({
    activities,
}: {
    activities: ActivityLog[];
}): React.JSX.Element {
    return (
        <section className="rounded-2xl border border-[#D5DEEF] p-4">
            <div className="flex items-center gap-2">
                <History className="size-4 text-[#628ECB]" />
                <div>
                    <p className="text-xs font-black tracking-wide text-[#395886] uppercase">
                        Riwayat perubahan
                    </p>
                    <p className="mt-0.5 text-[11px] text-[#395886]/65">
                        Nilai sebelum dan sesudah ditampilkan bila aktivitas mengubah data.
                    </p>
                </div>
            </div>

            <div className="mt-3 grid gap-2">
                {activities.length ? (
                    activities.map((activity, index) => (
                        <ActivityHistoryEntry
                            key={activity.id}
                            activity={activity}
                            defaultOpen={index === 0}
                        />
                    ))
                ) : (
                    <p className="text-xs text-[#395886]/65">
                        Belum ada perubahan yang tercatat untuk mahasiswa ini.
                    </p>
                )}
            </div>
        </section>
    );
}

function ActivityHistoryEntry({
    activity,
    defaultOpen,
}: {
    activity: ActivityLog;
    defaultOpen: boolean;
}): React.JSX.Element {
    const changes = stateChanges(activity.before_state, activity.after_state);

    return (
        <details
            open={defaultOpen}
            className="group rounded-xl border border-[#D5DEEF] bg-[#F0F3FA]/45 px-3 py-2.5"
        >
            <summary className="cursor-pointer list-none pr-6 focus-visible:outline-none">
                <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                    <div>
                        <p className="text-xs font-bold text-[#395886]">
                            {activityDescription(activity)}
                        </p>
                        <p className="mt-0.5 text-[11px] text-[#395886]/65">
                            {new Date(activity.occurred_at).toLocaleString(
                                "id-ID",
                                { timeZone: "Asia/Jakarta" },
                            )}
                            {activity.actor_name
                                ? ` · ${activity.actor_name}`
                                : " · Sistem"}
                        </p>
                    </div>
                    <span
                        className={cn(
                            "rounded-full px-2 py-0.5 text-[10px] font-bold",
                            changes.length
                                ? "bg-[#B1C9EF]/60 text-[#395886]"
                                : "bg-white text-[#395886]/70",
                        )}
                    >
                        {changes.length
                            ? `${changes.length} nilai berubah`
                            : "Catatan aktivitas"}
                    </span>
                </div>
            </summary>

            <div className="mt-3 grid gap-3 border-t border-[#D5DEEF] pt-3">
                {activity.reason ? (
                    <p className="rounded-lg bg-white/75 px-3 py-2 text-xs leading-relaxed text-[#395886]/75">
                        {activity.reason}
                    </p>
                ) : null}
                {changes.length ? (
                    <dl className="grid gap-2">
                        {changes.map((change) => (
                            <div
                                key={change.key}
                                className="grid gap-1 rounded-lg bg-white/75 px-3 py-2 text-xs sm:grid-cols-[minmax(130px,0.8fr)_1fr_auto_1fr] sm:items-center sm:gap-3"
                            >
                                <dt className="font-semibold text-[#395886]">
                                    {fieldLabels[change.key] ?? change.key}
                                </dt>
                                <dd className="font-mono text-[#395886]/70 break-words">
                                    {formatStateValue(change.key, change.before)}
                                </dd>
                                <span
                                    aria-hidden="true"
                                    className="font-bold text-[#628ECB]"
                                >
                                    →
                                </span>
                                <dd className="font-mono font-bold text-[#395886] break-words">
                                    {formatStateValue(change.key, change.after)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                ) : (
                    <p className="text-xs text-[#395886]/65">
                        Aktivitas ini tidak mengubah nilai yang dapat dibandingkan.
                    </p>
                )}
            </div>
        </details>
    );
}

function stateChanges(
    beforeState: ActivityState,
    afterState: ActivityState,
): { key: string; before: unknown; after: unknown }[] {
    const before = beforeState ?? {};
    const after = afterState ?? {};
    const keys = new Set([...Object.keys(before), ...Object.keys(after)]);

    return [...keys]
        .filter((key) => !valuesAreEqual(before[key], after[key]))
        .map((key) => ({
            key,
            before: before[key],
            after: after[key],
        }));
}

function valuesAreEqual(firstValue: unknown, secondValue: unknown): boolean {
    return JSON.stringify(firstValue) === JSON.stringify(secondValue);
}

function formatStateValue(key: string, value: unknown): string {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    if (typeof value === "boolean") {
        return value ? "Ya" : "Tidak";
    }

    if (key.endsWith("_at") && typeof value === "string") {
        const date = new Date(value);

        if (!Number.isNaN(date.getTime())) {
            return date.toLocaleString("id-ID", { timeZone: "Asia/Jakarta" });
        }
    }

    if (key.includes("jam") && (typeof value === "number" || typeof value === "string")) {
        return `${number(String(value))} jam`;
    }

    if (typeof value === "string" || typeof value === "number") {
        return String(value);
    }

    return JSON.stringify(value);
}
