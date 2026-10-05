import { router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Pagination } from "../types";

const perPageOptions = [15, 25, 50, 100] as const;

export function Pager<T>({ data }: { data: Pagination<T> }): React.JSX.Element {
    const [pageInput, setPageInput] = useState(
        data.meta.current_page.toString(),
    );

    useEffect(() => {
        setPageInput(data.meta.current_page.toString());
    }, [data.meta.current_page]);

    function paginationUrl(
        page: number,
        perPage: number = data.meta.per_page,
    ): string {
        const url = new URL(window.location.href);
        url.searchParams.set(data.meta.page_name, page.toString());
        url.searchParams.set("per_page", perPage.toString());

        return `${url.pathname}${url.search}`;
    }

    function visitPage(page: number, perPage?: number): void {
        router.get(
            paginationUrl(page, perPage),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    function submitPage(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const requestedPage = Number.parseInt(pageInput, 10);
        if (! Number.isInteger(requestedPage)) {
            setPageInput(data.meta.current_page.toString());

            return;
        }

        visitPage(Math.min(Math.max(requestedPage, 1), data.meta.last_page));
    }

    return (
        <div className="flex flex-col gap-3 border-t border-[#F0F3FA] bg-white/40 px-5 py-3.5 text-xs font-medium text-[#395886]/80 md:flex-row md:flex-wrap md:items-center md:justify-between md:text-sm">
            <p>
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
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <Select
                    value={data.meta.per_page.toString()}
                    onValueChange={(value) => visitPage(1, Number(value))}
                >
                    <SelectTrigger
                        className="h-8 w-[104px] rounded-xl border-[#8AAEE0] bg-white px-2 text-xs font-bold text-[#395886] hover:bg-[#F0F3FA]"
                        aria-label="Jumlah data per halaman"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="rounded-xl text-xs font-semibold">
                        {perPageOptions.map((option) => (
                            <SelectItem key={option} value={option.toString()}>
                                {option} data
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    disabled={data.links.prev === null}
                    onClick={() => visitPage(data.meta.current_page - 1)}
                    className="size-8 rounded-xl border-[#8AAEE0] bg-white text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    aria-label="Halaman sebelumnya"
                    title="Halaman sebelumnya"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <form className="flex items-center gap-1.5" onSubmit={submitPage}>
                    <Input
                        aria-label="Nomor halaman"
                        value={pageInput}
                        onChange={(event) => setPageInput(event.target.value)}
                        inputMode="numeric"
                        min={1}
                        max={data.meta.last_page}
                        pattern="[0-9]*"
                        className="h-8 w-14 rounded-xl border-[#8AAEE0] bg-white px-2 text-center text-xs font-bold text-[#395886]"
                        title="Ketik nomor halaman lalu tekan Enter"
                    />
                </form>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    disabled={data.links.next === null}
                    onClick={() => visitPage(data.meta.current_page + 1)}
                    className="size-8 rounded-xl border-[#8AAEE0] bg-white text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    aria-label="Halaman berikutnya"
                    title="Halaman berikutnya"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
