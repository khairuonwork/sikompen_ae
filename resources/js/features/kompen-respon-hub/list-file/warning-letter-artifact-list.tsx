import { Form } from "@inertiajs/react";
import { Download, FilePenLine, FileSpreadsheet, History, PencilLine, Trash2 } from "lucide-react";
import { useState } from "react";
import { destroy, download, update } from "@/actions/App/Http/Controllers/KompenResponHubWarningLetterArtifactController";
import { ConfirmForm } from "../shared/components/confirm-action";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Pager } from "../shared/components/pagination";
import type { Pagination, WarningLetterArtifact } from "../shared/types";

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

export function WarningLetterArtifactList({
    data,
}: {
    data: Pagination<WarningLetterArtifact>;
}): React.JSX.Element {
    const [isEditMode, setIsEditMode] = useState(false);
    const [selectedArtifact, setSelectedArtifact] = useState<WarningLetterArtifact | null>(null);

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <div className="flex flex-col justify-between gap-3 border-b border-[#F0F3FA] px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h3 className="text-sm font-extrabold text-[#395886]">
                        Artifact Surat Peringatan
                    </h3>
                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                        Hasil XLSX resmi setiap periode. Saat koreksi dibuka, file sebelumnya tetap tersimpan tetapi belum menjadi hasil final.
                    </p>
                </div>
                <Button type="button" variant="outline" onClick={() => setIsEditMode((enabled) => !enabled)} className={isEditMode ? "rounded-xl border-[#395886] bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white" : "rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"}>
                    <FilePenLine className="size-3.5" /> {isEditMode ? "Selesai mengelola" : "Kelola file"}
                </Button>
            </div>
            {data.data.length > 0 ? (
                <>
                    <div className="data-table-scroll">
                        <table className="data-table min-w-[1180px]">
                            <colgroup>
                                {["16rem", "24rem", "11rem", "18rem", "12rem", "16rem", "14rem"].map((width, index) => (
                                    <col key={`${width}-${index}`} style={{ width }} />
                                ))}
                            </colgroup>
                            <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                                <tr>
                                    <th className="px-5 py-4">Periode</th>
                                    <th className="px-5 py-4">Artifact XLSX</th>
                                    <th className="px-5 py-4">Isi</th>
                                    <th className="px-5 py-4">Sumber saat final</th>
                                    <th className="px-5 py-4">Status</th>
                                    <th className="px-5 py-4">Finalisasi</th>
                                    <th className="px-5 py-4 text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F0F3FA]">
                                {data.data.map((artifact) => {
                                    const isRevising = artifact.status === "revising";
                                    const isSuperseded = artifact.status === "superseded";

                                    return (
                                        <tr key={artifact.id} className="hover:bg-[#B1C9EF]/10">
                                            <td className="px-5 py-4 text-xs font-bold text-[#395886]">
                                                {artifact.periode_semester ?? "Periode tidak tersedia"}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex items-start gap-2">
                                                    <FileSpreadsheet className="mt-0.5 size-4 shrink-0 text-emerald-700" />
                                                    <div>
                                                        <p className="break-words font-mono text-xs font-bold text-[#395886]">
                                                            {artifact.display_filename}
                                                        </p>
                                                        <p className="mt-1 text-[11px] text-[#395886]/60">
                                                            Revisi {artifact.revision}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-xs font-semibold text-[#395886]">
                                                {artifact.row_count} mahasiswa
                                            </td>
                                            <td className="px-5 py-4 text-[11px] leading-relaxed text-[#395886]/75">
                                                {artifact.source_provenance ? (
                                                    <>
                                                        <p className="break-words font-semibold text-[#395886]">
                                                            Workbook: {artifact.source_provenance.kompen_respon_workbook.display_filename}
                                                        </p>
                                                        <p className="mt-1 break-words">
                                                            Roster: {artifact.source_provenance.active_student_roster?.original_filename ?? "Tidak ada roster aktif"}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <p>Jejak sumber belum tersedia untuk artifact lama.</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={isRevising
                                                    ? "inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-amber-800 uppercase"
                                                    : isSuperseded
                                                        ? "inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-slate-700 uppercase"
                                                    : "inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase"}>
                                                    {isRevising ? <PencilLine className="size-3" /> : isSuperseded ? <History className="size-3" /> : <FileSpreadsheet className="size-3" />}
                                                    {isRevising ? "Sedang dibenarkan" : isSuperseded ? "Digantikan revisi baru" : "Final"}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-xs text-[#395886]">
                                                <p>{formatJakartaDateTime(artifact.finalized_at)}</p>
                                                {artifact.correction_started_at ? (
                                                    <p className="mt-1 text-[11px] text-amber-800">
                                                        Koreksi sejak {formatJakartaDateTime(artifact.correction_started_at)}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex min-w-max justify-end gap-2">
                                                    <Button asChild size="sm" variant="outline" className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                        <a href={download.url(artifact.id)}>
                                                            <Download className="mr-1.5 size-3.5" /> Unduh
                                                        </a>
                                                    </Button>
                                                    {isEditMode ? (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => setSelectedArtifact(artifact)} className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                            <FilePenLine className="mr-1.5 size-3.5" /> Ubah nama
                                                        </Button>
                                                    ) : null}
                                                    <ConfirmForm
                                                        {...destroy.form(artifact.id)}
                                                        title="Hapus artifact permanen"
                                                        description={artifact.status === "finalized"
                                                            ? `Hapus ${artifact.display_filename}? Artifact XLSX tidak dapat dipulihkan. Karena ini artifact final terbaru, finalisasi periode akan dibuka kembali untuk koreksi dan SP yang diterbitkan akan ditarik.`
                                                            : `Hapus ${artifact.display_filename}? Artifact XLSX tidak dapat dipulihkan. Riwayat aktivitas tetap tersimpan.`}
                                                        confirmButtonClassName="rounded-xl bg-rose-700 text-white hover:bg-rose-800 hover:text-white"
                                                    >
                                                        {({ processing }) => (
                                                            <Button type="submit" size="sm" disabled={processing} variant="outline" className="rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:border-rose-700 hover:bg-rose-700 hover:text-white">
                                                                <Trash2 className="mr-1.5 size-3.5" /> {processing ? "Menghapus…" : "Hapus"}
                                                            </Button>
                                                        )}
                                                    </ConfirmForm>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <Pager data={data} />
                </>
            ) : (
                <p className="m-5 rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/45 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                    Belum ada artifact Surat Peringatan. Finalisasi periode setelah cutoff untuk membuat XLSX resmi.
                </p>
            )}
            <Dialog open={selectedArtifact !== null} onOpenChange={(isOpen) => { if (!isOpen) setSelectedArtifact(null); }}>
                <DialogContent className="rounded-3xl border-[#B1C9EF] bg-white p-6 text-[#395886]">
                    {selectedArtifact ? (
                        <Form {...update.form(selectedArtifact.id)} onSuccess={() => setSelectedArtifact(null)}>
                            {({ errors, processing }) => (
                                <div className="grid gap-5">
                                    <DialogHeader><DialogTitle className="text-[#395886]">Ubah nama artifact</DialogTitle><DialogDescription className="text-[#395886]/70">Ekstensi .xlsx selalu dipertahankan; isi artifact tidak berubah.</DialogDescription></DialogHeader>
                                    <div className="grid gap-2"><Label htmlFor="display_filename" className="text-xs font-bold text-[#395886]">Nama file</Label><Input id="display_filename" name="display_filename" defaultValue={selectedArtifact.display_filename} className="border-[#8AAEE0] bg-white text-[#395886]" />{errors.display_filename ? <p className="text-xs font-semibold text-rose-700">{errors.display_filename}</p> : null}</div>
                                    <DialogFooter className="gap-3 sm:gap-3"><Button type="button" variant="outline" onClick={() => setSelectedArtifact(null)} className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#F0F3FA] hover:text-[#395886]">Batal</Button><Button type="submit" disabled={processing} className="bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white">{processing ? "Menyimpan…" : "Simpan nama"}</Button></DialogFooter>
                                </div>
                            )}
                        </Form>
                    ) : null}
                </DialogContent>
            </Dialog>
        </section>
    );
}
