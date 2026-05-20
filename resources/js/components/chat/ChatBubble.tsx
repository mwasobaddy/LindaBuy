import { cn } from '@/lib/utils';

interface ChatBubbleProps {
    message: string;
    senderName: string;
    senderType: 'buyer' | 'seller';
    createdAt: string;
    isOwn: boolean;
}

export default function ChatBubble({ message, senderName, senderType, createdAt, isOwn }: ChatBubbleProps) {
    return (
        <div className={cn('flex', isOwn ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'max-w-[75%] rounded-2xl px-4 py-2.5',
                    isOwn
                        ? 'bg-primary text-primary-foreground rounded-br-sm'
                        : 'bg-muted text-foreground rounded-bl-sm',
                )}
            >
                <p className="text-xs font-medium opacity-80">
                    {isOwn ? 'You' : senderName}
                </p>
                <p className="mt-0.5 text-sm whitespace-pre-wrap break-words">{message}</p>
                <p
                    className={cn(
                        'mt-1 text-[10px] opacity-60',
                        isOwn ? 'text-right' : 'text-left',
                    )}
                >
                    {new Date(createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </p>
            </div>
        </div>
    );
}
