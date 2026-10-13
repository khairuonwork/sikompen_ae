import { Form } from "@inertiajs/react";
import { AlertTriangle, RefreshCw, ShieldCheck } from "lucide-react";
import { retry } from "@/actions/App/Http/Controllers/KompenResponHubTaskDiagnosticController";
import { Button } from "@/components/ui/button";
import type { TaskDiagnostic } from "../shared/types";

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

export function TaskDiagnosticPanel({
    diagnostics,
}: {
    diagnostics: TaskDiagnostic[];
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="border-b border-[#F0F3FA] px-5 py-4">
                <h2 className="text-base font-extrabold text-[#395886]">Diagnostik pekerjaan</h2>
                <p className="mt-1 text-xs font-medium leading-relaxed text-[#395886]/70">
                    Menampilkan pekerjaan impor dan ekspor yang gagal atau tampak macet. Hanya pekerjaan gagal yang dapat dijadwalkan ulang; pekerjaan macet akan ditandai gagal oleh pemeriksa latar belakang terlebih dahulu.
                </p>
            </div>
            {diagnostics.length === 0 ? (
                <div className="flex flex-col items-center gap-2 px-5 py-12 text-center">
                    <ShieldCheck className="size-8 text-emerald-600" />
                    <p className="text-sm font-extrabold text-[#395886]">Tidak ada pekerjaan yang memerlukan perhatian.</p>
                    <p className="text-xs font-medium text-[#395886]/65">Impor dan ekspor terbaru berjalan atau selesai dengan normal.</p>
                </div>
            ) : (
                <div className="divide-y divide-[#F0F3FA]">
                    {diagnostics.map((task) => (
                        <article key={`${task.task_type}-${task.id}`} className="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-rose-800 uppercase">
                                        <AlertTriangle className="size-3" /> {task.status === "failed" ? "Gagal" : "Tampak macet"}
                                    </span>
                                    <p className="text-xs font-extrabold text-[#395886]">{task.label}</p>
                                </div>
                                <p className="mt-2 text-xs font-medium text-[#395886]/75">{task.error_message ?? task.progress_message}</p>
                                <p className="mt-1 text-[11px] text-[#395886]/60">
                                    Mulai: {formatJakartaDateTime(task.started_at ?? task.queued_at)}
                                </p>
                            </div>
                            {task.can_retry ? (
                                <Form {...retry.form()} className="shrink-0">
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="task_type" value={task.task_type} />
                                            <input type="hidden" name="task_id" value={task.id} />
                                            <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                <RefreshCw className="size-3.5" /> {processing ? "Menjadwalkan…" : "Coba lagi"}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
