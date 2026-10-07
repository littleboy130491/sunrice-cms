# AI agents (MCP)

Sunrice includes an [MCP](https://modelcontextprotocol.io) server, so AI
agents such as Claude, ChatGPT, Cursor or VS Code can manage the site:
content, structure, menus, globals, media, forms, SEO, translations,
templates and maintenance commands.

## How it works

The server is part of the site. An agent connects to it, receives
instructions about how Sunrice is organised and a list of **tools**. Each
tool has a description and an input schema. The agent decides which tools
to call, for example `get_site_info`, then `get_entry`, then `update_entry`.

Every tool runs Sunrice's own actions, so the agent gets the same
validation, drafts, revisions, redirects, cache clearing and permission
checks as a person using the admin. The agent acts as the user who owns
the access token and can do exactly what that user can.

## Connecting an agent

1. Give the user's role the **Connect AI agents** permission
   (`sunrice.ai-access`). Administrators have it.
2. In the admin, open **AI access**, create a token and copy it. It is
   shown once. Or run
   `php artisan sunrice:mcp-token admin@example.com --name="Claude"`.
3. Add the server to the agent. The AI access page shows ready-made
   settings:

   ```bash
   # Claude Code
   claude mcp add --transport http sunrice https://example.com/mcp \
     --header "Authorization: Bearer sr_…"
   ```

   ```json
   {
     "mcpServers": {
       "sunrice": {
         "type": "http",
         "url": "https://example.com/mcp",
         "headers": { "Authorization": "Bearer sr_…" }
       }
     }
   }
   ```

Revoke a token on the same page (or with `--revoke=ID`) and the agent
can no longer connect.

An agent running on the server itself can use stdio instead of HTTP:
`php artisan mcp:start sunrice`, with `SUNRICE_MCP_USER` set to the
email of the user it acts as.

## Tools

| Area | Tools |
| --- | --- |
| Site | `get_site_info` (start here), `update_site_settings`, `read_docs` |
| Entries | `list_entries`, `get_entry`, `create_entry`, `update_entry` (drafts; `publish: true` puts changes live; `locale` writes a translation), `manage_entry` (unpublish, trash, restore, delete, duplicate, set_homepage) |
| Structure | `save_collection`, `get_blueprint` (also lists field types and their options), `save_blueprint` (blueprints and fieldsets), `save_taxonomy`, `delete_structure` |
| Terms | `list_terms`, `save_term` (create, update, trash, restore) |
| Globals and menus | `get_global`, `save_global`, `get_menu`, `save_menu` |
| Media | `list_assets` (e.g. images missing alt text), `save_asset` (upload from a URL or base64, edit title/alt/caption) |
| Forms | `get_form` (with submissions), `save_form` |
| SEO | `seo_audit`: meta titles and descriptions (length, duplicates, missing), share images, noindex, thin content, missing translations, images without alt |
| Languages | `translate_entry` (machine translation with the configured service; the agent can also translate and save with `update_entry`) |
| Templates | `get_template_guide`, `read_template`, `write_template`, `render_page` |
| Commands | `run_command` (only the commands in `sunrice.mcp.commands`) |

## Templates

`get_template_guide` gives the agent everything it needs to design pages:

- how a template is chosen (priority and fallbacks)
- layouts and partials
- the variables of each page type
- reading fields and querying entries
- menus, globals, forms, SEO and languages
- which template every collection and taxonomy uses right now

The agent can read the starter templates as examples, then write templates
to `resources/views/sunrice/` and CSS/JS to `public/sunrice-theme/`, and
check the result with `render_page`.

Blade templates run PHP on the server. So writing them is limited to
**super admins**, and you can turn it off with `SUNRICE_MCP_TEMPLATES=false`.
Each change is checked for syntax errors first, and the previous version
is kept in `storage/app/sunrice/template-backups/`.

## Configuration

```php
// config/sunrice.php
'mcp' => [
    'enabled' => env('SUNRICE_MCP_ENABLED', true),
    'path' => 'mcp',                              // the endpoint: POST /mcp
    'local_user' => env('SUNRICE_MCP_USER'),      // for `mcp:start sunrice`
    'templates' => env('SUNRICE_MCP_TEMPLATES', true),
    'commands' => ['sunrice:publish-scheduled', 'sunrice:translate', 'cache:clear', /* … */],
],
```

`run_command` also needs the **Let AI agents run maintenance commands**
permission (`sunrice.run-commands`). Requests are limited to 300 a
minute per token.

## Safety

- Tokens are stored hashed and can be revoked at any time.
- Give agents a user with only the permissions they need, e.g. an Editor
  for content work.
- Agents are told to ask before deleting or publishing large changes,
  but they can still make mistakes. Entry changes keep revisions, and
  deleted collections and taxonomies keep their content until purged.
