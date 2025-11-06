import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';

interface ActionButtonProps {
    children: React.ReactNode;
    onClick?: () => void;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
    className?: string;
}

export function ActionButton({
    children,
    onClick,
    variant = 'default',
    size = 'sm',
    disabled = false,
    type = 'button',
    className = '',
    ...props
}: ActionButtonProps) {
    return (
        <Button
            onClick={onClick}
            variant={variant}
            size={size}
            disabled={disabled}
            type={type}
            className={className}
            {...props}
        >
            {children}
        </Button>
    );
}

interface CreateButtonProps {
    onClick?: () => void;
    children: React.ReactNode;
    disabled?: boolean;
    className?: string;
}

export function CreateButton({ onClick, children, disabled = false, className = '' }: CreateButtonProps) {
    return (
        <ActionButton
            onClick={onClick}
            disabled={disabled}
            className={className}
        >
            <Plus className="mr-2 h-4 w-4" />
            {children}
        </ActionButton>
    );
}
