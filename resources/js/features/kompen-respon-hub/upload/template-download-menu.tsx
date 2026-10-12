import { Download, FileSpreadsheet, UsersRound } from "lucide-react";
import { toast } from "sonner";
import { downloadTemplate as downloadActiveStudentsTemplate } from "@/actions/App/Http/Controllers/KompenResponHubActiveStudentImportController";
import { downloadTemplate as downloadKompenResponTemplate } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

function notifyTemplateDownload(kind: string): void {
    toast.success("Unduhan template dimulai", {
        description: `Template ${kind} sedang diunduh.`,
    });
}

export function TemplateDownloadMenu(): React.JSX.Element {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                >
                    <Download className="size-4" />
                    Download Template
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 rounded-2xl p-2">
                <DropdownMenuLabel className="px-3 py-2 text-xs font-black tracking-[0.1em] text-[#395886] uppercase">
                    Pilih template
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild className="cursor-pointer rounded-xl p-3">
                    <a
                        href={downloadKompenResponTemplate.url()}
                        onClick={() =>
                            notifyTemplateDownload("Kompen dan Respon")
                        }
                    >
                        <FileSpreadsheet className="size-5" />
                        <span className="grid gap-0.5">
                            <span className="font-bold">Kompen dan Respon</span>
                            <span className="text-xs text-[#395886]/65">
                                Rekap Kompen/Responsi dan Detail Kompen.
                            </span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild className="cursor-pointer rounded-xl p-3">
                    <a
                        href={downloadActiveStudentsTemplate.url()}
                        onClick={() =>
                            notifyTemplateDownload("Daftar Mahasiswa Aktif")
                        }
                    >
                        <UsersRound className="size-5" />
                        <span className="grid gap-0.5">
                            <span className="font-bold">Mahasiswa Aktif</span>
                            <span className="text-xs text-[#395886]/65">
                                Roster mahasiswa aktif per periode dan blok kelas.
                            </span>
                        </span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
