import type { ComponentProps, Ref } from 'react';
import { forwardRef } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface PhoneInputProps
    extends Omit<ComponentProps<'input'>, 'type' | 'onChange'> {
    onChange?: (value: string) => void;
}

const PhoneInput = forwardRef<HTMLInputElement, PhoneInputProps>(
    ({ className, onChange, value, ...props }, ref) => {
        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            let input = e.target.value;

            // Remove non-digits
            input = input.replace(/\D/g, '');

            // Only allow first digit to be 1 or 7
            if (input.length > 0 && !/^[17]/.test(input)) {
                input = '';
            }

            // Limit to 9 digits
            if (input.length > 9) {
                input = input.slice(0, 9);
            }

            // Update the underlying input value
            e.target.value = input;

            // Trigger onChange if provided
            if (onChange) {
                onChange(input);
            }

            // Also call the original onChange if it exists in props
            props.onChange?.(e);
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
