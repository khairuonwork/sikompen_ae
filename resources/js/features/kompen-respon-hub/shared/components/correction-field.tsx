import { useId, type ComponentProps } from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

export function CorrectionField({ label, error, wrapperClassName, id, className, ...props }: ComponentProps<typeof Input> & {
    label: string;
    error?: string;
    wrapperClassName?: string;
}) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    return (
        <div className={cn("grid min-w-0 content-start gap-1.5", wrapperClassName)}>
            <Label htmlFor={fieldId} className="text-xs font-bold text-[#395886]">{label}</Label>
            <Input {...props} id={fieldId} aria-invalid={!!error} aria-describedby={error ? fieldId + "-error" : undefined}
                className={cn("rounded-xl border-[#8AAEE0] bg-white text-[#1E293B] [color-scheme:light]", className)} />
            {error && <p id={fieldId + "-error"} className="text-xs font-bold text-rose-700">{error}</p>}
        </div>
    );
}
