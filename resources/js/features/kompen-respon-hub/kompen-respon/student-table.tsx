import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { number } from "../shared/lib/formatters";
import type { Pagination, Student } from "../shared/types";
import { Pager } from "../shared/components/pagination";

export function StudentTable({
    data,
    isEditMode = false,
    onSelect,
    onOpenProfile,
}: {
    data: Pagination<Student>;
    isEditMode?: boolean;
    onSelect?: (student: Student) => void;
    onOpenProfile?: (student: Student) => void;
}): React.JSX.Element {
    const headings = [
        "Tingkat",
        "NIM",
        "Nama",
        "Kelas",
        "Periode",
        "Status",
        "T[j]",
        "S[j]",
        "I[j]",
        "B[j]",
        "Kompen[j]",
        "Responsi[j]",
        "Total[j]",
        "Komp. selesai[j]",
        "Resp. selesai[j]",
        "Sisa[j]",
        ...(onOpenProfile ? ["Profil"] : []),
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1150px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr
                                key={student.id}
                                className={cn(
                                    "transition-colors duration-150 hover:bg-[#B1C9EF]/10",
                                    isEditMode &&
                                        "cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]",
                                )}
                                onClick={() =>
                                    isEditMode && onSelect?.(student)
                                }
                            >
                                <td className="px-4 py-3.5 font-semibold text-[#395886]">
                                    {student.tingkat}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {student.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {student.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">
                                    {student.kelas}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {student.periode_semester}
                                </td>
                                <td className="px-4 py-3.5">
                                    <ProgressStatusBadge
                                        status={student.progress_status}
                                    />
                                </td>
                                {[
                                    student.total_jam_terlambat,
                                    student.total_jam_sakit,
                                    student.total_jam_izin,
                                    student.total_jam_bolos,
                                    student.effective_total_kompensasi_jam,
                                    student.effective_total_responsi_jam,
                                    student.effective_total_hutang_jam,
                                    student.effective_kompensasi_dikerjakan_jam,
                                    student.effective_responsi_dikerjakan_jam,
                                    student.effective_sisa_hutang_jam,
                                ].map((value, valueIndex) => (
                                    <td
                                        key={`${student.id}-${valueIndex}`}
                                        className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums"
                                    >
                                        {number(value)}
                                    </td>
                                ))}
                                {onOpenProfile ? (
                                    <td className="px-4 py-3.5">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onOpenProfile(student);
                                            }}
                                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                        >
                                            Lihat
                                        </Button>
                                    </td>
                                ) : null}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

export function ProgressStatusBadge({
    status,
}: {
    status: Student["progress_status"];
}): React.JSX.Element | null {
    const labels: Record<Student["progress_status"], string> = {
        completed: "Selesai",
        warning_active: "SP aktif",
        none: "",
    };
    const tones: Record<Student["progress_status"], string> = {
        completed: "bg-emerald-100 text-emerald-800",
        warning_active: "bg-rose-100 text-rose-800",
        none: "",
    };

    if (status === "none") {
        return null;
    }

    return (
        <span
            className={cn(
                "inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black",
                tones[status],
            )}
        >
            {labels[status]}
        </span>
    );
}
