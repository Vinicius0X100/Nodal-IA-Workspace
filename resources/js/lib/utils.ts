import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Utility para merge de classes Tailwind sem conflitos.
 * Padrão shadcn/ui — usado em todos os componentes.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
