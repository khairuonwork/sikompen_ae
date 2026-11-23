import { Form } from "@inertiajs/react";
import { useRef, useState, type ComponentProps } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";

type Confirmation = {
    title: string;
    description: string;
    submit: (finish: () => void) => void;
    confirmButtonClassName?: string;
};

export function useConfirmAction() {
    const [action, setAction] = useState<Confirmation | null>(null);
    const [processing, setProcessing] = useState(false);
    const trigger = useRef<HTMLElement | null>(null);
    const cancel = useRef<HTMLButtonElement>(null);
    const busy = useRef(false);

    function confirm(action: Confirmation) {
        if (busy.current) return;
        trigger.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        setAction(action);
    }

    function submit() {
        if (!action || busy.current) return;
        busy.current = true;
        setProcessing(true);
        action.submit(() => {
            busy.current = false;
            setProcessing(false);
            setAction(null);
        });
    }

    const dialog = (
        <Dialog open={action !== null} onOpenChange={(open) => {
            if (!open && !busy.current) setAction(null);
        }}>
            <DialogContent
                className="rounded-3xl border-[#D5DEEF] bg-white text-[#395886]"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    cancel.current?.focus();
                }}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    trigger.current?.focus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>{action?.title}</DialogTitle>
                    <DialogDescription className="text-[#395886]">{action?.description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        ref={cancel}
                        type="button"
                        variant="outline"
                        disabled={processing}
                        className="rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]"
                        onClick={() => setAction(null)}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        className={action?.confirmButtonClassName ?? "rounded-xl bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white"}
                        onClick={submit}
                    >
                        {processing ? "Memproses…" : action?.title}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { confirm, dialog, processing };
}

type ConfirmFormProps = ComponentProps<typeof Form<Record<string, string>>> & {
    title: string;
    description: string;
    confirmButtonClassName?: string;
};

export function ConfirmForm({ title, description, confirmButtonClassName, ...props }: ConfirmFormProps) {
    const { confirm, dialog } = useConfirmAction();
    const finishVisit = useRef<(() => void) | null>(null);

    return (
        <>
            <Form
                {...props}
                onSubmitCapture={(event) => {
                    if (finishVisit.current) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const form = event.currentTarget;
                    confirm({
                        title,
                        description,
                        confirmButtonClassName,
                        submit: (finish) => {
                            if (!form.reportValidity()) {
                                finish();
                                return;
                            }
                            finishVisit.current = finish;
                            form.requestSubmit();
                        },
                    });
                }}
                onFinish={(visit) => {
                    finishVisit.current?.();
                    finishVisit.current = null;
                    props.onFinish?.(visit);
                }}
            />
            {dialog}
        </>
    );
}
