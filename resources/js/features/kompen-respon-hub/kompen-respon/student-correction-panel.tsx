import { Form } from "@inertiajs/react";
import { Save, X } from "lucide-react";
import { storeProgress, storeSummaryOverride } from "@/actions/App/Http/Controllers/KompenResponHubLifecycleController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { Student } from "../shared/types";

export function StudentCorrectionPanel({
    student,
    onClose,
}: {
    student: Student;
    onClose: () => void;
}): React.JSX.Element {
    return (
        <section className="grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                        Mahasiswa dipilih
                    </p>
                    <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                        {student.nama_mahasiswa} · {student.nim}
                    </h3>
                    <p className="mt-1 text-xs text-[#395886]/70">
                        Simpan setiap koreksi dengan alasan agar jejak audit
                        tetap lengkap.
                    </p>
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
            <div className="grid gap-4 xl:grid-cols-2">
                <Form
                    {...storeProgress.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Progres pengerjaan
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="kompensasi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_kompensasi_dikerjakan_jam
                                    }
                                    placeholder="Jam Kompen"
                                />
                                <Input
                                    name="responsi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_responsi_dikerjakan_jam
                                    }
                                    placeholder="Jam Responsi"
                                />
                            </div>
                            <Input
                                name="last_worked_at"
                                type="datetime-local"
                                required
                                defaultValue={
                                    student.last_worked_at?.slice(0, 16) ?? ""
                                }
                            />
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan perubahan"
                            />
                            {errors.kompensasi_dikerjakan_jam ||
                            errors.last_worked_at ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.kompensasi_dikerjakan_jam ??
                                        errors.last_worked_at}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan progres
                            </Button>
                        </>
                    )}
                </Form>
                <Form
                    {...storeSummaryOverride.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Koreksi total Kompen / Responsi
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="total_kompensasi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_kompensasi_jam
                                    }
                                    placeholder="Total Kompen"
                                />
                                <Input
                                    name="total_responsi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_responsi_jam
                                    }
                                    placeholder="Total Responsi"
                                />
                            </div>
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan koreksi"
                            />
                            {errors.total_kompensasi_jam ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.total_kompensasi_jam}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan koreksi
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}
