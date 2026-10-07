import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { registerField } from '@/fields/registry';
import { fetchJson } from '@/lib/fetch-json';

/**
 * window.Sunrice: the admin's extension API for site-provided scripts
 * (config `sunrice.admin.scripts`, or Sunrice::registerAdminScript()).
 *
 * Scripts use this React instance (a second copy of React would break
 * hooks) and register field components:
 *
 *     Sunrice.registerField('rating', ({ field, value, onChange, error }) => …);
 *
 * Keep this surface small and stable: it is a public API.
 */
const api = {
    version: 1,
    React,
    ReactDOM,
    /** Admin control for a field type: receives { field, value, onChange, errors, pathPrefix }. */
    registerField,
    /** The admin's own UI primitives, so custom fields match its look. */
    ui: { Badge, Button, Checkbox, Input, Label, Switch, Textarea },
    /** fetch() returning JSON, with the admin's CSRF token and error handling. */
    fetchJson,
    /** Toast notifications: Sunrice.toast.success('Saved'), .error(…). */
    toast,
};

export type SunriceApi = typeof api;

declare global {
    interface Window {
        Sunrice: SunriceApi;
    }
}

export function installPluginApi(): void {
    window.Sunrice = api;
}
