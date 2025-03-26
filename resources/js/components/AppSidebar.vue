<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem
} from '@/components/ui/sidebar';
import {type NavItem} from '@/types';
import {Link} from '@inertiajs/vue3';
import {
    Blocks,
    BookOpen,
    Folder,
    Headset,
    Info,
    LayoutGrid,
    PanelsTopLeft,
    RefreshCcw,
    Settings,
    Users
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import NavFooter from "@/components/NavFooter.vue";

const mainNavItems: NavItem[] = [
    {
        title: 'داشبورد',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'به‌روز‌رسانی',
        href: '/update',
        icon: RefreshCcw,
    },
    {
        title: 'کاربران',
        href: '/users',
        icon: Users,
    },
    {
        title: 'افزونه‌ها',
        href: '/plugins',
        icon: Blocks,
    },

    {
        title: 'صفحات',
        href: '/pages',
        icon: PanelsTopLeft,
    },

    {
        title: 'تنظیمات',
        href: '/setting',
        icon: Settings,
    },

];


const footerNavItems: NavItem[] = [
    {
        title: 'پشتیبانی',
        href: '/support',
        icon: Headset,
    },
    {
        title: 'درباره سازنده',
        href: '/info',
        icon: Info,
    },
];


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo/>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems"/>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser/>
        </SidebarFooter>
    </Sidebar>
    <slot/>
</template>
