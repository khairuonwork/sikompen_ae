import { useConfirmAction } from "../shared/components/confirm-action";
import { CorrectionField } from "../shared/components/correction-field";
import { Form, router } from "@inertiajs/react";
import { Save, Trash2, X } from "lucide-react";
import {
    destroyDetailOverride,
    storeDetailOverride,
} from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import type { Detail } from "../shared/types";

export function DetailCorrectionPanel({
    detail,
    onClose,
}: {
    detail: Detail;
    onClose: () => void;
}): React.JSX.Element {
    const { confirm, dialog, processing: deleting } = useConfirmAction();
    return (
        <Form
            {...storeDetailOverride.form(detail.id)}
            className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm md:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    {dialog}
                    <div className="flex items-start justify-between gap-4 md:col-span-2">
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                                Detail dipilih
                            </p>
                            <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                                {detail.nama_mahasiswa} · {detail.mata_kuliah}
                            </h3>
                        </div>
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            onClick={onClose}
                            className="shrink-0 rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]"
                            aria-label="Tutup panel perbaikan"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                    <CorrectionField
                        label="Tanggal"
                        error={errors.tanggal}
                        name="tanggal"
                        type="date"
                        required
                        defaultValue={detail.tanggal}
                    />
                    <CorrectionField
                        label="Mata kuliah"
                        error={errors.mata_kuliah}
                        name="mata_kuliah"
                        required
                        maxLength={100}
                        defaultValue={detail.mata_kuliah}
                    />
                    <CorrectionField
                        label="Nama dosen"
                        error={errors.nama_dosen}
                        name="nama_dosen"
                        required
                        maxLength={100}
                        defaultValue={detail.nama_dosen}
                    />
                    <CorrectionField
                        label="Jenis pertemuan"
                        error={errors.jenis_pertemuan}
                        name="jenis_pertemuan"
                        required
                        maxLength={20}
                        defaultValue={detail.jenis_pertemuan}
                    />
                    <CorrectionField
                        label="Presensi"
                        error={errors.presensi}
                        name="presensi"
                        required
                        maxLength={20}
                        defaultValue={detail.presensi}
                    />
                    <CorrectionField
                        label="Keterlambatan (menit)"
                        error={errors.menit_keterlambatan}
                        name="menit_keterlambatan"
                        type="number"
                        min="0"
                        defaultValue={detail.menit_keterlambatan}
                    />
                    <CorrectionField
                        label="Kompen (jam)"
                        error={errors.jam_kompensasi}
                        name="jam_kompensasi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_kompensasi}
                    />
                    <CorrectionField
                        label="Responsi (jam)"
                        error={errors.jam_responsi}
                        name="jam_responsi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_responsi}
                    />
                    <CorrectionField
                        label="Keterangan"
                        error={errors.keterangan}
                        name="keterangan"
                        wrapperClassName="md:col-span-2"
                        maxLength={2000}
                        defaultValue={detail.keterangan ?? ""}
                        placeholder="Keterangan"
                    />
                    <CorrectionField
                        label="Alasan koreksi"
                        error={errors.reason}
                        name="reason"
                        wrapperClassName="md:col-span-2"
                        required
                        minLength={5}
                        maxLength={1000}
                        placeholder="Alasan koreksi"
                    />
                    <Button
                        disabled={processing || deleting}
                        type="submit"
                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                    >
                        <Save className="mr-2 size-4" />
                        Simpan koreksi detail
                    </Button>
                    {detail.has_override ? (
                        <Button
                            disabled={processing || deleting}
                            type="button"
                            variant="outline"
                            className="w-fit rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white"
                            onClick={() => confirm({
                                title: "Hapus koreksi",
                                description: "Nilai detail akan kembali mengikuti workbook yang digunakan.",
                                submit: (onFinish) => router.delete(destroyDetailOverride.url(detail.id), { preserveScroll: true, onSuccess: onClose, onFinish }),
                            })}
                        >
                            <Trash2 className="mr-2 size-4" />
                            Hapus koreksi
                        </Button>
                    ) : null}
                </>
            )}
        </Form>
    );
}
