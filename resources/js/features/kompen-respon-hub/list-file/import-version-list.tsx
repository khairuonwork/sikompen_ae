import { Form } from "@inertiajs/react";
import { CheckCircle2, Download, FileSpreadsheet, UploadCloud } from "lucide-react";
import { store as activate } from "@/actions/App/Http/Controllers/KompenResponHubImportActivationController";
import { downloadUploadedWorkbook } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { Button } from "@/components/ui/button";
import { Pager } from "../shared/components/pagination";
import type { ImportVersion, Pagination } from "../shared/types";

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

export function ImportVersionList({
    data,
}: {
    data: Pagination<ImportVersion>;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1080px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3.5">Periode</th>
                            <th className="px-4 py-3.5">Workbook</th>
                            <th className="px-4 py-3.5">Diunggah</th>
                            <th className="px-4 py-3.5">Isi</th>
                            <th className="px-4 py-3.5">Status sumber</th>
                            <th className="px-4 py-3.5 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((version) => (
                            <tr key={version.id} className="hover:bg-[#B1C9EF]/10">
                                <td className="px-4 py-3.5 text-xs font-bold text-[#395886]">
                                    {version.periode_semester}
                                </td>
                                <td className="max-w-72 px-4 py-3.5">
                                    <p className="truncate font-mono text-xs font-bold text-[#395886]">
                                        {version.original_filename}
                                    </p>
                                    <p className="mt-1 text-[11px] text-[#395886]/60">
                                        {version.uploaded_by_name ?? version.uploaded_by_email ?? "Admin tidak diketahui"}
                                    </p>
                                </td>
                                <td className="px-4 py-3.5 text-xs text-[#395886]">
                                    {formatJakartaDateTime(version.imported_at)}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#395886] tabular-nums">
                                    {version.class_count} kelas · {version.student_count} mahasiswa · {version.detail_count} detail
                                </td>
                                <td className="px-4 py-3.5">
                                    {version.is_active ? (
                                        <div>
                                            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                                <CheckCircle2 className="size-3" /> Aktif
                                            </span>
                                            <p className="mt-1 text-[11px] text-[#395886]/60">
                                                Sejak {formatJakartaDateTime(version.activated_at)}
                                            </p>
                                        </div>
                                    ) : (
                                        <span className="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-slate-700 uppercase">
                                            Tersimpan
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3.5">
                                    <div className="flex justify-end gap-2">
                                        <Button asChild size="sm" variant="outline" className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                            <a href={downloadUploadedWorkbook.url(version.id)}>
                                                <Download className="mr-1.5 size-3.5" /> Unduh
                                            </a>
                                        </Button>
                                        {version.is_active ? (
                                            <Button size="sm" disabled className="rounded-xl bg-emerald-600 text-xs font-bold text-white disabled:opacity-100">
                                                Digunakan
                                            </Button>
                                        ) : (
                                            <Form
                                                {...activate.form(version.id)}
                                                onBefore={() => window.confirm(`Gunakan ${version.original_filename} sebagai sumber data aktif untuk periode ${version.periode_semester}? Data sumber periode tersebut akan diganti, sedangkan koreksi manual dan riwayat tetap dipertahankan.`)}
                                            >
                                                {({ processing }) => (
                                                    <Button type="submit" size="sm" disabled={processing} className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                        <UploadCloud className="mr-1.5 size-3.5" />
                                                        {processing ? "Memilih…" : "Jadikan aktif"}
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
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
