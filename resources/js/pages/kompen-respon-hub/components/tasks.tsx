import { router } from "@inertiajs/react";
import { Download } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { show as importTaskStatus } from "@/actions/App/Http/Controllers/KompenResponHubImportTaskController";
import {
    download as downloadExport,
    show as exportTaskStatus,
    store as storeExport,
} from "@/actions/App/Http/Controllers/KompenResponHubExportController";
import { Button } from "@/components/ui/button";
import type { ExportTask, Filters, ImportTask } from "../types";

export function ProgressBar({ value }: { value: number }): React.JSX.Element {
    const progress = Math.min(100, Math.max(0, value));

    return (
        <div
            className="h-2.5 overflow-hidden rounded-full bg-[#D5DEEF]/60 shadow-inner"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={progress}
            aria-label={`Progres impor ${progress}%`}
        >
            <div
                className="h-full rounded-full bg-gradient-to-r from-[#395886] to-[#628ECB] transition-[width] duration-300"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}

export function UploadProgressPanel({
    progress,
}: {
    progress: number;
}): React.JSX.Element {
    return (
        <div className="grid gap-2.5 rounded-2xl border border-[#8AAEE0]/50 bg-[#B1C9EF]/20 p-4 backdrop-blur-md">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className="font-extrabold text-[#395886]">
                    Mengirim workbook ke server
                </span>
                <span className="font-mono font-bold text-[#395886] tabular-nums">
                    {progress}%
                </span>
            </div>
            <ProgressBar value={progress} />
            <p className="text-[11px] font-medium text-[#395886]/70">
                Jangan berpindah menu sampai pengiriman file selesai.
            </p>
        </div>
    );
}

export function ImportProgressPanel({
    initialImportTasks,
}: {
    initialImportTasks: ImportTask[];
}): React.JSX.Element | null {
    const [importTasks, setImportTasks] = useState(initialImportTasks);
    const notifiedFailedTaskIds = useRef<Set<number>>(new Set());
    const activeTaskIds = importTasks
        .filter(
            (importTask) =>
                importTask.status === "queued" ||
                importTask.status === "processing",
        )
        .map((importTask) => importTask.id);
    const activeTaskKey = activeTaskIds.join(",");

    useEffect(() => {
        setImportTasks(initialImportTasks);
    }, [initialImportTasks]);

    useEffect(() => {
        if (activeTaskIds.length === 0) {
            return;
        }

        let isMounted = true;

        const refreshProgress = async (): Promise<void> => {
            try {
                const updatedTasks = await Promise.all(
                    activeTaskIds.map(async (importTaskId) => {
                        const response = await fetch(
                            importTaskStatus.url(importTaskId),
                            {
                                credentials: "same-origin",
                                headers: {
                                    Accept: "application/json",
                                    "X-Requested-With": "XMLHttpRequest",
                                },
                            },
                        );

                        if (!response.ok) {
                            throw new Error("Status impor tidak dapat dimuat.");
                        }

                        const payload: { data: ImportTask } =
                            await response.json();

                        return payload.data;
                    }),
                );

                if (!isMounted) {
                    return;
                }

                updatedTasks
                    .filter((task) => task.status === "failed")
                    .forEach((task) => {
                        if (notifiedFailedTaskIds.current.has(task.id)) {
                            return;
                        }

                        notifiedFailedTaskIds.current.add(task.id);
                        toast.error("Impor perlu diperbaiki", {
                            description:
                                task.error_message ??
                                "Periksa keterangan pada proses impor.",
                        });
                    });

                setImportTasks((currentTasks) =>
                    currentTasks.map(
                        (importTask) =>
                            updatedTasks.find(
                                (updatedTask) =>
                                    updatedTask.id === importTask.id,
                            ) ?? importTask,
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

    if (importTasks.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3" aria-live="polite">
            {importTasks.map((importTask) => (
                <div
                    key={importTask.id}
                    className="animate-in fade-in grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl duration-300"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-extrabold text-[#395886]">
                                Memproses {importTask.original_filename}
                            </p>
                            <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                {importTask.progress_message}
                            </p>
                        </div>
                        <span className="rounded-full border border-[#8AAEE0]/40 bg-[#B1C9EF]/30 px-3 py-1 font-mono text-sm font-bold text-[#395886] tabular-nums">
                            {importTask.progress}%
                        </span>
                    </div>
                    <ProgressBar value={importTask.progress} />
                    {importTask.error_message ? (
                        <p className="mt-1 text-xs font-bold text-rose-600">
                            {importTask.error_message}
                        </p>
                    ) : null}
                </div>
            ))}
        </section>
    );
}

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
