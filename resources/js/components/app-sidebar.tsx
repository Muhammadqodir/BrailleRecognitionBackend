import { Link, usePage } from '@inertiajs/react';
import { CreditCard, Database, LayoutGrid, ScanText, SquareDashedMousePointer, Users } from 'lucide-react';
import * as React from 'react';

import { Logo } from '@/components/logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import type { SharedProps } from '@/types';

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { url, props: pageProps } = usePage<SharedProps>();
    const { routes } = pageProps;

    const navItems = [
        {
            title: 'Dashboard',
            href: routes.dashboard,
            icon: LayoutGrid,
        },
        {
            title: 'Users',
            href: routes.users,
            icon: Users,
        },
        {
            title: 'Translations',
            href: routes.translations,
            icon: ScanText,
        },
        {
            title: 'Subscriptions',
            href: routes.subscriptions,
            icon: CreditCard,
        },
        {
            title: 'Labeling',
            href: routes.labeling,
            icon: SquareDashedMousePointer,
        },
        {
            title: 'Dataset',
            href: routes.dataset,
            icon: Database,
        },
    ];

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader className="h-14 justify-center p-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-14">
                            <Link href={routes.dashboard}>
                                <Logo className="size-8" />
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">Braille Recognition</span>
                                    <span className="truncate text-xs text-muted-foreground">Admin dashboard</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarSeparator className="mx-0" />
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {navItems.map((item) => (
                                <SidebarMenuItem key={item.href}>
                                    <SidebarMenuButton asChild isActive={url.split('?')[0] === new URL(item.href).pathname} tooltip={item.title}>
                                        <Link href={item.href}>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
