import { FileSpreadsheet, ListFilter } from "lucide-react";

export function TableGuide({
    title,
    items,
}: {
    title: string;
    items: string[];
}): React.JSX.Element {
    return (
        <details className="group rounded-2xl border border-[#D5DEEF] bg-[#F0F3FA]/45 px-4 py-3 text-xs text-[#395886]">
            <summary className="cursor-pointer list-none font-bold marker:hidden">
                <span className="inline-flex items-center gap-2">
                    <ListFilter className="size-3.5" /> {title}
                </span>
            </summary>
            <ul className="mt-3 grid list-disc gap-1.5 pl-5 leading-relaxed text-[#395886]/75">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </details>
    );
}

export function EmptyTableState({
    isAdmin,
}: {
    isAdmin: boolean;
}): React.JSX.Element {
    return (
        <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-12 text-center text-sm backdrop-blur-xl transition-all duration-300">
            <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl border border-[#8AAEE0]/50 bg-[#B1C9EF]/40 text-[#395886] shadow-inner">
                <FileSpreadsheet className="size-7" />
            </div>
            <p className="text-base font-extrabold text-[#395886]">
                {isAdmin ? "Belum Ada Data yang Cocok" : "Belum Ada Data"}
            </p>
            <p className="mx-auto mt-1 max-w-sm text-xs leading-relaxed font-medium text-[#395886]/70">
                {isAdmin
                    ? "Unggah workbook XLSX melalui tab Upload Dokumen atau sesuaikan filter yang digunakan."
                    : "Belum ada data yang sesuai dengan filter yang dipilih. Coba pilih periode lain atau atur ulang filter."}
            </p>
        </div>
    );
}
