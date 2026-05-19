import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    const shownRef = useRef(false);

    useEffect(() => {
        return router.on('flash', (event) => {
            if (shownRef.current) {
                return;
            }

            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            shownRef.current = true;

            setTimeout(() => {
                toast[data.type](data.message, {
                    duration: 5000,
                    dismissible: true,
                });
            }, 100);
        });
    }, []);
}
