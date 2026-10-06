import { Form } from "@inertiajs/react";
import { UploadCloud } from "lucide-react";
import { type DragEvent, useState } from "react";
import { store } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
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

export function UploadPanel({
    onUploadRequestActivityChange,
}: {
    onUploadRequestActivityChange: (isActive: boolean) => void;
}): React.JSX.Element {
    const [selectedFilename, setSelectedFilename] = useState<string | null>(
        null,
    );

    return (
        <Card className="group mx-auto w-full max-w-3xl overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-500 hover:shadow-[0_15px_35px_rgba(57,88,134,0.15)]">
            <CardHeader className="border-b border-[#F0F3FA] pb-6">
                <CardTitle className="text-xl font-black tracking-tight text-[#395886] md:text-2xl">
                    Upload Workbook
                </CardTitle>
                <CardDescription className="mt-1 text-xs leading-relaxed font-normal text-[#395886]/70 md:text-sm">
                    Gunakan template Sikompen. Periode wajib memakai tahun
                    ajaran di C4 dan pilihan Gasal atau Genap di B4.
                </CardDescription>
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
                            {/* Input Uploader Name */}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="uploader-name"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Nama Admin yang Mengunggah
                                </Label>
                                <Input
                                    id="uploader-name"
                                    name="uploader_name"
                                    autoComplete="name"
                                    maxLength={100}
                                    onDragOver={preventDropIntoUploaderName}
                                    onDrop={preventDropIntoUploaderName}
                                    required
                                    className="rounded-2xl border-[#8AAEE0] bg-white/90 py-2.5 text-sm text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                    placeholder="Masukkan nama admin"
                                />
                                {errors.uploader_name ? (
                                    <p className="mt-0.5 text-xs font-bold text-rose-600">
                                        {errors.uploader_name}
                                    </p>
                                ) : null}
                            </div>

                            {/* Input File */}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="kompen-respon-workbook"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Workbook XLSX
                                </Label>
                                <Input
                                    id="kompen-respon-workbook"
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
                                    className="h-12 cursor-pointer rounded-2xl border-[#8AAEE0] bg-white/90 p-1.5 text-sm text-[#395886] shadow-2xs transition-all duration-300 file:mr-3 file:h-9 file:rounded-xl file:border-0 file:bg-[#395886] file:px-3 file:text-xs file:font-bold file:text-white hover:file:bg-[#1E293B]"
                                />
                                <p className="rounded-xl bg-[#F0F3FA]/70 px-3 py-2 text-[11px] font-medium text-[#395886]/75">
                                    Nama file dicatat di Log Upload:{" "}
                                    <span className="font-mono font-bold text-[#395886]">
                                        {selectedFilename ??
                                            "Belum ada file dipilih"}
                                    </span>
                                </p>
                                <p className="text-[11px] leading-tight font-medium text-[#395886]/60">
                                    Maksimum 20 MB. B4 hanya menerima Gasal
                                    atau Genap; C4 wajib berformat YYYY/YYYY,
                                    misalnya 2026/2027.
                                </p>
                                {errors.file ? (
                                    <p className="mt-0.5 text-xs font-bold text-rose-600">
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
                        <CardFooter className="justify-end border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 pt-6 pb-6">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-2xl bg-[#395886] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:text-white hover:shadow-xl active:scale-95"
                            >
                                <UploadCloud className="mr-2 size-4" />
                                {processing
                                    ? "Mengirim workbook…"
                                    : "Upload & impor"}
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}

export function preventDropIntoUploaderName(
    event: DragEvent<HTMLInputElement>,
): void {
    event.preventDefault();
    event.dataTransfer.dropEffect = "none";
}
