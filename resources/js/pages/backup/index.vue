<script setup lang="ts">
import AppPageTitle from '@/components/AppPageTitle.vue';
import DeleteResource from '@/components/DeleteResource.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingDialog from '@/pages/backup/components/SettingDialog.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { PlayIcon, SettingsIcon } from 'lucide-vue-next';
import { defineProps, reactive } from 'vue';

const deleteFile = reactive({
    dialog: false,
    url: null,
});
const { files } = defineProps(['files']);
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'پنل مدیریت',
        href: '/dashboard',
    },
    {
        title: 'پشتیبان‌گیری',
        href: '/backup',
    },
];
const settingData = reactive({
    dialog: false,
});
</script>

<template>
    <Head title="پشتیبان‌گیری" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <AppPageTitle
                icon="DatabaseBackup"
                title="پشتیبان‌گیری"
                subtitle="در این جدول می توانید لیست همه فایل های پشتیبان را ببینید و آنها را مدیریت کنید."
            >
                <template #actions>
                    <div class="flex items-center justify-end">
                        <Button class="ml-2"  @click="router.visit(route('admin.backup.run'))" >
                            <PlayIcon />
                            پشتیبان‌گیری
                        </Button>

                        <Button variant="outline" @click="settingData.dialog = true">
                            <SettingsIcon />
                            تنظیمات پشتیبان‌گیری
                        </Button>
                    </div>
                </template>
            </AppPageTitle>

            <Table v-if="files.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>نام</TableHead>
                        <TableHead>وضعیت</TableHead>
                        <TableHead>تاریخ ثبت نام</TableHead>
                        <TableHead>عملیات</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <!--                    <TableRow v-for="file in files">-->
                    <!--                        <TableCell>{{ user.first_name }} {{ user.last_name }}</TableCell>-->
                    <!--                        <TableCell>{{ user.email }}</TableCell>-->
                    <!--                        <TableCell>{{ user.phone_number }}</TableCell>-->
                    <!--                        <TableCell>-->

                    <!--                            <Badge v-if="user.status" variant="secondary">-->
                    <!--                                فعال-->
                    <!--                            </Badge>-->
                    <!--                            <Badge v-else variant="destructive">-->
                    <!--                                غیرفعال-->
                    <!--                            </Badge>-->

                    <!--                            <Badge class="mr-1" v-if="user.is_admin" variant="default">-->
                    <!--                                ادمین-->
                    <!--                            </Badge>-->

                    <!--                        </TableCell>-->
                    <!--                        <TableCell>{{ toPersianDate(user.created_at) }}</TableCell>-->
                    <!--                        <TableCell>-->
                    <!--                            <DropdownMenu>-->
                    <!--                                <DropdownMenuTrigger>-->
                    <!--                                    <Button variant="ghost" size="icon">-->
                    <!--                                        <Ellipsis class="w-4 h-4"/>-->
                    <!--                                    </Button>-->

                    <!--                                </DropdownMenuTrigger>-->
                    <!--                                <DropdownMenuContent>-->
                    <!--                                    <DropdownMenuItem @click="openDialogForDelete(user.id)" class="cursor-pointer">-->
                    <!--                                        <Trash class="text-red-500"/>-->
                    <!--                                        <span class="text-red-500">حذف</span>-->
                    <!--                                    </DropdownMenuItem>-->
                    <!--                                    <DropdownMenuSeparator/>-->
                    <!--                                    <DropdownMenuItem @click="router.visit(route('admin.users.edit',{id:user.id}))" class="cursor-pointer">-->
                    <!--                                        <UserRoundPen/>-->
                    <!--                                        <span>ویرایش</span>-->
                    <!--                                    </DropdownMenuItem >-->
                    <!--                                    <DropdownMenuSeparator/>-->
                    <!--                                    <DropdownMenuItem  @click="router.visit(route('admin.users.show',{id:user.id}))"  class="cursor-pointer">-->
                    <!--                                        <Eye/>-->
                    <!--                                        <span>نمایش</span>-->
                    <!--                                    </DropdownMenuItem>-->

                    <!--                                </DropdownMenuContent>-->
                    <!--                            </DropdownMenu>-->
                    <!--                        </TableCell>-->
                    <!--                    </TableRow>-->
                </TableBody>
            </Table>

            <TableEmpty v-else />

            <DeleteResource v-model="deleteFile.dialog" label="فایل پشتیبان " :url="deleteFile.url" />
        </div>

        <SettingDialog v-model="settingData.dialog" />
    </AppLayout>
</template>
