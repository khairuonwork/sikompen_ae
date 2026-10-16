import type { PropsWithChildren } from "react";
import { SelectContent } from "@/components/ui/select";

export function ClassSelectContent({
    children,
}: PropsWithChildren): React.JSX.Element {
    return (
        <SelectContent
            className="w-[min(42rem,calc(100vw-2rem))] rounded-2xl text-xs font-semibold [&_[data-slot=select-viewport]]:grid [&_[data-slot=select-viewport]]:grid-cols-2 [&_[data-slot=select-viewport]]:gap-1 md:[&_[data-slot=select-viewport]]:grid-cols-4"
            align="start"
        >
            {children}
        </SelectContent>
    );
}
