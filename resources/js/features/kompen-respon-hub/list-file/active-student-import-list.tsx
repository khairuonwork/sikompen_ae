import { ConfirmForm } from "../shared/components/confirm-action";
import { Form } from "@inertiajs/react";
import { CheckCircle2, Download, FilePenLine, Power, Trash2, Unlink, UsersRound } from "lucide-react";
import { useState } from "react";
import {
    activate,
    destroy as destroyImport,
    downloadUploadedWorkbook,
    release,
    update,
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Pager } from "../shared/components/pagination";
import type {
    ActiveStudentImportReference,
    ActiveStudentImportVersion,
    Pagination,
} from "../shared/types";

type FileManagementAction = "rename" | "delete" | null;

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
    const [managementAction, setManagementAction] = useState<FileManagementAction>(null);

    function closeManagementDialog(): void {
        setSelectedVersion(null);
        setManagementAction(null);
    }

    return (
        <section className="grid gap-4">
            <div className="rounded-3xl border border-[#B1C9EF]/70 bg-[#B1C9EF]/15 p-5">
                <div className="flex flex-col gap-1">
                    <h3 className="text-sm font-extrabold text-[#395886]">
                        Daftar mahasiswa aktif yang digunakan
                    </h3>
                    <p className="text-xs font-medium text-[#395886]/70">
                        Satu file roster aktif menentukan seluruh akun mahasiswa yang dapat masuk. Memilih file baru langsung menggantikan roster sebelumnya, meski periodenya berbeda.
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
                                        Roster login mahasiswa
                                    </p>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                        <CheckCircle2 className="size-3" /> Digunakan
                                    </span>
                                </div>
                                <p className="mt-3 break-words font-mono text-xs font-bold text-[#395886]">
                                    {reference.import.original_filename}
                                </p>
                                <p className="mt-1 text-[11px] font-semibold text-[#395886]/75">
                                    Periode data: {reference.periode_semester ?? "Belum ditetapkan"}
                                </p>
                                <p className="mt-1 text-[11px] text-[#395886]/60">
                                    Dipilih {formatJakartaDateTime(reference.activated_at)}
                                </p>
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-[11px] font-medium text-[#395886]/70">
                                        {reference.import.class_count} kelas · {reference.import.student_count} mahasiswa
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild size="sm" variant="outline" className="h-8 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                            <a href={downloadUploadedWorkbook.url(reference.import.id)}>
                                                <Download className="mr-1.5 size-3.5" /> Unduh
                                            </a>
                                        </Button>
                                        <ConfirmForm
                                            {...release.form(reference.import.id)}
                                            title="Nonaktifkan roster" description={"Nonaktifkan roster ini? Seluruh mahasiswa akan langsung kehilangan akses masuk. Akun dan riwayatnya tetap tersimpan."}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                    className="h-8 rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                                >
                                                    <Power className="mr-1.5 size-3.5" />
                                                    {processing ? "Menonaktifkan…" : "Nonaktifkan akses"}
                                                </Button>
                                            )}
                                        </ConfirmForm>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <p className="mt-4 rounded-2xl border border-dashed border-[#8AAEE0] bg-white/70 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                        Belum ada daftar mahasiswa aktif yang digunakan. Upload lalu pilih satu file roster untuk menentukan siapa yang dapat masuk.
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
                            Pilih satu file untuk menjadi roster aktif seluruh kelas. Pemilihan roster tidak bergantung pada periode maupun workbook Kompen dan Respon.
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
                    <table className="data-table min-w-[1400px]">
                        <colgroup>
                            {["26rem", "12rem", "12rem", "14rem", "13rem", "24rem"].map((width, index) => (
                                <col key={`${width}-${index}`} style={{ width }} />
                            ))}
                        </colgroup>
                        <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                            <tr>
                                <th className="px-5 py-4">File roster</th>
                                <th className="px-5 py-4">Periode</th>
                                <th className="px-5 py-4">Isi</th>
                                <th className="px-5 py-4">Diunggah</th>
                                <th className="px-5 py-4">Status</th>
                                <th className="px-5 py-4 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F0F3FA]">
                            {versions.data.map((version) => (
                                <tr key={version.id} className="align-middle hover:bg-[#B1C9EF]/10">
                                    <td className="px-5 py-4">
                                        <p className="font-mono text-xs font-bold text-[#395886]">
                                            {version.original_filename}
                                        </p>
                                        <p className="mt-1 text-[11px] text-[#395886]/60">
                                            {version.uploader_name} · {version.uploader_username ?? "—"}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold text-[#395886]">
                                        {version.periode_semester ?? "Belum ditetapkan"}
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
                                            {version.is_active ? (
                                                <ConfirmForm
                                                    {...release.form(version.id)}
                                                    title="Nonaktifkan roster"
                                                    description={`Nonaktifkan ${version.original_filename}? Seluruh mahasiswa akan langsung kehilangan akses masuk. Akun dan riwayatnya tetap tersimpan.`}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={processing}
                                                            className="shrink-0 rounded-xl border-amber-300 bg-white text-xs font-bold text-amber-800 hover:bg-amber-100 hover:text-amber-900"
                                                        >
                                                            <Unlink className="mr-1.5 size-3.5" />
                                                            {processing ? "Menonaktifkan…" : "Lepaskan roster"}
                                                        </Button>
                                                    )}
                                                </ConfirmForm>
                                            ) : isEditMode ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setSelectedVersion(version);
                                                            setManagementAction("rename");
                                                        }}
                                                        className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                                    >
                                                        <FilePenLine className="mr-1.5 size-3.5" /> Ubah nama
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setSelectedVersion(version);
                                                            setManagementAction("delete");
                                                        }}
                                                        className="shrink-0 rounded-xl border-rose-200 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                                                    >
                                                        <Trash2 className="mr-1.5 size-3.5" /> Hapus
                                                    </Button>
                                                </>
                                            ) : (
                                                <ConfirmForm
                                                    {...activate.form(version.id)}
                                                    title="Gunakan roster" description={`Gunakan ${version.original_filename} sebagai satu-satunya roster aktif? Mahasiswa yang tidak tercantum pada file baru akan langsung kehilangan akses masuk.`}
                                                >
                                                    {({ processing }) => (
                                                        <Button type="submit" size="sm" disabled={processing} className="shrink-0 rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                            {processing ? "Memilih…" : "Jadikan digunakan"}
                                                        </Button>
                                                    )}
                                                </ConfirmForm>
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
                open={selectedVersion !== null && managementAction !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        closeManagementDialog();
                    }
                }}
            >
                <DialogContent className="rounded-3xl border-[#B1C9EF] bg-white p-6 text-[#395886]">
                    {selectedVersion !== null && managementAction === "rename" ? (
                        <Form
                            key={`rename-${selectedVersion.id}`}
                            {...update.form(selectedVersion.id)}
                            onSuccess={closeManagementDialog}
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-5">
                                    <DialogHeader>
                                        <DialogTitle className="text-[#395886]">Ubah nama file</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">Ekstensi .xlsx selalu dipertahankan; isi roster tidak berubah.</DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-2 py-2">
                                        <Label htmlFor="display_filename" className="text-xs font-bold text-[#395886]">Nama file</Label>
                                        <Input id="display_filename" name="display_filename" defaultValue={selectedVersion.original_filename} className="border-[#8AAEE0] bg-white text-[#395886]" />
                                        {errors.display_filename ? <p className="text-xs font-semibold text-rose-700">{errors.display_filename}</p> : null}
                                    </div>
                                    <DialogFooter className="gap-3 sm:gap-3">
                                        <Button type="button" variant="outline" onClick={closeManagementDialog} className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#F0F3FA] hover:text-[#395886]">Batal</Button>
                                        <Button type="submit" disabled={processing} className="bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white">{processing ? "Menyimpan…" : "Simpan nama"}</Button>
                                    </DialogFooter>
                                </div>
                            )}
                        </Form>
                    ) : null}
                    {selectedVersion !== null && managementAction === "delete" ? (
                        <Form
                            key={`delete-${selectedVersion.id}`}
                            {...destroyImport.form(selectedVersion.id)}
                            onSuccess={closeManagementDialog}
                        >
                            {({ processing }) => (
                                <div className="grid gap-5">
                                    <DialogHeader>
                                        <DialogTitle className="text-[#395886]">Hapus versi file?</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">
                                            {selectedVersion.original_filename} akan dipindahkan ke Recycle Bin. File dapat dipulihkan selama masa retensi.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter className="gap-3 sm:gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={closeManagementDialog}
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
