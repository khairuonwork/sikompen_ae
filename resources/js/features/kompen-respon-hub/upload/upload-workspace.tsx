import { FileSpreadsheet, UsersRound } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { ActiveStudentUploadPanel } from "./active-student-upload-panel";
import { UploadPanel } from "./upload-panel";

type UploadKind = "kompen_respon" | "active_students";

const uploadOptions: {
    kind: UploadKind;
    title: string;
    description: string;
    icon: typeof FileSpreadsheet;
}[] = [
    {
        kind: "kompen_respon",
        title: "Kompen dan Respon",
        description: "Unggah rekap jam Kompen/Responsi dan Detail Kompen.",
        icon: FileSpreadsheet,
    },
    {
        kind: "active_students",
        title: "Mahasiswa Aktif",
        description: "Unggah master NIM, nama, dan kelas mahasiswa aktif.",
        icon: UsersRound,
    },
];

export function UploadWorkspace({
    onUploadRequestActivityChange,
}: {
    onUploadRequestActivityChange: (isActive: boolean) => void;
}): React.JSX.Element {
    const [selectedKind, setSelectedKind] =
        useState<UploadKind>("kompen_respon");

    return (
        <section className="mx-auto grid w-full max-w-4xl gap-4">
            <div className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl sm:grid-cols-2">
                {uploadOptions.map((option) => {
                    const Icon = option.icon;
                    const isSelected = selectedKind === option.kind;

                    return (
                        <button
                            key={option.kind}
                            type="button"
                            onClick={() => setSelectedKind(option.kind)}
                            className={cn(
                                "flex items-start gap-3 rounded-2xl border p-4 text-left transition-colors",
                                isSelected
                                    ? "border-[#628ECB] bg-[#B1C9EF]/35 shadow-sm"
                                    : "border-[#D5DEEF] bg-white hover:border-[#8AAEE0] hover:bg-[#F0F3FA]",
                            )}
                        >
                            <span
                                className={cn(
                                    "flex size-10 shrink-0 items-center justify-center rounded-2xl",
                                    isSelected
                                        ? "bg-[#395886] text-white"
                                        : "bg-[#F0F3FA] text-[#628ECB]",
                                )}
                            >
                                <Icon className="size-5" />
                            </span>
                            <span className="grid gap-1">
                                <span className="text-sm font-extrabold text-[#395886]">
                                    {option.title}
                                </span>
                                <span className="text-xs leading-relaxed text-[#395886]/70">
                                    {option.description}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>

            {selectedKind === "kompen_respon" ? (
                <UploadPanel
                    onUploadRequestActivityChange={
                        onUploadRequestActivityChange
                    }
                />
            ) : (
                <ActiveStudentUploadPanel
                    onUploadRequestActivityChange={
                        onUploadRequestActivityChange
                    }
                />
            )}
        </section>
    );
}
