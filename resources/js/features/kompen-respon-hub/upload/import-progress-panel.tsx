import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { show as importTaskStatus } from "@/actions/App/Http/Controllers/KompenResponHubImportTaskController";
import type { ImportTask } from "../shared/types";
import { ProgressBar } from "../shared/components/progress-bar";

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
