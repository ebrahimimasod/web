<script setup lang="ts">
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import FtpConnection from '@/pages/backup/components/remote-storages/FtpConnection.vue';
import LocalConnection from '@/pages/backup/components/remote-storages/LocalConnection.vue';
import S3Connection from '@/pages/backup/components/remote-storages/S3Connection.vue';
import SftpConnection from '@/pages/backup/components/remote-storages/SftpConnection.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const emit = defineEmits(['onClose']);

const pageProps = usePage().props
let rawSettings = pageProps.backup_storage_setting

if (!Array.isArray(rawSettings)) {
    console.warn('⚠️ props.backup_storage_setting is not an array:', rawSettings)
    rawSettings = []
}

const [
    localSetting  = { enabled: false, config: {} },
    ftpSetting    = { enabled: false, config: {} },
    sftpSetting   = { enabled: false, config: {} },
    s3Setting     = { enabled: false, config: {} },
] = rawSettings

function toInt(value, fallback) {
    const n = parseInt(value, 10)
    return Number.isNaN(n) ? fallback : n
}


const form = useForm({
    connections: [
        {
            title: 'سرور محلی (Local)',
            key:   'local',
            enabled: Boolean(localSetting.enabled),
            config: {},
        },
        {
            title: 'سرور ریموت (FTP)',
            key:   'ftp',
            enabled: Boolean(ftpSetting.enabled),
            config: {
                host:     ftpSetting?.config?.host     || '',
                port:     toInt(ftpSetting?.config?.port, 21),
                username: ftpSetting?.config?.username || '',
                password: ftpSetting?.config?.password || '',
            },
        },
        {
            title: 'سرور ریموت (SFTP)',
            key:   'sftp',
            enabled: Boolean(sftpSetting.enabled),
            config: {
                host:       sftpSetting?.config?.host       || '',
                port:       toInt(sftpSetting?.config?.port, 22),
                username:   sftpSetting?.config?.username   || '',
                password:   sftpSetting?.config?.password   || '',
                ssh_key:    sftpSetting?.config?.ssh_key    || '',
                auth_by:    sftpSetting?.config?.auth_by    || 'password',
            },
        },
        {
            title: 'سرور ریموت (S3)',
            key:   's3',
            enabled: Boolean(s3Setting.enabled),
            config: {
                region:      s3Setting?.config?.region      || '',
                bucket:      s3Setting?.config?.bucket      || '',
                endpoint:    s3Setting?.config?.endpoint    || '',
                access_key:  s3Setting?.config?.access_key  || '',
                secret_key:  s3Setting?.config?.secret_key  || '',
            },
        },
    ],
})


function submit() {
    form.post(route('admin.backup.setting.storage'), {
        onSuccess: () => {
            emit('onClose');
        },
    });
}
</script>

<template>
    <div>
        <Accordion type="single" class="w-full" collapsible>
            <AccordionItem v-for="item in form.connections" :key="item.key" :value="item.key">
                <AccordionTrigger>
                    <div class="flex items-center justify-start">
                        <span>
                            {{ item.title }}
                        </span>
                        <Badge class="mr-2 text-[9px]" v-if="item.enabled">فعال</Badge>
                    </div>
                </AccordionTrigger>

                <AccordionContent class="mb-2 rounded-md bg-gray-100 p-2">
                    <LocalConnection v-model="form.connections[0]" v-if="item.key === 'local'" />
                    <FtpConnection v-model="form.connections[1]" v-if="item.key === 'ftp'" />
                    <SftpConnection v-model="form.connections[2]" v-if="item.key === 'sftp'" />
                    <S3Connection v-model="form.connections[3]" v-if="item.key === 's3'" />
                </AccordionContent>
            </AccordionItem>
        </Accordion>
        <DialogFooter class="mt-6">
            <Button variant="default" @click="submit" :loading="form.processing"> ذخیره کردن</Button>
        </DialogFooter>
    </div>
</template>
