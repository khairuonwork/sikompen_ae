

export function ProgressBar({ value }: { value: number }): React.JSX.Element {
    const progress = Math.min(100, Math.max(0, value));

    return (
        <div
            className="h-2.5 overflow-hidden rounded-full bg-[#D5DEEF]/60 shadow-inner"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={progress}
            aria-label={`Progres impor ${progress}%`}
        >
            <div
                className="h-full rounded-full bg-gradient-to-r from-[#395886] to-[#628ECB] transition-[width] duration-300"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}
