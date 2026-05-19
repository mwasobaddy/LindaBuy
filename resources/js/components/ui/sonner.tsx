import { CheckCircle2, XCircle, AlertTriangle, Info, Loader2 } from 'lucide-react';
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
            icons={{
                success: <CheckCircle2 className="h-5 w-5 text-green-500" />,
                error: <XCircle className="h-5 w-5 text-red-500" />,
                warning: <AlertTriangle className="h-5 w-5 text-yellow-500" />,
                info: <Info className="h-5 w-5 text-blue-500" />,
                loading: <Loader2 className="h-5 w-5 animate-spin text-gray-500" />,
            }}
            toastOptions={{
                classNames: {
                    success: 'border-l-4 border-green-500',
                    error: 'border-l-4 border-red-500',
                    warning: 'border-l-4 border-yellow-500',
                    info: 'border-l-4 border-blue-500',
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
