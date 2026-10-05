import * as React from 'react';
import { format } from 'date-fns';
import { Calendar as CalendarIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import type { FieldProps } from './types';

export default function DateField({ field, value, onChange }: FieldProps) {
    const withTime = !!field.config?.time;
    const date = value ? new Date(value as string) : undefined;

    return (
        <div className="flex gap-2">
            <Popover>
                <PopoverTrigger asChild>
                    <Button variant="outline" className="justify-start font-normal">
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {date ? format(date, 'yyyy-MM-dd') : 'Pick a date'}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={date}
                        onSelect={(d) => onChange(d ? format(d, 'yyyy-MM-dd') : null)}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
            {withTime && (
                <Input
                    type="time"
                    className="w-32"
                    value={typeof value === 'string' && value.includes('T') ? value.split('T')[1]?.slice(0, 5) : ''}
                    onChange={(e) => {
                        const day = date ? format(date, 'yyyy-MM-dd') : format(new Date(), 'yyyy-MM-dd');
                        onChange(`${day}T${e.target.value}`);
                    }}
                />
            )}
        </div>
    );
}
