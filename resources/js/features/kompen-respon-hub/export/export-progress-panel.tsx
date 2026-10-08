import { router } from "@inertiajs/react";
import { AlertTriangle, Download, LoaderCircle, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import {
    dismiss as dismissExport,
    destroy as cancelExport,
    download as downloadExport,
    show as exportTaskStatus,
    store as storeExport,
} from "@/actions/App/Http/Controllers/KompenResponHubExportController";
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
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Ekspor masuk antrean", {
                    description:
                        "Anda dapat tetap bekerja. Status dan tautan unduh akan muncul di bawah.",
                });
            },
            onError: () => {
                toast.error("Ekspor belum dapat dibuat", {
                    description:
                        "Periksa filter ekspor, lalu coba lagi.",
                });
            },
        },
    );
}

function resourceLabel(resource: ExportTask["resource"]): string {
    return resource === "students"
        ? "Kompen dan Respon"
        : resource === "details"
          ? "Detail Kompen"
          : "Surat Peringatan";
}

function isActive(task: ExportTask): boolean {
    return task.status === "queued" || task.status === "processing";
}

function hasStalled(task: ExportTask, stalledAfterMinutes: number): boolean {
    const startedAt = task.started_at ?? task.queued_at;
    if (startedAt === null) {
        return false;
    }

    return Date.now() - Date.parse(startedAt) >= stalledAfterMinutes * 60_000;
}

