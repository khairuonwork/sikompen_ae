import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            closeButton
            toastOptions={{
                classNames: {
                    toast: 'border border-[#8AAEE0] bg-white text-[#1E293B] shadow-[0_16px_32px_rgba(30,62,109,0.18)]',
                    title: 'font-bold text-[#1E293B]',
                    description: 'text-[#526B93]',
                    success: 'border-emerald-300 bg-emerald-50',
                    error: 'border-rose-300 bg-rose-50',
                    warning: 'border-amber-300 bg-amber-50',
                    info: 'border-[#8AAEE0] bg-[#F7FAFF]',
                    closeButton: 'border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#395886] hover:text-white',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
