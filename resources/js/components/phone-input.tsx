import type { ComponentProps, Ref } from 'react';
import { forwardRef } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type NativeOnChange = ComponentProps<'input'>['onChange'];

interface PhoneInputProps
    extends Omit<ComponentProps<'input'>, 'type' | 'onChange'> {
    onChange?: (value: string) => void;
}

const PhoneInput = forwardRef<HTMLInputElement, PhoneInputProps>(
    ({ className, onChange, value, ...props }, ref) => {
        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            let input = e.target.value;

            input = input.replace(/\D/g, '');

            if (input.length > 0 && !/^[17]/.test(input)) {
                input = '';
            }

            if (input.length > 9) {
                input = input.slice(0, 9);
            }

            e.target.value = input;

            if (onChange) {
                onChange(input);
            }
        };

        return (
            <div className="relative">
                <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                    +254
                </div>
                <Input
                    type="tel"
                    inputMode="numeric"
                    className={cn('pl-12', className)}
                    ref={ref}
                    value={value}
                    onChange={handleChange}
                    placeholder="1xx xxx xxx"
                    maxLength={9}
                    {...props}
                />
            </div>
        );
    }
);

PhoneInput.displayName = 'PhoneInput';

export default PhoneInput;
