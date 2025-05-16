<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import BackupFile from '@/pages/backup/components/BackupFile.vue';
import BackupSchedule from '@/pages/backup/components/BackupSchedule.vue';
import BackupStorage from '@/pages/backup/components/BackupStorage.vue';

const model = defineModel();
</script>

<template>
    <Dialog :open="model">
        <DialogContent @close="model = false" class="max-w-2xl">
            <div class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle> تنظیمات پشتیبان‌گیری</DialogTitle>
                </DialogHeader>

                <Tabs default-value="account">
                    <TabsList class="mb-4">
                        <TabsTrigger value="backup_file_setting"> تنظیمات فایل پشتبان</TabsTrigger>
                        <TabsTrigger  value="backup_schedule"> پشتیبان‌گیری خودکار</TabsTrigger>
                        <TabsTrigger  value="backup_storage"> تنظیمات محل ذخیره فایل پشتیبان</TabsTrigger>
                    </TabsList>

                    <TabsContent value="backup_file_setting">
                        <BackupFile @onClose="model = false" />
                    </TabsContent>

                    <TabsContent value="backup_schedule">
                        <BackupSchedule @onClose="model = false" />
                    </TabsContent>

                    <TabsContent value="backup_storage">
                        <BackupStorage @onClose="model = false" />
                    </TabsContent>
                </Tabs>
            </div>
        </DialogContent>
    </Dialog>
</template>
