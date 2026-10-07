<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Actions\Assets\UpdateAssetMeta;
use Sunrice\Actions\Assets\UploadAsset;
use Sunrice\Mcp\Presenter;
use Sunrice\Models\Asset;

#[Description('Upload a file to the media library from a public URL or base64 content (with a filename), or update an asset\'s title, alt text and caption (id). Returns the asset with its id and URL.')]
class SaveAsset extends SunriceTool
{
    protected string $name = 'save_asset';

    public function handle(Request $request): Response
    {
        $args = $request->validate([
            'id' => ['nullable', 'integer'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'content_base64' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);
        $meta = array_intersect_key($request->all(), array_flip(['title', 'alt', 'caption']));

        if (! empty($args['id'])) {
            $asset = Asset::query()->find($args['id']);
            if ($asset === null) {
                return $this->notFound('Asset');
            }
            $this->authorize('update', $asset);

            return $this->json(['saved' => true] + Presenter::asset(app(UpdateAssetMeta::class)->handle($asset, $meta)));
        }

        $this->authorize('create', Asset::class);
        if (empty($args['url']) && empty($args['content_base64'])) {
            return Response::error('Send url or content_base64 to upload, or id to update an asset.');
        }

        if (! empty($args['url'])) {
            if (! static::isPublicUrl($args['url'])) {
                return Response::error('Only files on public web addresses can be fetched.');
            }
            $download = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($args['url']);
            if (! $download->successful()) {
                return Response::error('Could not download the file (HTTP '.$download->status().').');
            }
            $contents = $download->body();
            $filename = $args['filename'] ?? (basename((string) parse_url($args['url'], PHP_URL_PATH)) ?: 'file');
        } else {
            $contents = base64_decode((string) $args['content_base64'], true);
            if ($contents === false) {
                return Response::error('content_base64 is not valid base64.');
            }
            $filename = $args['filename'] ?? 'file';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sunrice-mcp-');
        file_put_contents($tmp, $contents);
        try {
            $asset = app(UploadAsset::class)->handle(
                new UploadedFile($tmp, $filename, null, null, true),
                $meta + ['uploaded_by' => $request->user()?->getAuthIdentifier()],
            );
        } finally {
            @unlink($tmp);
        }

        return $this->json(['uploaded' => true] + Presenter::asset($asset));
    }

    /** Not the server itself or its private network. */
    public static function isPublicUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $ips !== [] && collect($ips)->every(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Asset to update (no upload).'),
            'url' => $schema->string()->description('Public file URL to upload.'),
            'content_base64' => $schema->string()->description('File content to upload, base64.'),
            'filename' => $schema->string()->description('File name with extension (for base64; optional for url).'),
            'title' => $schema->string(),
            'alt' => $schema->string()->description('Alt text for images: describe the image.'),
            'caption' => $schema->string(),
        ];
    }
}
