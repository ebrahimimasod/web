<script setup lang="ts">
import {defineModel, defineProps} from "vue";
import {Button} from '@/components/ui/button';
import {Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle,} from '@/components/ui/dialog';
import {useForm} from "@inertiajs/vue3";
import {X} from "lucide-vue-next";


const model = defineModel();
const props = defineProps({
    label: {
        type: String,
        default: 'ایتم'
    },
    url: {
        type: String,
    },

});


const form = useForm({});

function onDelete() {
    form.delete(props.url, {
        preserveScroll: true,
        onSuccess: () => model.value = false,
    });
}

</script>

<template>
    <Dialog :open="model">
        <DialogContent @close="model=false">
            <div class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        آیا از حذف کردن این
                        {{ label }}
                        مطمئن هستید؟
                    </DialogTitle>

                </DialogHeader>


                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" @click="model = false"> لغو</Button>
                    </DialogClose>

                    <Button variant="destructive" @click="onDelete" :disabled="form.processing" :loading="form.processing">
                        حذف کردن
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
