import { AlertTriangle, CheckCircle2, UsersRound } from "lucide-react";
import type { DataConsistencyRecord } from "../shared/types";

export function DataConsistencyPanel({
    records,
}: {
    records: DataConsistencyRecord[];
}): React.JSX.Element {
    return (
        <section className="rounded-3xl border border-[#B1C9EF]/70 bg-[#B1C9EF]/15 p-5">
            <div className="flex flex-col gap-1">
                <h3 className="flex items-center gap-2 text-sm font-extrabold text-[#395886]">
                    <UsersRound className="size-4" /> Kesesuaian sumber data
                </h3>
                <p className="text-xs font-medium text-[#395886]/70">
                    Periksa kesesuaian NIM antara workbook Kompen dan Respon aktif dengan roster aktif. Sistem tidak mengubah sumber data secara otomatis.
                </p>
            </div>

            {records.length === 0 ? (
                <p className="mt-4 rounded-2xl border border-dashed border-[#8AAEE0] bg-white/70 px-4 py-3 text-xs font-semibold text-[#395886]/70">
                    Belum ada workbook Kompen dan Respon yang digunakan untuk diperiksa.
                </p>
            ) : (
                <div className="mt-4 grid gap-3 lg:grid-cols-2">
                    {records.map((record) => {
                        const isReady = record.status === "ready";
                        const isMissingRoster = record.status === "roster_missing";

                        return (
                            <article
                                key={record.periode_semester}
                                className="rounded-2xl border border-white bg-white/90 p-4 shadow-2xs"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-extrabold text-[#395886]">
                                            {record.periode_semester}
                                        </p>
                                        <p className="mt-1 text-[11px] font-medium text-[#395886]/65">
                                            {record.kompen_respon_students} mahasiswa Kompen/Respon · {record.roster_students} mahasiswa roster
                                        </p>
                                    </div>
                                    <span
                                        className={
                                            isReady
                                                ? "inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-emerald-800 uppercase"
                                                : "inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[10px] font-black tracking-wider text-amber-800 uppercase"
                                        }
                                    >
                                        {isReady ? <CheckCircle2 className="size-3" /> : <AlertTriangle className="size-3" />}
                                        {isReady ? "Selaras" : isMissingRoster ? "Roster belum dipilih" : "Perlu ditinjau"}
                                    </span>
                                </div>
                                {!isReady ? (
                                    <div className="mt-3 grid gap-2 text-[11px] leading-relaxed text-[#395886]/75">
                                        {isMissingRoster ? (
                                            <p>Pilih satu roster aktif untuk periode ini agar akses mahasiswa dan pengecekan data dapat digunakan.</p>
                                        ) : (
                                            <>
                                                <p>
                                                    {record.missing_from_roster.length > 0
                                                        ? `NIM belum ada di roster: ${record.missing_from_roster.join(", ")}${record.missing_from_roster.length === 5 ? "…" : ""}.`
                                                        : "Semua NIM Kompen/Respon ada di roster."}
                                                </p>
                                                <p>
                                                    {record.missing_from_kompen_respon.length > 0
                                                        ? `NIM belum ada di workbook Kompen/Respon: ${record.missing_from_kompen_respon.join(", ")}${record.missing_from_kompen_respon.length === 5 ? "…" : ""}.`
                                                        : "Tidak ada NIM roster tambahan."}
                                                </p>
                                            </>
                                        )}
                                    </div>
                                ) : null}
                            </article>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
