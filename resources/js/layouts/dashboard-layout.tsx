import { type PropsWithChildren, type ReactNode } from 'react';

import { AppSidebar } from '@/components/app-sidebar';
import { AppearanceToggle } from '@/components/appearance-toggle';
import { Separator } from '@/components/ui/separator';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface DashboardLayoutProps {
    title: string;
    description: string;
    /** Rendered at the right end of the main header row (e.g. a page's search input). */
    actions?: ReactNode;
    /** A second row beneath the header (e.g. a result count and filter pills). */
    toolbar?: ReactNode;
    /** Drops the top gap above the content so it sits flush under the header/toolbar (e.g. a full-bleed table). */
    flush?: boolean;
}

export function DashboardLayout({ title, description, actions, toolbar, flush, children }: PropsWithChildren<DashboardLayoutProps>) {
    return (
        <TooltipProvider delayDuration={0}>
            <SidebarProvider>
                <AppSidebar />
                <SidebarInset className="min-w-0">
                    <header className="sticky top-0 z-10 border-b bg-background">
                        <div className="flex h-14 items-center gap-3 px-4 sm:px-6">
                            <SidebarTrigger className="md:hidden" />
                            <Separator orientation="vertical" className="md:hidden data-[orientation=vertical]:h-5" />
                            <div className="min-w-0 flex-1 leading-tight">
                                <h1 className="truncate text-sm font-medium">{title}</h1>
                                <p className="truncate text-xs text-muted-foreground">{description}</p>
                            </div>
                            {actions}
                            <AppearanceToggle />
                        </div>
                        {toolbar && <div className="flex h-11 items-center gap-2 px-4 sm:px-6">{toolbar}</div>}
                    </header>
                    <div
                        className={cn(
                            'flex min-h-0 flex-1 flex-col',
                            flush ? 'overflow-hidden' : 'overflow-y-auto gap-4 p-4 md:gap-6 md:p-6 [&>*]:shrink-0',
                        )}
                    >
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
