import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { Pagination } from "../types";

export function Pager<T>({ data }: { data: Pagination<T> }): React.JSX.Element {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] bg-white/40 px-5 py-3.5 text-xs font-medium text-[#395886]/80 md:text-sm">
            <span>
                Halaman{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.current_page}
                </span>{" "}
                dari{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.last_page}
                </span>{" "}
                ·{" "}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.total}
                </span>{" "}
                total data
            </span>
            <div className="flex gap-2">
                {data.links.prev ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.prev}
                            className="flex items-center gap-1"
                        >
                            <ChevronLeft className="size-4" />
                            Sebelumnya
                        </Link>
                    </Button>
                ) : null}
                {data.links.next ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.next}
                            className="flex items-center gap-1"
                        >
                            Berikutnya
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
