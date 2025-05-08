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
          <div class="grid grid-cols-2 gap-2 mb-2">
              <Input  placeholder="منطقه (Region)" class="h-9 text-left"   v-model="model.config.region" />
              <Input  placeholder="صندوقچه (Bucket)" class="h-9 text-left"   v-model="model.config.bucket" />
          </div>
            <Input  placeholder="آدرس دسترسی (Endpoint)" class="h-9 mb-2 text-left"   v-model="model.config.endpoint" />
            <Input type="password"  placeholder="کلید دسترسی (Access key)" class="h-9 mb-2 text-left"   v-model="model.config.access_key" />
            <Input type="password"  placeholder="کلید امنیتی (Secret key)" class="h-9 mb-2 text-left"   v-model="model.config.secret_key" />
        </div>
    </div>
</template>
