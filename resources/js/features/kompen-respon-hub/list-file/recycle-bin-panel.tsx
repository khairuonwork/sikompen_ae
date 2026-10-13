import { Form } from "@inertiajs/react";
import { ArchiveRestore, Recycle } from "lucide-react";
import { restore as restoreActiveStudentImport } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { restore as restoreImport } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { updateWorkbookRecycleBin } from "@/actions/App/Http/Controllers/KompenResponHubActivityRetentionController";
import { Button } from "@/components/ui/button";
import { Pager } from "../shared/components/pagination";
import type {
    ActiveStudentImportVersion,
    ImportVersion,
    Pagination,
} from "../shared/types";

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

export function RecycleBinPanel({
    activeStudentVersions,
    kompenResponVersions,
    retentionDays,
}: {
    activeStudentVersions: Pagination<ActiveStudentImportVersion>;
    kompenResponVersions: Pagination<ImportVersion>;
    retentionDays: number;
}): React.JSX.Element {
    const hasDeletedVersions = kompenResponVersions.data.length > 0 || activeStudentVersions.data.length > 0;

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="flex flex-col gap-4 border-b border-[#F0F3FA] px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 className="flex items-center gap-2 text-sm font-extrabold text-[#395886]">
                        <Recycle className="size-4" /> Recycle bin workbook
                    </h3>
                    <p className="mt-0.5 max-w-3xl text-xs font-medium text-[#395886]/70">
                        File yang dilepas dari List File dapat dipulihkan selama masa retensi. Penghapusan permanen tidak menghapus data operasional yang masih bergantung pada versi tersebut.
                    </p>
                </div>
                <Form {...updateWorkbookRecycleBin.form()} className="flex flex-wrap items-center gap-2">
                    {({ processing }) => (
                        <>
                            <label className="text-xs font-bold text-[#395886]" htmlFor="workbook-recycle-bin-retention-days">
                                Simpan selama
                            </label>
                            <select
                                id="workbook-recycle-bin-retention-days"
                                name="workbook_recycle_bin_retention_days"
                                defaultValue={retentionDays}
                                className="h-9 rounded-xl border border-[#8AAEE0] bg-white px-3 text-xs font-bold text-[#395886] outline-none focus:border-[#395886]"
                            >
                                {[7, 14, 30, 60, 90, 180].map((days) => (
                                    <option key={days} value={days}>{days} hari</option>
                                ))}
                            </select>
                            <Button type="submit" size="sm" disabled={processing} className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                Simpan
                            </Button>
                        </>
                    )}
                </Form>
            </div>

            {!hasDeletedVersions ? (
                <p className="m-5 rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/65 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                    Recycle bin kosong.
                </p>
            ) : (
                <div className="grid gap-4 p-5 xl:grid-cols-2">
                    <RecycleBinList
                        title="Workbook Kompen dan Respon"
                        versions={kompenResponVersions}
                        kind="kompen_respon"
                    />
                    <RecycleBinList
                        title="Daftar mahasiswa aktif"
                        versions={activeStudentVersions}
                        kind="active_students"
                    />
                </div>
            )}
        </section>
    );
}

function RecycleBinList({
    kind,
    title,
    versions,
}: {
    kind: "kompen_respon" | "active_students";
    title: string;
    versions: Pagination<ImportVersion> | Pagination<ActiveStudentImportVersion>;
}): React.JSX.Element {
    return (
        <div className="rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/55 p-4">
            <h4 className="text-xs font-extrabold text-[#395886]">{title}</h4>
            {versions.data.length === 0 ? (
                <p className="mt-3 text-xs font-medium text-[#395886]/65">Tidak ada file di recycle bin.</p>
            ) : (
                <div className="mt-3 grid gap-2">
                    {versions.data.map((version) => (
                        <article key={version.id} className="flex flex-col gap-3 rounded-xl border border-white bg-white/90 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <p className="break-words font-mono text-xs font-bold text-[#395886]">
                                    {"display_filename" in version ? version.display_filename : version.original_filename}
                                </p>
                                <p className="mt-1 text-[11px] font-medium text-[#395886]/65">
                                    {version.periode_semester ?? "Periode belum ditetapkan"} · dihapus {formatJakartaDateTime(version.deleted_at)}
                                </p>
                            </div>
                            <Form
                                {...(kind === "kompen_respon" ? restoreImport.form(version.id) : restoreActiveStudentImport.form(version.id))}
                                onBefore={() => window.confirm(`Pulihkan ${"display_filename" in version ? version.display_filename : version.original_filename}?`)}
                            >
                                {({ processing }) => (
                                    <Button type="submit" size="sm" disabled={processing} variant="outline" className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                        <ArchiveRestore className="size-3.5" /> {processing ? "Memulihkan…" : "Pulihkan"}
                                    </Button>
                                )}
                            </Form>
                        </article>
                    ))}
                    <Pager<ImportVersion | ActiveStudentImportVersion> data={versions} />
                </div>
            )}
        </div>
    );
}
