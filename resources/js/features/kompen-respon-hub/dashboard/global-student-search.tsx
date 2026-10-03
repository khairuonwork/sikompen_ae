import { useEffect, useId, useState } from "react";
import { LoaderCircle, Search, X } from "lucide-react";
import { adminStudentSearch } from "@/actions/App/Http/Controllers/KompenResponHubController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { StudentSearchResult } from "../shared/types";

const minimumSearchLength = 2;

export function GlobalStudentSearch({
    isProfileLoading,
    onSelectStudent,
}: {
    isProfileLoading: boolean;
    onSelectStudent: (studentId: number) => void;
}): React.JSX.Element {
    const [query, setQuery] = useState("");
    const [results, setResults] = useState<StudentSearchResult[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const listboxId = useId();
    const normalizedQuery = query.trim();

    useEffect(() => {
        if (normalizedQuery.length < minimumSearchLength) {
            setResults([]);
            setErrorMessage(null);
            setIsLoading(false);

            return;
        }

        const requestController = new AbortController();
        const debounceTimer = window.setTimeout(async () => {
            setIsLoading(true);
            setErrorMessage(null);

            try {
                const response = await fetch(
                    adminStudentSearch.url({
                        query: { q: normalizedQuery },
                    }),
                    {
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        signal: requestController.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error("Student search request failed.");
                }

                const payload = (await response.json()) as {
                    data: StudentSearchResult[];
                };

                setResults(payload.data);
            } catch (error) {
                if (error instanceof DOMException && error.name === "AbortError") {
                    return;
                }

                setResults([]);
                setErrorMessage(
                    "Pencarian tidak dapat diproses. Periksa kata kunci lalu coba lagi.",
                );
            } finally {
                if (!requestController.signal.aborted) {
                    setIsLoading(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(debounceTimer);
            requestController.abort();
        };
    }, [normalizedQuery]);

    function clearSearch(): void {
        setQuery("");
        setResults([]);
        setErrorMessage(null);
    }

    return (
        <div className="relative w-full min-w-0 max-w-md">
            <label
                htmlFor="global-student-search"
                className="sr-only"
            >
                Cari mahasiswa secara global
            </label>
            <Search className="pointer-events-none absolute top-1/2 left-4 z-10 size-5 -translate-y-1/2 text-[#628ECB]" />
            <Input
                id="global-student-search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Cari nama atau NIM mahasiswa…"
                autoComplete="off"
                maxLength={100}
                disabled={isProfileLoading}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={listboxId}
                aria-expanded={normalizedQuery.length >= minimumSearchLength}
                className="h-12 rounded-2xl border-[#8AAEE0] bg-white/90 pr-11 pl-12 text-base text-[#395886] shadow-sm placeholder:text-[#628ECB]/60 focus-visible:border-[#395886] focus-visible:ring-[#B1C9EF]"
            />
            {isLoading ? (
                <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-[#628ECB]" />
            ) : query ? (
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    onClick={clearSearch}
                    aria-label="Hapus pencarian mahasiswa"
                    className="absolute top-1/2 right-1 size-8 -translate-y-1/2 rounded-xl text-[#628ECB] hover:bg-[#F0F3FA] hover:text-[#395886]"
                >
                    <X className="size-4" />
                </Button>
            ) : null}

            {normalizedQuery.length >= minimumSearchLength ? (
                <div
                    id={listboxId}
                    role="listbox"
                    aria-label="Hasil pencarian mahasiswa"
                    className="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-[#D5DEEF] bg-white shadow-xl"
                >
                    {errorMessage ? (
                        <p className="px-4 py-3 text-xs text-rose-700">
                            {errorMessage}
                        </p>
                    ) : isLoading ? (
                        <p className="px-4 py-3 text-xs text-[#395886]/70">
                            Mencari mahasiswa…
                        </p>
                    ) : results.length ? (
                        <div className="max-h-80 overflow-y-auto p-1.5">
                            {results.map((student) => (
                                <button
                                    key={student.id}
                                    type="button"
                                    role="option"
                                    onClick={() => {
                                        clearSearch();
                                        onSelectStudent(student.id);
                                    }}
                                    className="grid w-full gap-0.5 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-[#F0F3FA] focus-visible:bg-[#F0F3FA] focus-visible:outline-none"
                                >
                                    <span className="text-sm font-bold text-[#395886]">
                                        {student.nama_mahasiswa}
                                    </span>
                                    <span className="font-mono text-[11px] font-semibold text-[#628ECB]">
                                        {student.nim} · Tingkat {student.tingkat} · {student.kelas} · {student.periode_semester}
                                    </span>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="px-4 py-3 text-xs text-[#395886]/70">
                            Mahasiswa tidak ditemukan.
                        </p>
                    )}
                </div>
            ) : null}
        </div>
    );
}
