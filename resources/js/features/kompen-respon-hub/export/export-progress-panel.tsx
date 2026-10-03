import { router } from "@inertiajs/react";
import { Download } from "lucide-react";
import { useEffect, useState } from "react";
import { download as downloadExport, show as exportTaskStatus, store as storeExport } from "@/actions/App/Http/Controllers/KompenResponHubExportController";
import { Button } from "@/components/ui/button";
import type { ExportTask, Filters } from "../shared/types";
import { ProgressBar } from "../shared/components/progress-bar";

export function queueExport(
    resource: ExportTask["resource"],
    format: ExportTask["format"],
    filters: Filters,
): void {
    router.post(
        storeExport.url(),
        {
            resource,
            format,
            nim: filters.nim,
            nama: filters.nama,
            search: filters.search,
            kelas: filters.kelas,
            tingkat: filters.tingkat,
            periode_semester: filters.periode_semester,
        },
        { preserveScroll: true },
    );
}

export function ExportProgressPanel({
    initialExportTasks,
}: {
    initialExportTasks: ExportTask[];
}): React.JSX.Element | null {
    const [exportTasks, setExportTasks] = useState(initialExportTasks);
    const activeTaskIds = exportTasks
        .filter(
            (task) => task.status === "queued" || task.status === "processing",
        )
        .map((task) => task.id);
    const activeTaskKey = activeTaskIds.join(",");

    useEffect(() => {
        setExportTasks(initialExportTasks);
    }, [initialExportTasks]);

    useEffect(() => {
        if (activeTaskIds.length === 0) {
            return;
        }

        let isMounted = true;
        const refreshProgress = async (): Promise<void> => {
            try {
                const updatedTasks: ExportTask[] = [];

                for (const exportTaskId of activeTaskIds) {
                    const task = exportTasks.find(
                        (currentTask) => currentTask.id === exportTaskId,
                    );
                    if (!task) {
                        continue;
                    }

                    const response = await fetch(
                        exportTaskStatus.url(exportTaskId, {
                            query: { token: task.access_token },
                        }),
                        {
                            credentials: "same-origin",
                            headers: {
                                Accept: "application/json",
                                "X-Requested-With": "XMLHttpRequest",
                            },
                        },
                    );

                    if (!response.ok) {
                        throw new Error("Status ekspor tidak dapat dimuat.");
                    }

                    const payload: { data: ExportTask } = await response.json();
                    updatedTasks.push(payload.data);
                }

                if (!isMounted) {
                    return;
                }

                setExportTasks((currentTasks) =>
                    currentTasks.map(
                        (task) =>
                            updatedTasks.find(
                                (updatedTask) => updatedTask.id === task.id,
                            ) ?? task,
                    ),
                );
            } catch {
                return;
            }
        };

        void refreshProgress();
        const interval = window.setInterval(() => {
            void refreshProgress();
        }, 1500);

        return () => {
            isMounted = false;
            window.clearInterval(interval);
        };
    }, [activeTaskKey]);

    if (exportTasks.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3" aria-live="polite">
            {exportTasks.map((task) => (
                <div
                    key={task.id}
                    className="grid gap-3 rounded-3xl border border-white/80 bg-white/85 p-4 shadow-sm"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-extrabold text-[#395886]">
                                Ekspor{" "}
                                {task.resource === "students"
                                    ? "Kompen dan Respon"
                                    : task.resource === "details"
                                      ? "Detail Kompen"
                                      : "Surat Peringatan"}{" "}
                                · {task.format.toUpperCase()}
                            </p>
                            <p className="mt-0.5 text-xs text-[#395886]/70">
                                {task.progress_message}
                            </p>
                        </div>
                        {task.status === "completed" ? (
                            <Button
                                asChild
                                size="sm"
                                className="rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <a
                                    href={downloadExport.url(task.id, {
                                        query: { token: task.access_token },
                                    })}
                                >
                                    <Download className="mr-1.5 size-3.5" />
                                    Unduh file
                                </a>
                            </Button>
                        ) : task.status === "failed" ? (
                            <span className="text-xs font-bold text-rose-600">
                                {task.error_message}
                            </span>
                        ) : (
                            <span className="font-mono text-xs font-bold text-[#395886]">
                                {task.progress}%
                            </span>
                        )}
                    </div>
                    {task.status === "queued" ||
                    task.status === "processing" ? (
                        <ProgressBar value={task.progress} />
                    ) : null}
                </div>
            ))}
        </section>
    );
}
