import { Form } from "@inertiajs/react";
import { CheckCircle2, Download, FilePenLine, Trash2, UsersRound } from "lucide-react";
import { useState } from "react";
import {
    activate,
    destroy as destroyImport,
    downloadUploadedWorkbook,
} from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Pager } from "../shared/components/pagination";
import type {
    ActiveStudentImportReference,
    ActiveStudentImportVersion,
    Pagination,
} from "../shared/types";

function formatJakartaDateTime(value: string): string {
    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Jakarta",
    }).format(new Date(value));
}

export function ActiveStudentImportList({
    references,
    versions,
}: {
    references: Pagination<ActiveStudentImportReference>;
    versions: Pagination<ActiveStudentImportVersion>;
}): React.JSX.Element {
    const [isEditMode, setIsEditMode] = useState(false);
    const [selectedVersion, setSelectedVersion] = useState<ActiveStudentImportVersion | null>(null);

    return (
        <section className="grid gap-4">
            <div className="rounded-3xl border border-[#B1C9EF]/70 bg-[#B1C9EF]/15 p-5">
                <div className="flex flex-col gap-1">
                    <h3 className="text-sm font-extrabold text-[#395886]">
                        Daftar mahasiswa aktif yang digunakan
                    </h3>
                    <p className="text-xs font-medium text-[#395886]/70">
                        Satu file roster aktif berlaku untuk seluruh kelas pada satu periode. Memilih file baru akan menggantikan file aktif sebelumnya untuk periode tersebut.
                    </p>
                </div>
                {references.data.length > 0 ? (
                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {references.data.map((reference) => (
                            <article
                                key={reference.id}
                                className="rounded-2xl border border-white bg-white/90 p-4 shadow-2xs"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <p className="text-xs font-extrabold text-[#395886]">
                                        {reference.periode_semester}
                                    </p>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                        <CheckCircle2 className="size-3" /> Digunakan
                                    </span>
                                </div>
                                <p className="mt-3 break-words font-mono text-xs font-bold text-[#395886]">
                                    {reference.import.original_filename}
                                </p>
                                <p className="mt-1 text-[11px] text-[#395886]/60">
                                    Dipilih {formatJakartaDateTime(reference.activated_at)}
                                </p>
                                <div className="mt-4 flex items-center justify-between gap-3">
                                    <p className="text-[11px] font-medium text-[#395886]/70">
                                        {reference.import.class_count} kelas · {reference.import.student_count} mahasiswa
                                    </p>
                                    <Button asChild size="sm" variant="outline" className="h-8 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                        <a href={downloadUploadedWorkbook.url(reference.import.id)}>
                                            <Download className="mr-1.5 size-3.5" /> Unduh
                                        </a>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <p className="mt-4 rounded-2xl border border-dashed border-[#8AAEE0] bg-white/70 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                        Belum ada daftar mahasiswa aktif yang digunakan. Upload lalu pilih satu file roster untuk periode terkait.
                    </p>
                )}
                {references.data.length > 0 ? <Pager data={references} /> : null}
            </div>

            <div className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                <div className="flex flex-col justify-between gap-3 border-b border-[#F0F3FA] px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 className="flex items-center gap-2 text-sm font-extrabold text-[#395886]">
                            <UsersRound className="size-4" /> Semua versi daftar mahasiswa aktif
                        </h3>
                        <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                            Pilih satu file untuk menjadi roster aktif seluruh kelas pada periodenya. Pemilihan roster tidak bergantung pada workbook Kompen dan Respon.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsEditMode((enabled) => !enabled)}
                        className={
                            isEditMode
                                ? "rounded-xl border-[#395886] bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                : "rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        }
                    >
                        <FilePenLine className="size-3.5" />
                        {isEditMode ? "Selesai mengelola" : "Kelola file"}
                    </Button>
                </div>
                <div className="data-table-scroll">
                    <table className="data-table min-w-[1300px]">
                        <colgroup>
                            {["11rem", "27rem", "12rem", "14rem", "13rem", "24rem"].map((width, index) => (
                                <col key={`${width}-${index}`} style={{ width }} />
                            ))}
                        </colgroup>
                        <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                            <tr>
                                <th className="px-5 py-4">Periode</th>
                                <th className="px-5 py-4">File roster</th>
                                <th className="px-5 py-4">Isi</th>
                                <th className="px-5 py-4">Diunggah</th>
                                <th className="px-5 py-4">Status</th>
                                <th className="px-5 py-4 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F0F3FA]">
                            {versions.data.map((version) => (
                                <tr key={version.id} className="align-middle hover:bg-[#B1C9EF]/10">
                                    <td className="px-5 py-4 text-xs font-bold text-[#395886]">
                                        {version.periode_semester ?? "Belum ditetapkan"}
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="font-mono text-xs font-bold text-[#395886]">
                                            {version.original_filename}
                                        </p>
                                        <p className="mt-1 text-[11px] text-[#395886]/60">
                                            {version.uploader_name} · {version.uploader_username ?? "—"}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-xs font-semibold text-[#395886]">
                                        {version.class_count} kelas<br />
                                        {version.student_count} mahasiswa
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#395886]">
                                        {formatJakartaDateTime(version.imported_at)}
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#395886]">
                                        {version.is_active ? (
                                            <div>
                                                <span className="inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                                    Digunakan
                                                </span>
                                                {version.activated_at !== null ? (
                                                    <p className="mt-1 text-[11px] font-medium text-[#395886]/65">
                                                        {formatJakartaDateTime(version.activated_at)}
                                                    </p>
                                                ) : null}
                                            </div>
                                        ) : "Belum digunakan"}
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <div className="flex min-w-max justify-end gap-2">
                                            <Button asChild size="sm" variant="outline" className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                <a href={downloadUploadedWorkbook.url(version.id)}>
                                                    <Download className="mr-1.5 size-3.5" /> Unduh
                                                </a>
                                            </Button>
                                            {isEditMode ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={version.is_active}
                                                    onClick={() => setSelectedVersion(version)}
                                                    className="shrink-0 rounded-xl border-rose-200 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                                >
                                                    <Trash2 className="mr-1.5 size-3.5" /> Hapus
                                                </Button>
                                            ) : (
                                                <Form
                                                    {...activate.form(version.id)}
                                                    onBefore={() => window.confirm(`Gunakan ${version.original_filename} sebagai satu-satunya roster aktif untuk periode ${version.periode_semester ?? "ini"}? File roster aktif sebelumnya untuk periode tersebut akan digantikan.`)}
                                                >
                                                    {({ processing }) => (
                                                        <Button type="submit" size="sm" disabled={processing || version.is_active} className="shrink-0 rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                            {version.is_active ? "Sedang digunakan" : processing ? "Memilih…" : "Jadikan digunakan"}
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
                <Pager data={versions} />
            </div>

            <Dialog
                open={selectedVersion !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setSelectedVersion(null);
                    }
                }}
            >
                <DialogContent className="rounded-3xl border-[#B1C9EF] bg-white p-6 text-[#395886]">
                    {selectedVersion !== null ? (
                        <Form
                            key={`delete-${selectedVersion.id}`}
                            {...destroyImport.form(selectedVersion.id)}
                            onSuccess={() => setSelectedVersion(null)}
                        >
                            {({ processing }) => (
                                <div className="grid gap-5">
                                    <DialogHeader>
                                        <DialogTitle className="text-[#395886]">Hapus versi file?</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">
                                            {selectedVersion.original_filename} akan dihapus dari penyimpanan beserta data roster versi ini. Riwayat audit tetap tersimpan, tetapi file tidak dapat dipulihkan dari aplikasi.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter className="gap-3 sm:gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setSelectedVersion(null)}
                                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#F0F3FA] hover:text-[#395886]"
                                        >
                                            Batal
                                        </Button>
                                        <Button type="submit" disabled={processing} className="bg-rose-600 text-white hover:bg-rose-700 hover:text-white">
                                            <Trash2 className="size-4" />
                                            {processing ? "Menghapus…" : "Hapus file"}
                                        </Button>
                                    </DialogFooter>
                                </div>
                            )}
                        </Form>
                    ) : null}
                </DialogContent>
            </Dialog>
        </section>
    );
}
