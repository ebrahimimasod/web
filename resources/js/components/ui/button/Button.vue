<script setup lang="ts">
import {Loader2} from "lucide-vue-next"
import {cn} from '@/lib/utils';
import {Primitive, type PrimitiveProps} from 'radix-vue';
import type {HTMLAttributes} from 'vue';
import {buttonVariants, type ButtonVariants} from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
    loading?: Boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
    loading: false,
});
</script>

<template>
    <Primitive :as="as" :as-child="asChild" :class="cn(buttonVariants({ variant, size }), props.class)">
        <slot/>
        <Loader2 v-if="props.loading" class="w-4 h-4 mr-2 animate-spin"/>
    </Primitive>
</template>
