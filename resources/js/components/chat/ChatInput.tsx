import { useState, useRef, KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface ChatInputProps {
    onSend: (message: string) => Promise<void>;
    disabled?: boolean;
}

export default function ChatInput({ onSend, disabled }: ChatInputProps) {
    const [text, setText] = useState('');
    const [sending, setSending] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleSend = async () => {
        const msg = text.trim();
        if (!msg || sending || disabled) return;

        setSending(true);
        try {
            await onSend(msg);
            setText('');
            inputRef.current?.focus();
        } finally {
            setSending(false);
        }
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="flex items-center gap-2 border-t border-border bg-background px-4 py-3">
            <Input
                ref={inputRef}
                placeholder="Type a message..."
                value={text}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={disabled || sending}
                maxLength={1000}
                className="flex-1"
            />
            <Button
                onClick={handleSend}
                disabled={!text.trim() || sending || disabled}
                size="sm"
            >
                {sending ? 'Sending...' : 'Send'}
            </Button>
        </div>
    );
}
