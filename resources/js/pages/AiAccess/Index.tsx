import * as React from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, LoaderCircle, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { InputError } from '@/components/app/input-error';
import { adminUrl } from '@/lib/route';
import type { SharedProps } from '@/types';

interface Token { id: number; name: string; last_used_at: string | null; created_at: string | null }

interface Props {
    tokens: Token[];
    endpoint: string;
    enabled: boolean;
    newToken?: string | null;
    canWriteTemplates: boolean;
}

function copy(text: string) {
    navigator.clipboard.writeText(text).then(() => toast.success('Copied.'), () => toast.error('Could not copy.'));
}

function Snippet({ label, code }: { label: string; code: string }) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{label}</span>
                <Button type="button" variant="ghost" size="sm" onClick={() => copy(code)}><Copy /> Copy</Button>
            </div>
            <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2 text-xs"><code>{code}</code></pre>
        </div>
    );
}

export default function AiAccess({ tokens, endpoint, enabled, newToken, canWriteTemplates }: Props) {
    const { adminPath } = usePage<SharedProps>().props;
    const form = useForm({ name: '' });
    const token = newToken ?? 'YOUR_TOKEN';
    // A new token is shown once: in a popup where the user is, not only at the top of the page.
    const [showToken, setShowToken] = React.useState(Boolean(newToken));
    React.useEffect(() => setShowToken(Boolean(newToken)), [newToken]);

    const create = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(adminUrl('ai-access', adminPath), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const revoke = (t: Token) =>
        window.confirm(`Revoke "${t.name}"? Agents using it can no longer connect.`)
        && router.delete(adminUrl(`ai-access/${t.id}`, adminPath), { preserveScroll: true });

    const jsonConfig = JSON.stringify({ mcpServers: { sunrice: { type: 'http', url: endpoint, headers: { Authorization: `Bearer ${token}` } } } }, null, 2);

    return (
        <div className="flex max-w-4xl flex-col gap-6">
            <Dialog open={showToken} onOpenChange={setShowToken}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2"><KeyRound className="size-4" /> Your new token</DialogTitle>
                        <DialogDescription>Copy it now: for safety it is never shown again.</DialogDescription>
                    </DialogHeader>
                    {newToken && (
                        <div className="grid gap-4">
                            <div className="flex gap-2">
                                <Input readOnly value={newToken} className="font-mono text-sm" autoFocus onFocus={(e) => e.target.select()} aria-label="New token" />
                                <Button type="button" onClick={() => copy(newToken)}><Copy /> Copy</Button>
                            </div>
                            <Snippet label="Claude Code" code={`claude mcp add --transport http sunrice ${endpoint} --header "Authorization: Bearer ${newToken}"`} />
                            <p className="text-xs text-muted-foreground">The connection snippets on this page include it too, until you leave the page.</p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" onClick={() => setShowToken(false)}>I&apos;ve copied it</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div>
                <h1 className="sunrice-page-title">AI access</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Connect an AI agent (Claude, ChatGPT, Cursor…) to this site through MCP. It can read and manage content, structure,
                    menus, globals, media, forms, SEO, translations{canWriteTemplates ? ', templates' : ''} and maintenance commands, always with
                    your permissions.
                </p>
            </div>
            {!enabled && (
                <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    The MCP server is turned off on this site (SUNRICE_MCP_ENABLED).
                </p>
            )}

            {newToken && (
                <Card className="border-primary">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><KeyRound className="size-4" /> Your new token</CardTitle>
                        <CardDescription>Copy it now: for safety it is never shown again. The snippets below already include it.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex gap-2">
                        <Input readOnly value={newToken} className="font-mono text-sm" onFocus={(e) => e.target.select()} />
                        <Button type="button" onClick={() => copy(newToken)}><Copy /> Copy</Button>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Connect an agent</CardTitle>
                    <CardDescription>The server address and the header carrying your token. Most agents accept one of these.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Snippet label="Server URL" code={endpoint} />
                    <Snippet label="Claude Code" code={`claude mcp add --transport http sunrice ${endpoint} --header "Authorization: Bearer ${token}"`} />
                    <Snippet label="Claude Desktop, Cursor, VS Code… (JSON config)" code={jsonConfig} />
                    <p className="text-xs text-muted-foreground">
                        For agents running on the server itself: <code>php artisan mcp:start sunrice</code> with <code>SUNRICE_MCP_USER</code> set to
                        the email of the user it acts as. See Docs → AI agents.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Your tokens</CardTitle>
                    <CardDescription>One per agent or device, so you can revoke one without the others.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <form onSubmit={create} className="flex flex-wrap items-end gap-2">
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="token-name">Name</Label>
                            <Input id="token-name" placeholder="e.g. Claude on my laptop" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </div>
                        <Button type="submit" disabled={form.processing || form.data.name.trim() === ''}>
                            {form.processing && <LoaderCircle className="animate-spin" />} Create token
                        </Button>
                    </form>
                    <InputError message={form.errors.name} />
                    {tokens.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Last used</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tokens.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell className="font-medium">{t.name}</TableCell>
                                        <TableCell>{t.last_used_at ?? 'Never'}</TableCell>
                                        <TableCell>{t.created_at}</TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="icon" className="text-destructive" aria-label={`Revoke ${t.name}`} onClick={() => revoke(t)}>
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-sm text-muted-foreground">No tokens yet.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
