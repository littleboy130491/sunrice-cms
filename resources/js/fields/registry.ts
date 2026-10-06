import type { ComponentType } from 'react';
import TextField from './TextField';
import TextareaField from './TextareaField';
import RichTextField from './RichTextField';
import NumberField from './NumberField';
import ToggleField from './ToggleField';
import SelectField from './SelectField';
import DateField from './DateField';
import LinkField from './LinkField';
import AssetField from './AssetField';
import EntriesField from './EntriesField';
import TermsField from './TermsField';
import GroupField from './GroupField';
import RepeaterField from './RepeaterField';
import FlexibleField from './FlexibleField';
import FieldsetField from './FieldsetField';
import FileField from './FileField';
import BelongsToField from './BelongsToField';
import type { AdminField, Json } from '@/types';

export interface ContainerFieldProps {
    field: AdminField;
    value: unknown;
    errors?: Record<string, string>;
    pathPrefix?: string;
    onChange: (value: unknown) => void;
}

export const fieldComponents: Record<string, ComponentType<ContainerFieldProps>> = {
    text: TextField,
    textarea: TextareaField,
    rich_text: RichTextField,
    number: NumberField,
    toggle: ToggleField,
    select: SelectField,
    date: DateField,
    link: LinkField,
    asset: AssetField,
    entries: EntriesField,
    terms: TermsField,
    group: GroupField,
    repeater: RepeaterField,
    flexible: FlexibleField,
    fieldset: FieldsetField,
    file: FileField,
    belongs_to: BelongsToField,
    belongs_to_many: BelongsToField,
};
