import { ProgressBar } from "../shared/components/progress-bar";

export function UploadProgressPanel({
    progress,
}: {
    progress: number;
}): React.JSX.Element {
    return (
        <div className="grid gap-2.5 rounded-2xl border border-[#8AAEE0]/50 bg-[#B1C9EF]/20 p-4 backdrop-blur-md">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className="font-extrabold text-[#395886]">
                    Mengirim workbook ke server
                </span>
                <span className="font-mono font-bold text-[#395886] tabular-nums">
                    {progress}%
                </span>
            </div>
            <ProgressBar value={progress} />
            <p className="text-[11px] font-medium text-[#395886]/70">
                Jangan berpindah menu sampai pengiriman file selesai.
            </p>
        </div>
    );
}
