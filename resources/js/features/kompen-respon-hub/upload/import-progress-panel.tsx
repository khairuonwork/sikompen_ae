import { useEffect, useRef, useState } from "react";
import { X } from "lucide-react";
import { toast } from "sonner";
import { showTask as activeStudentImportTaskStatus } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { show as importTaskStatus } from "@/actions/App/Http/Controllers/KompenResponHubImportTaskController";
import { Button } from "@/components/ui/button";
import type { ImportTask } from "../shared/types";
import { ProgressBar } from "../shared/components/progress-bar";

const dismissedImportTasksStorageKey = "sikompen-dismissed-import-tasks";

function taskKey(importTask: ImportTask): string {
    return `${importTask.import_type}:${importTask.id}`;
}

function dismissedTaskKeysFromSession(): Set<string> {
    if (typeof window === "undefined") {
        return new Set();
    }

    try {
        const storedKeys: unknown = JSON.parse(
            window.sessionStorage.getItem(dismissedImportTasksStorageKey) ??
                "[]",
        );

        return new Set(
            Array.isArray(storedKeys)
                ? storedKeys.filter((key): key is string => typeof key === "string")
                : [],
        );
    } catch {
        return new Set();
    }
}

export function ImportProgressPanel({
    initialImportTasks,
}: {
    initialImportTasks: ImportTask[];
}): React.JSX.Element | null {
    const [importTasks, setImportTasks] = useState(initialImportTasks);
    const [dismissedTaskKeys, setDismissedTaskKeys] = useState<Set<string>>(
        dismissedTaskKeysFromSession,
    );
    const notifiedFailedTaskIds = useRef<Set<string>>(new Set());
    const activeTaskIds = importTasks
        .filter(
            (importTask) =>
                importTask.status === "queued" ||
                importTask.status === "processing",
        )
        .map(taskKey);
    const activeTaskKey = activeTaskIds.join(",");
    const visibleImportTasks = importTasks.filter(
        (importTask) => !dismissedTaskKeys.has(taskKey(importTask)),
    );

    useEffect(() => {
        setImportTasks(initialImportTasks);
    }, [initialImportTasks]);

    useEffect(() => {
        const failedTasks = importTasks.filter(
            (importTask) =>
                importTask.status === "failed" &&
                !dismissedTaskKeys.has(taskKey(importTask)),
        );

        if (failedTasks.length === 0) {
            return;
        }

        failedTasks.forEach((task) => {
            const failedTaskKey = taskKey(task);

            if (notifiedFailedTaskIds.current.has(failedTaskKey)) {
                return;
            }

            notifiedFailedTaskIds.current.add(failedTaskKey);
            toast.error("Impor gagal", {
                description:
                    task.error_message ??
                    "File unggahan dibersihkan. Perbaiki file lalu unggah ulang.",
            });
        });

        setDismissedTaskKeys((currentTaskKeys) => {
            const updatedTaskKeys = new Set(currentTaskKeys);
            failedTasks.forEach((task) => updatedTaskKeys.add(taskKey(task)));

            try {
                window.sessionStorage.setItem(
                    dismissedImportTasksStorageKey,
                    JSON.stringify([...updatedTaskKeys]),
                );
            } catch {}

            return updatedTaskKeys;
        });
    }, [dismissedTaskKeys, importTasks]);

    useEffect(() => {
        if (activeTaskIds.length === 0) {
            return;
        }

        let isMounted = true;

        const refreshProgress = async (): Promise<void> => {
            try {
                const updatedTasks = await Promise.all(
                    importTasks
                        .filter(
                            (importTask) =>
                                importTask.status === "queued" ||
                                importTask.status === "processing",
                        )
                        .map(async (importTask) => {
                            const response = await fetch(
                                importTask.import_type === "active_students"
                                    ? activeStudentImportTaskStatus.url(
                                          importTask.id,
                                      )
                                    : importTaskStatus.url(importTask.id),
                                {
                                    credentials: "same-origin",
                                    headers: {
                                        Accept: "application/json",
                                        "X-Requested-With": "XMLHttpRequest",
                                    },
                                },
                            );

                            if (!response.ok) {
                                throw new Error(
                                    "Status impor tidak dapat dimuat.",
                                );
                            }

                            const payload: { data: ImportTask } =
                                await response.json();

                            return payload.data;
                        }),
                );

                if (!isMounted) {
                    return;
                }

                setImportTasks((currentTasks) =>
                    currentTasks.map(
                        (importTask) =>
                            updatedTasks.find(
                                (updatedTask) =>
                                    updatedTask.id === importTask.id &&
                                    updatedTask.import_type ===
                                        importTask.import_type,
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

    function dismissTask(importTask: ImportTask): void {
        const dismissedTaskKey = taskKey(importTask);

        setDismissedTaskKeys((currentTaskKeys) => {
            const updatedTaskKeys = new Set(currentTaskKeys);
            updatedTaskKeys.add(dismissedTaskKey);

            try {
                window.sessionStorage.setItem(
                    dismissedImportTasksStorageKey,
                    JSON.stringify([...updatedTaskKeys]),
                );
            } catch {}

            return updatedTaskKeys;
        });
    }

    if (visibleImportTasks.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3" aria-live="polite">
            {visibleImportTasks.map((importTask) => (
                <div
                    key={taskKey(importTask)}
                    className="animate-in fade-in grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl duration-300"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-extrabold text-[#395886]">
                                {importTask.import_type === "active_students"
                                    ? "Memproses daftar mahasiswa aktif"
                                    : "Memproses workbook Kompen dan Respon"}
                            </p>
                            <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                {importTask.progress_message}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full border border-[#8AAEE0]/40 bg-[#B1C9EF]/30 px-3 py-1 font-mono text-sm font-bold text-[#395886] tabular-nums">
                                {importTask.progress}%
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                onClick={() => dismissTask(importTask)}
                                className="size-8 rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#B1C9EF]/30 hover:text-[#395886]"
                                aria-label="Tutup notifikasi impor"
                                title="Tutup notifikasi. Proses impor tetap berjalan di latar belakang."
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
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
