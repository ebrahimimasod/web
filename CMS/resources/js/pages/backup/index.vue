<script setup lang="ts">
import AppPageTitle from '@/components/AppPageTitle.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DeleteResource from '@/components/DeleteResource.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { toPersianDate } from '@/lib/utils';
import SettingDialog from '@/pages/backup/components/SettingDialog.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { DownloadIcon, Ellipsis, PlayIcon, RotateCcw, SettingsIcon, Trash } from 'lucide-vue-next';
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
const restoreData = reactive({
    filePath: null,
    dialog: false,
});

function openDialogForDelete(filePath) {
    deleteFile.url = route('admin.backup.delete', { filePath });
    deleteFile.dialog = true;
}

function openDialogForRestore(filePath){
    restoreData.dialog = true;
    restoreData.filePath = filePath;
}

function onRestore(){
    router.get(route('admin.backup.restore.page',{filePath:restoreData.filePath}))
}


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
                        <Button class="ml-2" @click="router.visit(route('admin.backup.run'))">
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
                        <TableHead>نام فایل</TableHead>
                        <TableHead>حجم فایل</TableHead>
                        <TableHead>تاریخ ایجاد</TableHead>
                        <TableHead>عملیات</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="file in files" :key="file.name">
                        <TableCell>{{ file.name }}</TableCell>
                        <TableCell>{{ file.size_kb }}</TableCell>
                        <TableCell>{{ toPersianDate(file.created_at) }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger>
                                    <Button variant="ghost" size="icon">
                                        <Ellipsis class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem class="cursor-pointer" @click="openDialogForDelete(file.path)">
                                        <Trash class="text-red-500" />
                                        <span class="text-red-500">حذف</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="cursor-pointer" as="a" :href="route('admin.backup.download', { filePath: file.path })">
                                        <DownloadIcon />
                                        <span>دانلود</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="cursor-pointer" @click="openDialogForRestore(file.path)">
                                        <RotateCcw class="text-blue-500" />
                                        <span class="text-blue-500">بازگردانی</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <TableEmpty v-else />

            <DeleteResource v-model="deleteFile.dialog" label="فایل پشتیبان " :url="deleteFile.url" />
            <ConfirmDialog
                @on-confirm="onRestore"
                v-model="restoreData.dialog"
                action-label="بله مطمئنم"
                label="آیا از بازگردانی این فایل پشتیبان مطمئن هستید؟" />
        </div>

        <SettingDialog v-model="settingData.dialog" />
    </AppLayout>
</template>
