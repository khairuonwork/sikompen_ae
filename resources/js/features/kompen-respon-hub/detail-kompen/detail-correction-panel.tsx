import { Form } from "@inertiajs/react";
import { Save, X } from "lucide-react";
import { storeDetailOverride } from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { Detail } from "../shared/types";

export function DetailCorrectionPanel({
    detail,
    onClose,
}: {
    detail: Detail;
    onClose: () => void;
}): React.JSX.Element {
    return (
        <Form
            {...storeDetailOverride.form(detail.id)}
            className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm md:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
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
                    <Input
                        name="tanggal"
                        type="date"
                        required
                        defaultValue={detail.tanggal}
                    />
                    <Input
                        name="mata_kuliah"
                        required
                        maxLength={100}
                        defaultValue={detail.mata_kuliah}
                    />
                    <Input
                        name="nama_dosen"
                        required
                        maxLength={100}
                        defaultValue={detail.nama_dosen}
                    />
                    <Input
                        name="jenis_pertemuan"
                        required
                        maxLength={20}
                        defaultValue={detail.jenis_pertemuan}
                    />
                    <Input
                        name="presensi"
                        required
                        maxLength={20}
                        defaultValue={detail.presensi}
                    />
                    <Input
                        name="menit_keterlambatan"
                        type="number"
                        min="0"
                        defaultValue={detail.menit_keterlambatan}
                    />
                    <Input
                        name="jam_kompensasi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_kompensasi}
                    />
                    <Input
                        name="jam_responsi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_responsi}
                    />
                    <Input
                        name="keterangan"
                        className="md:col-span-2"
                        maxLength={2000}
                        defaultValue={detail.keterangan ?? ""}
                        placeholder="Keterangan"
                    />
                    <Input
                        name="reason"
                        className="md:col-span-2"
                        required
                        minLength={5}
                        maxLength={1000}
                        placeholder="Alasan koreksi"
                    />
                    {errors.tanggal ? (
                        <p className="text-xs font-bold text-rose-600">
                            {errors.tanggal}
                        </p>
                    ) : null}
                    <Button
                        disabled={processing}
                        type="submit"
                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                    >
                        <Save className="mr-2 size-4" />
                        Simpan koreksi detail
                    </Button>
                </>
            )}
        </Form>
    );
}
