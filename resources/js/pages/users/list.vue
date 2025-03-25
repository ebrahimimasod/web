<script setup lang="ts">
import {defineProps} from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem} from '@/types';
import {Head} from '@inertiajs/vue3';
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow,} from '@/components/ui/table'
import ResourcePagination from "@/components/ResourcePagination.vue";

const {users} = defineProps(['users'])


console.log({users})

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'کاربران',
        href: '/users',
    },
];
</script>

<template>
    <Head title="کاربران"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>نام</TableHead>
                        <TableHead>ایمیل</TableHead>
                        <TableHead>موبایل</TableHead>
                        <TableHead>وضعیت</TableHead>
                        <TableHead>تاریخ ثبت نام</TableHead>
                        <TableHead>عملیات</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data">
                        <TableCell>{{ user.first_name }} {{user.last_name}}</TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell>{{ user.phone_number }}</TableCell>
                        <TableCell>{{ user.status }}</TableCell>
                        <TableCell>{{ user.created_at }}</TableCell>
                        <TableCell>actions</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <ResourcePagination :meta="users.meta"/>
        </div>
    </AppLayout>
</template>
