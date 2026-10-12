import { Form } from "@inertiajs/react";
import { UploadCloud, UsersRound } from "lucide-react";
import { type DragEvent, useState } from "react";
import { store } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { UploadProgressPanel } from "./upload-progress-panel";
import { preventDropIntoUploaderName } from "./upload-panel";

export function ActiveStudentUploadPanel({
    onUploadRequestActivityChange,
}: {
    onUploadRequestActivityChange: (isActive: boolean) => void;
}): React.JSX.Element {
    const [selectedFilename, setSelectedFilename] = useState<string | null>(
        null,
    );

    return (
        <Card className="mx-auto w-full max-w-3xl overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
            <CardHeader className="border-b border-[#F0F3FA] pb-6">
                <div className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-2xl bg-[#B1C9EF]/40 text-[#395886]">
                        <UsersRound className="size-5" />
                    </span>
                    <div>
                        <CardTitle className="text-xl font-black tracking-tight text-[#395886] md:text-2xl">
                            Upload Daftar Mahasiswa Aktif
                        </CardTitle>
                        <CardDescription className="mt-1 text-xs leading-relaxed font-normal text-[#395886]/70 md:text-sm">
                            Data ini menjadi master mahasiswa aktif untuk
                            persiapan akun mahasiswa. Unggahan baru menggantikan
                            daftar aktif sebelumnya setelah validasi berhasil.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <Form
                {...store.form()}
                resetOnSuccess
                onStart={() => onUploadRequestActivityChange(true)}
                onFinish={() => onUploadRequestActivityChange(false)}
                onSuccess={() => setSelectedFilename(null)}
            >
                {({ errors, processing, progress }) => (
                    <>
                        <CardContent className="grid gap-5 pt-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="active-student-uploader-name"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Nama Admin yang Mengunggah
                                </Label>
                                <Input
                                    id="active-student-uploader-name"
                                    name="uploader_name"
                                    autoComplete="name"
                                    maxLength={100}
                                    onDragOver={preventDropIntoUploaderName}
                                    onDrop={preventDropIntoUploaderName}
                                    required
                                    className="rounded-2xl border-[#8AAEE0] bg-white/90 py-2.5 text-sm text-[#395886] placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                    placeholder="Masukkan nama admin"
                                />
                                {errors.uploader_name ? (
                                    <p className="text-xs font-bold text-rose-600">
                                        {errors.uploader_name}
                                    </p>
                                ) : null}
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="active-student-workbook"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Daftar Mahasiswa Aktif (XLSX)
                                </Label>
                                <Input
                                    id="active-student-workbook"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required
                                    onChange={(event) =>
                                        setSelectedFilename(
                                            event.currentTarget.files?.[0]
                                                ?.name ?? null,
                                        )
                                    }
                                    className="h-12 cursor-pointer rounded-2xl border-[#8AAEE0] bg-white/90 p-1.5 text-sm text-[#395886] file:mr-3 file:h-9 file:rounded-xl file:border-0 file:bg-[#395886] file:px-3 file:text-xs file:font-bold file:text-white hover:file:bg-[#1E293B]"
                                />
                                <p className="rounded-xl bg-[#F0F3FA]/70 px-3 py-2 text-[11px] font-medium text-[#395886]/75">
                                    Nama file dicatat di Log Upload:{" "}
                                    <span className="font-mono font-bold text-[#395886]">
                                        {selectedFilename ??
                                            "Belum ada file dipilih"}
                                    </span>
                                </p>
                                <p className="text-[11px] leading-tight font-medium text-[#395886]/60">
                                    Kolom wajib: NIM, Nama Mahasiswa, dan Kelas.
                                    NIM harus unik dan kode kelas mengikuti
                                    format seperti 1AEA1.
                                </p>
                                {errors.file ? (
                                    <p className="text-xs font-bold text-rose-600">
                                        {errors.file}
                                    </p>
                                ) : null}
                            </div>

                            {processing ? (
                                <UploadProgressPanel
                                    progress={progress?.percentage ?? 0}
                                />
                            ) : null}
                        </CardContent>
                        <CardFooter className="justify-end border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 py-6">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-2xl bg-[#395886] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-[#1E293B] hover:text-white"
                            >
                                <UploadCloud className="mr-2 size-4" />
                                {processing
                                    ? "Mengirim daftar…"
                                    : "Upload daftar mahasiswa"}
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}
