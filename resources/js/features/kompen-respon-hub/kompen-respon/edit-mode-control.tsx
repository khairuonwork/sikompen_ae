import { PencilLine } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function EditModeControl({
    enabled,
    onChange,
}: {
    enabled: boolean;
    onChange: (enabled: boolean) => void;
}): React.JSX.Element {
    return (
        <Button
            type="button"
            variant="outline"
            onClick={() => onChange(!enabled)}
            className={cn(
                "rounded-2xl border-[#8AAEE0] bg-white px-4 text-xs font-bold text-[#395886]",
                enabled &&
                    "border-[#395886] bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white",
            )}
        >
            <PencilLine className="mr-2 size-4" />
            {enabled ? "Mode perbaikan aktif" : "Mode perbaikan data"}
        </Button>
    );
}
