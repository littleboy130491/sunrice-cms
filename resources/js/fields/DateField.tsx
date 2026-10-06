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
    // Stored as 'YYYY-MM-DD' or 'YYYY-MM-DDTHH:mm[:ss]', a wall-clock value.
    // Read the parts directly: new Date('YYYY-MM-DD') is UTC midnight and
    // shows as the previous day west of Greenwich.
    const raw = typeof value === 'string' ? value : '';
    const day = /^\d{4}-\d{2}-\d{2}/.test(raw) ? raw.slice(0, 10) : '';
    const time = raw.length > 10 ? raw.slice(11, 16) : '';
    const date = day ? new Date(Number(day.slice(0, 4)), Number(day.slice(5, 7)) - 1, Number(day.slice(8, 10))) : undefined;
    const emit = (d: string, t: string) => onChange(withTime && t ? `${d}T${t}` : d);

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
                        onSelect={(d) => (d ? emit(format(d, 'yyyy-MM-dd'), time) : onChange(null))}
                        autoFocus
                    />
                </PopoverContent>
            </Popover>
            {withTime && (
                <Input
                    type="time"
                    className="w-32"
                    value={time}
                    onChange={(e) => emit(day || format(new Date(), 'yyyy-MM-dd'), e.target.value)}
                />
            )}
        </div>
    );
}
