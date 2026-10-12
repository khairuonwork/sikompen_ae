import { Form } from "@inertiajs/react";
import { Clock3 } from "lucide-react";
import { useEffect, useState } from "react";
import { updateImportAudit } from "@/actions/App/Http/Controllers/KompenResponHubActivityRetentionController";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

const retentionOptions = [30, 90, 180, 365, 730, 1095] as const;

export function ImportAuditRetentionPanel({
    retentionDays,
}: {
    retentionDays: number;
}): React.JSX.Element {
    const [selectedRetentionDays, setSelectedRetentionDays] = useState(
        String(retentionDays),
    );

    useEffect(() => {
        setSelectedRetentionDays(String(retentionDays));
    }, [retentionDays]);

    return (
        <section className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Retensi Log Upload
                    </h2>
                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                        Log upload yang lebih lama dari masa simpan dihapus otomatis setiap hari. File sumber dan data aktif tidak ikut dihapus.
                    </p>
                </div>
                <span className="rounded-full bg-[#B1C9EF]/30 px-2.5 py-1 text-xs font-bold text-[#395886]">
                    Saat ini: {retentionDays} hari
                </span>
            </div>
            <Form
                {...updateImportAudit.form()}
                className="mt-4 flex flex-wrap items-end gap-3 border-t border-[#D5DEEF] pt-4"
            >
                <input
                    name="import_audit_log_retention_days"
                    type="hidden"
                    value={selectedRetentionDays}
                />
                <div className="grid min-w-52 gap-1.5">
                    <Label
                        htmlFor="import-audit-log-retention"
                        className="text-xs font-bold text-[#395886]"
                    >
                        Simpan Log Upload selama
                    </Label>
                    <Select
                        value={selectedRetentionDays}
                        onValueChange={setSelectedRetentionDays}
                    >
                        <SelectTrigger
                            id="import-audit-log-retention"
                            className="h-10 rounded-2xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886]"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="rounded-2xl text-xs font-semibold">
                            {retentionOptions.map((days) => (
                                <SelectItem key={days} value={String(days)}>
                                    {days} hari
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <Button
                    type="submit"
                    className="h-10 rounded-2xl bg-[#395886] px-4 text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                >
                    <Clock3 className="mr-1.5 size-3.5" />
                    Simpan retensi
                </Button>
            </Form>
            <p className="mt-3 text-xs font-medium text-amber-800">
                Penghapusan Log Upload bersifat permanen. Retensi dapat diubah kembali kapan saja sebelum log melewati batas baru.
            </p>
        </section>
    );
}
