import { Form } from "@inertiajs/react";
import { CheckCircle2, Download, UsersRound } from "lucide-react";
import { activate } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { downloadUploadedWorkbook } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { Button } from "@/components/ui/button";
import { Pager } from "../shared/components/pagination";
import type {
    ActiveStudentImportSource,
    ActiveStudentImportVersion,
    Pagination,
} from "../shared/types";

type SourcesByPeriod = Record<string, ActiveStudentImportSource[]>;

function activeClassesSummary(classes: string[]): {
    label: string;
    preview: string;
} | null {
    if (classes.length === 0) {
        return null;
    }

    const visibleClasses = classes.slice(0, 3);
    const remainingClasses = classes.length - visibleClasses.length;

    return {
        label: `${classes.length} kelas digunakan`,
        preview: remainingClasses > 0
            ? `${visibleClasses.join(", ")} +${remainingClasses} lainnya`
            : visibleClasses.join(", "),
    };
}

function formatJakartaDateTime(value: string): string {
    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Jakarta",
    }).format(new Date(value));
}

export function ActiveStudentImportList({
    sources,
    versions,
}: {
    sources: Pagination<ActiveStudentImportSource>;
    versions: Pagination<ActiveStudentImportVersion>;
}): React.JSX.Element {
    const sourcesByPeriod = sources.data.reduce<SourcesByPeriod>((grouped, source) => {
        const period = source.periode_semester ?? "Periode belum ditetapkan";
        grouped[period] ??= [];
        grouped[period].push(source);

        return grouped;
    }, {});

    return (
        <section className="grid gap-4">
            <div className="rounded-3xl border border-[#B1C9EF]/70 bg-[#B1C9EF]/15 p-5">
                <div className="flex flex-col gap-1">
                    <h3 className="text-sm font-extrabold text-[#395886]">
                        Daftar mahasiswa aktif yang digunakan
                    </h3>
                    <p className="text-xs font-medium text-[#395886]/70">
                        Satu file roster aktif untuk setiap kombinasi periode dan kelas. Kelas atau periode lain dapat memakai file berbeda.
                    </p>
                </div>
                {sources.data.length > 0 ? (
                    <div className="mt-4 grid gap-5">
                        {Object.entries(sourcesByPeriod).map(([period, periodSources]) => (
                            <div key={period} className="grid gap-3">
                                <p className="text-xs font-black tracking-[0.1em] text-[#395886] uppercase">
                                    {period}
                                </p>
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {periodSources.map((source) => (
                                        <article
                                            key={source.id}
                                            className="rounded-2xl border border-white bg-white/90 p-4 shadow-2xs"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-xs font-extrabold text-[#395886]">
                                                    Kelas {source.kelas}
                                                </p>
                                                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                                    <CheckCircle2 className="size-3" /> Digunakan
                                                </span>
                                            </div>
                                            <p className="mt-3 break-words font-mono text-xs font-bold text-[#395886]">
                                                {source.import.original_filename}
                                            </p>
                                            <p className="mt-1 text-[11px] text-[#395886]/60">
                                                Dipilih {formatJakartaDateTime(source.activated_at)}
                                            </p>
                                            <div className="mt-4 flex items-center justify-between gap-3">
                                                <p className="text-[11px] font-medium text-[#395886]/70">
                                                    {source.import.student_count} mahasiswa
                                                </p>
                                                <Button asChild size="sm" variant="outline" className="h-8 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                    <a href={downloadUploadedWorkbook.url(source.import.id)}>
                                                        <Download className="mr-1.5 size-3.5" /> Unduh
                                                    </a>
                                                </Button>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </div>
                        ))}
                        <Pager data={sources} />
                    </div>
                ) : (
                    <p className="mt-4 rounded-2xl border border-dashed border-[#8AAEE0] bg-white/70 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                        Belum ada daftar mahasiswa aktif yang digunakan. Upload lalu pilih file roster untuk kelas terkait.
                    </p>
                )}
            </div>

            <div className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                <div className="border-b border-[#F0F3FA] px-5 py-4">
                    <h3 className="flex items-center gap-2 text-sm font-extrabold text-[#395886]">
                        <UsersRound className="size-4" /> Semua versi daftar mahasiswa aktif
                    </h3>
                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                        Pilih file untuk menjadikannya sumber roster kelas-kelas yang tercantum di dalam file. Pemilihan roster tidak bergantung pada workbook Kompen dan Respon.
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1220px] table-auto text-sm">
                        <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                            <tr>
                                <th className="w-[17%] px-4 py-3.5">Periode</th>
                                <th className="w-[27%] px-4 py-3.5">File roster</th>
                                <th className="w-[13%] px-4 py-3.5">Isi</th>
                                <th className="w-[17%] px-4 py-3.5">Diunggah</th>
                                <th className="w-[180px] px-4 py-3.5">Kelas digunakan</th>
                                <th className="min-w-[330px] px-4 py-3.5 text-right whitespace-nowrap">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F0F3FA]">
                            {versions.data.map((version) => {
                                const activeClasses = activeClassesSummary(version.active_classes);

                                return (
                                <tr key={version.id} className="align-middle hover:bg-[#B1C9EF]/10">
                                    <td className="break-words px-4 py-3.5 text-xs font-bold text-[#395886]">
                                        {version.periode_semester ?? "Belum ditetapkan"}
                                    </td>
                                    <td className="break-words px-4 py-3.5">
                                        <p className="font-mono text-xs font-bold text-[#395886]">
                                            {version.original_filename}
                                        </p>
                                        <p className="mt-1 break-words text-[11px] text-[#395886]/60">
                                            {version.uploader_name} · {version.uploader_email ?? "—"}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3.5 text-xs font-semibold text-[#395886]">
                                        {version.class_count} kelas<br />
                                        {version.student_count} mahasiswa
                                    </td>
                                    <td className="px-4 py-3.5 text-xs text-[#395886]">
                                        {formatJakartaDateTime(version.imported_at)}
                                    </td>
                                    <td className="w-[180px] px-4 py-3.5 text-xs text-[#395886]">
                                        {activeClasses === null ? (
                                            "Belum digunakan"
                                        ) : (
                                            <div className="max-w-[180px]">
                                                <p className="font-bold text-[#395886]">
                                                    {activeClasses.label}
                                                </p>
                                                <p
                                                    className="mt-1 truncate text-[11px] font-medium text-[#395886]/65"
                                                    title={version.active_classes.join(", ")}
                                                >
                                                    {activeClasses.preview}
                                                </p>
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                        <div className="flex min-w-max justify-end gap-2">
                                            <Button asChild size="sm" variant="outline" className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                <a href={downloadUploadedWorkbook.url(version.id)}>
                                                    <Download className="mr-1.5 size-3.5" /> Unduh
                                                </a>
                                            </Button>
                                            <Form
                                                {...activate.form(version.id)}
                                                onBefore={() => window.confirm(`Gunakan ${version.original_filename} sebagai roster aktif untuk kelas pada periode ${version.periode_semester ?? "ini"}?`)}
                                            >
                                                {({ processing }) => (
                                                    <Button type="submit" size="sm" disabled={processing} className="shrink-0 rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                        {processing ? "Memilih…" : "Jadikan digunakan"}
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    </td>
                                </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <Pager data={versions} />
            </div>
        </section>
    );
}
