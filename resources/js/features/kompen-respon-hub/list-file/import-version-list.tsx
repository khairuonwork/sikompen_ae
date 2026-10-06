import { Form } from "@inertiajs/react";
import { FilePenLine, CheckCircle2, Download, FileSpreadsheet, Pencil, Trash2, UploadCloud } from "lucide-react";
import { useState } from "react";
import { store as activate } from "@/actions/App/Http/Controllers/KompenResponHubImportActivationController";
import { destroy as destroyImport, downloadUploadedWorkbook, update as updateImport } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
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
import type { ImportVersion, Pagination } from "../shared/types";

type FileManagementAction = "rename" | "delete" | null;

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
    activeVersions,
    data,
}: {
    activeVersions: ImportVersion[];
    data: Pagination<ImportVersion>;
}): React.JSX.Element {
    const [isEditMode, setIsEditMode] = useState(false);
    const [selectedVersion, setSelectedVersion] = useState<ImportVersion | null>(null);
    const [managementAction, setManagementAction] = useState<FileManagementAction>(null);

    function openManagementDialog(
        version: ImportVersion,
        action: Exclude<FileManagementAction, null>,
    ): void {
        setSelectedVersion(version);
        setManagementAction(action);
    }

    function closeManagementDialog(): void {
        setSelectedVersion(null);
        setManagementAction(null);
    }

    return (
        <section className="grid gap-4">
            <div className="rounded-3xl border border-[#B1C9EF]/70 bg-[#B1C9EF]/15 p-5">
                <div className="flex flex-col gap-1">
                    <h3 className="text-sm font-extrabold text-[#395886]">
                        Sumber data yang digunakan
                    </h3>
                    <p className="text-xs font-medium text-[#395886]/70">
                        Satu workbook aktif untuk setiap periode.
                    </p>
                </div>
                {activeVersions.length > 0 ? (
                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {activeVersions.map((version) => (
                            <article
                                key={version.id}
                                className="rounded-2xl border border-white bg-white/90 p-4 shadow-2xs"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <p className="text-xs font-extrabold text-[#395886]">
                                        {version.periode_semester}
                                    </p>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase">
                                        <CheckCircle2 className="size-3" /> Digunakan
                                    </span>
                                </div>
                                <p className="mt-3 truncate font-mono text-xs font-bold text-[#395886]" title={version.display_filename}>
                                    {version.display_filename}
                                </p>
                                <p className="mt-1 text-[11px] text-[#395886]/60">
                                    Dipilih {formatJakartaDateTime(version.activated_at)}
                                </p>
                                <div className="mt-4 flex items-center justify-between gap-3">
                                    <p className="text-[11px] font-medium text-[#395886]/70">
                                        {version.student_count} mahasiswa
                                    </p>
                                    <Button asChild size="sm" variant="outline" className="h-8 rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                        <a href={downloadUploadedWorkbook.url(version.id)}>
                                            <Download className="mr-1.5 size-3.5" /> Unduh
                                        </a>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <p className="mt-4 rounded-2xl border border-dashed border-[#8AAEE0] bg-white/70 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                        Belum ada workbook yang digunakan. Pilih salah satu versi pada daftar di bawah.
                    </p>
                )}
            </div>

            <div className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                <div className="flex flex-col justify-between gap-3 border-b border-[#F0F3FA] px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 className="text-sm font-extrabold text-[#395886]">
                            Semua versi workbook
                        </h3>
                        <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                            Pilih versi lain bila data sumber suatu periode perlu diganti.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsEditMode((enabled) => !enabled)}
                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <FilePenLine className="size-3.5" />
                        {isEditMode ? "Selesai mengelola" : "Kelola file"}
                    </Button>
                </div>
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
                                        <p className="truncate font-mono text-xs font-bold text-[#395886]" title={version.display_filename}>
                                            {version.display_filename}
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
                                            {isEditMode ? (
                                                <>
                                                    <Button type="button" size="sm" variant="outline" onClick={() => openManagementDialog(version, "rename")} className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                        <Pencil className="mr-1.5 size-3.5" /> Ubah nama
                                                    </Button>
                                                    <Button type="button" size="sm" variant="outline" disabled={version.is_active} onClick={() => openManagementDialog(version, "delete")} className="rounded-xl border-rose-200 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white">
                                                        <Trash2 className="mr-1.5 size-3.5" /> Hapus
                                                    </Button>
                                                </>
                                            ) : version.is_active ? (
                                                <Button size="sm" disabled className="rounded-xl bg-emerald-600 text-xs font-bold text-white disabled:opacity-100">
                                                    Digunakan
                                                </Button>
                                            ) : (
                                                <Form
                                                    {...activate.form(version.id)}
                                                    onBefore={() => window.confirm(`Gunakan ${version.display_filename} sebagai sumber data aktif untuk periode ${version.periode_semester}? Data sumber periode tersebut akan diganti, sedangkan koreksi manual dan riwayat tetap dipertahankan.`)}
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
                            {...updateImport.form(selectedVersion.id)}
                            onSuccess={closeManagementDialog}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle className="text-[#395886]">Ubah nama file</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">
                                            Nama ini digunakan pada List File dan saat workbook diunduh. Isi workbook tidak berubah.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-2 py-2">
                                        <Label htmlFor="display_filename" className="text-xs font-bold text-[#395886]">
                                            Nama file
                                        </Label>
                                        <Input
                                            id="display_filename"
                                            name="display_filename"
                                            defaultValue={selectedVersion.display_filename}
                                            aria-invalid={Boolean(errors.display_filename)}
                                            className="border-[#8AAEE0] bg-white text-[#395886]"
                                        />
                                        {errors.display_filename ? (
                                            <p className="text-xs font-semibold text-rose-700">{errors.display_filename}</p>
                                        ) : (
                                            <p className="text-[11px] text-[#395886]/60">Gunakan huruf, angka, spasi, titik, garis bawah, tanda kurung, atau tanda hubung. Nama harus berakhiran .xlsx.</p>
                                        )}
                                    </div>
                                    <DialogFooter>
                                        <Button type="button" variant="outline" onClick={closeManagementDialog} className="border-[#8AAEE0] text-[#395886]">
                                            Batal
                                        </Button>
                                        <Button type="submit" disabled={processing} className="bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white">
                                            {processing ? "Menyimpan…" : "Simpan nama"}
                                        </Button>
                                    </DialogFooter>
                                </>
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
                                <>
                                    <DialogHeader>
                                        <DialogTitle className="text-[#395886]">Hapus versi file?</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">
                                            {selectedVersion.display_filename} akan dihapus dari penyimpanan. Riwayat audit tetap ada, tetapi workbook tidak dapat dipulihkan dari aplikasi.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button type="button" variant="outline" onClick={closeManagementDialog} className="border-[#8AAEE0] text-[#395886]">
                                            Batal
                                        </Button>
                                        <Button type="submit" disabled={processing} className="bg-rose-600 text-white hover:bg-rose-700 hover:text-white">
                                            <Trash2 className="size-4" />
                                            {processing ? "Menghapus…" : "Hapus file"}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    ) : null}
                </DialogContent>
            </Dialog>
        </section>
    );
}
