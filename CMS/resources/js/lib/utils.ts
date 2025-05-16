import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import moment from "jalali-moment";


export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}


export function toPersianDate(date: string, format: string = 'jYYYY-jMM-jDD HH:mm'): string {
    return moment(date).format(format)
}
