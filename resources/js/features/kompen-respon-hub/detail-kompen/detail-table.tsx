import { cn } from "@/lib/utils";
import { number } from "../shared/lib/formatters";
import type { Detail, Pagination } from "../shared/types";
import { Pager } from "../shared/components/pagination";

export function DetailTable({
    data,
    isEditMode = false,
    onSelect,
}: {
    data: Pagination<Detail>;
    isEditMode?: boolean;
    onSelect?: (detail: Detail) => void;
}): React.JSX.Element {
    const headings = [
        "Tanggal",
        "NIM",
        "Nama",
        "Kelas",
        "Mata Kuliah",
        "Dosen",
        "Pertemuan",
        "Presensi",
        "Terlambat",
        "Kompen[j]",
        "Responsi[j]",
        "Keterangan",
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="data-table-scroll">
                <table className="data-table min-w-[1450px]">
                    <colgroup>
                        {["8rem", "8.5rem", "12rem", "7.5rem", "13rem", "12rem", "9rem", "8rem", "7rem", "7rem", "7rem", "14rem"].map((width, index) => (
                            <col key={`${width}-${index}`} style={{ width }} />
                        ))}
                    </colgroup>
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-5 py-4">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((detail) => (
                            <tr
                                key={detail.id}
                                className={cn(
                                    "transition-colors duration-150 hover:bg-[#B1C9EF]/10",
                                    isEditMode &&
                                        "cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]",
                                )}
                                onClick={() => isEditMode && onSelect?.(detail)}
                            >
                                <td className="px-5 py-4 text-xs font-semibold whitespace-nowrap text-[#395886]">
                                    {detail.tanggal}
                                </td>
                                <td className="px-5 py-4 font-mono text-xs font-bold text-[#395886]">
                                    {detail.nim}
                                </td>
                                <td className="px-5 py-4 font-bold text-[#395886]">
                                    {detail.nama_mahasiswa}
                                </td>
                                <td className="px-5 py-4 font-medium text-[#395886]/80">
                                    {detail.kelas}
                                </td>
                                <td className="px-5 py-4 font-medium text-[#395886]">
                                    {detail.mata_kuliah}
                                </td>
                                <td className="px-5 py-4 text-xs text-[#395886]/80">
                                    {detail.nama_dosen}
                                </td>
                                <td className="px-5 py-4 text-xs font-medium text-[#628ECB]">
                                    {detail.jenis_pertemuan}
                                </td>
                                <td className="px-5 py-4 text-xs font-semibold text-[#395886]">
                                    {detail.presensi}
                                </td>
                                <td className="px-5 py-4 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {detail.menit_keterlambatan}
                                </td>
                                <td className="px-5 py-4 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_kompensasi)}
                                </td>
                                <td className="px-5 py-4 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_responsi)}
                                </td>
                                <td className="px-5 py-4 text-xs text-[#395886]/70">
                                    {detail.keterangan ?? "—"}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}