export function ExportProgressPanel({
    initialExportTasks,
    stalledAfterMinutes,
}: {
    initialExportTasks: ExportTask[];
    stalledAfterMinutes: number;
}): React.JSX.Element | null {
    const [exportTasks, setExportTasks] = useState(initialExportTasks);
    const [isCancellingTaskId, setIsCancellingTaskId] = useState<number | null>(
        null,
    );
    const [isDismissingTaskId, setIsDismissingTaskId] = useState<number | null>(
        null,
    );
    const hasReportedStatusFailure = useRef(false);
    const notifiedTerminalTaskIds = useRef<Set<number>>(new Set());
    const exportTasksRef = useRef(exportTasks);
    const activeTaskIds = exportTasks.filter(isActive).map((task) => task.id);
    const activeTaskKey = activeTaskIds.join(",");

    useEffect(() => {
        setExportTasks(initialExportTasks);
    }, [initialExportTasks]);

    useEffect(() => {
        exportTasksRef.current = exportTasks;
    }, [exportTasks]);

    useEffect(() => {
        if (activeTaskIds.length === 0) {
            return;
        }

        let isMounted = true;
        const refreshProgress = async (): Promise<void> => {
            try {
                const updatedTasks: ExportTask[] = [];

                for (const exportTaskId of activeTaskIds) {
                    const task = exportTasksRef.current.find(
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

                updatedTasks
                    .filter((task) => !isActive(task))
                    .forEach((task) => {
                        if (notifiedTerminalTaskIds.current.has(task.id)) {
                            return;
                        }

                        notifiedTerminalTaskIds.current.add(task.id);
                        if (task.status === "completed") {
                            toast.success("File ekspor siap diunduh");
                        } else if (task.status === "failed") {
                            toast.error("Ekspor tidak dapat dibuat", {
                                description: task.error_message ?? undefined,
                            });
                        }
                    });

                setExportTasks((currentTasks) =>
                    currentTasks.map(
                        (task) =>
                            updatedTasks.find(
                                (updatedTask) => updatedTask.id === task.id,
                            ) ?? task,
                    ),
                );
            } catch {
                if (!hasReportedStatusFailure.current) {
                    hasReportedStatusFailure.current = true;
                    toast.error("Status ekspor tidak dapat diperbarui", {
                        description:
                            "Muat ulang halaman. Permintaan tetap diproses di server.",
                    });
                }
            }
        };

        void refreshProgress();
        const interval = window.setInterval(() => {
            void refreshProgress();
        }, 2_000);

        return () => {
            isMounted = false;
            window.clearInterval(interval);
        };
    }, [activeTaskKey]);

    function cancelTask(task: ExportTask): void {
        setIsCancellingTaskId(task.id);
        router.delete(
            cancelExport.url(task.id, {
                query: { token: task.access_token },
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Ekspor dibatalkan", {
                        description:
                            "File tidak akan dibuat. Anda dapat membuat permintaan baru kapan saja.",
                    });
                },
                onError: () => {
                    toast.error("Ekspor tidak dapat dibatalkan", {
                        description:
                            "Mungkin file sudah selesai dibuat. Muat ulang status untuk memeriksa.",
                    });
                },
                onFinish: () => setIsCancellingTaskId(null),
            },
        );
    }

    function dismissTask(task: ExportTask): void {
        setIsDismissingTaskId(task.id);
        router.post(
            dismissExport.url(task.id, {
                query: { token: task.access_token },
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setExportTasks((tasks) =>
                        tasks.filter((currentTask) => currentTask.id !== task.id),
                    );
                },
                onError: () => {
                    toast.error("Notifikasi ekspor tidak dapat ditutup", {
                        description: "Muat ulang halaman, lalu coba lagi.",
                    });
                },
                onFinish: () => setIsDismissingTaskId(null),
            },
        );
    }

    if (exportTasks.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3" aria-live="polite">
            {exportTasks.map((task) => {
                const taskIsActive = isActive(task);
                const taskHasStalled = hasStalled(task, stalledAfterMinutes);

                return (
                    <div
                        key={task.id}
                        className="grid gap-3 rounded-3xl border border-white/80 bg-white/85 p-4 shadow-sm"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-extrabold text-[#395886]">
                                    Ekspor {resourceLabel(task.resource)} · {task.format.toUpperCase()}
                                </p>
                                <p className="mt-0.5 text-xs text-[#395886]/70">
                                    {task.progress_message}
                                </p>
                            </div>
                            {task.status === "completed" ? (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        asChild
                                        size="sm"
                                        className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
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
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={isDismissingTaskId === task.id}
                                        onClick={() => dismissTask(task)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#B1C9EF]/30"
                                    >
                                        <X className="mr-1.5 size-3.5" />
                                        Tutup
                                    </Button>
                                </div>
                            ) : taskIsActive ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={isCancellingTaskId === task.id}
                                    onClick={() => cancelTask(task)}
                                    className="rounded-xl border-rose-200 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white disabled:opacity-60"
                                >
                                    {isCancellingTaskId === task.id ? (
                                        <LoaderCircle className="mr-1.5 size-3.5 animate-spin" />
                                    ) : (
                                        <X className="mr-1.5 size-3.5" />
                                    )}
                                    Batalkan
                                </Button>
                            ) : task.status === "failed" ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-bold text-rose-600">
                                        {task.error_message}
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={isDismissingTaskId === task.id}
                                        onClick={() => dismissTask(task)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#B1C9EF]/30"
                                    >
                                        <X className="mr-1.5 size-3.5" />
                                        Tutup
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                        Dibatalkan
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={isDismissingTaskId === task.id}
                                        onClick={() => dismissTask(task)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#B1C9EF]/30"
                                    >
                                        <X className="mr-1.5 size-3.5" />
                                        Tutup
                                    </Button>
                                </div>
                            )}
                        </div>
                        {taskIsActive ? <ProgressBar value={task.progress} /> : null}
                        {taskHasStalled ? (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                <span className="flex items-center gap-1.5 font-semibold">
                                    <AlertTriangle className="size-3.5" />
                                    Proses melewati {stalledAfterMinutes} menit. Batalkan, lalu ulangi dengan filter ekspor yang lebih spesifik.
                                </span>
                            </div>
                        ) : null}
                    </div>
                );
            })}
        </section>
    );
}
