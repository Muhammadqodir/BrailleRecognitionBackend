import { ArrowUpRight } from 'lucide-react';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

/**
 * A link to another admin page that opens in a new tab, so the page you are
 * on (its filters, scroll, an open dialog) stays where it was. The arrow says so.
 */
export function NewTabLink({ className, children, icon = true, ...props }: ComponentProps<'a'> & { icon?: boolean }) {
    return (
        <a target="_blank" rel="noopener" className={cn('inline-flex items-center gap-1 hover:underline', className)} {...props}>
            {children}
            {icon && <ArrowUpRight className="size-3.5 shrink-0 opacity-60" aria-hidden />}
            <span className="sr-only">(opens in a new tab)</span>
        </a>
    );
}
