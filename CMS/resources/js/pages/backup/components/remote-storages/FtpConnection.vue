<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { defineModel } from 'vue';

const model = defineModel();
</script>

<template>
    <div>
        <div class="flex items-center">
            <Switch id="local-storage" v-model="model.enabled" />
            <Label for="local-storage" v-if="model.enabled" class="mr-2">فعال</Label>
            <Label for="local-storage" v-else class="mr-2">غیرفعال</Label>
        </div>
        <div class="mt-2" v-if="model.enabled">
            <Input  placeholder="هاست (Host)" class="h-9 mb-2 text-left"   v-model="model.config.host" />
            <Input  placeholder="پورت (Port)" class="h-9 mb-2 text-left"   v-model="model.config.port" />
            <Input placeholder="کاربر (Username)" class="h-9 mb-2 text-left" v-model="model.config.username" />
            <Input type="password" placeholder="رمزعبور (Password)" class="h-9  text-left" v-model="model.config.password" />
        </div>
    </div>
</template>
