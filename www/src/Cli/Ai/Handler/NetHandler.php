<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Ainet\AinetFacade;
use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class NetHandler extends AbstractHandler
{
    private ?AinetFacade $ainet = null;

    private ?string $ainetError = null;

    private bool $ainetInitialized = false;

    public function commands(): array
    {
        return ['net:status', 'image:search', 'fetch', 'image:download'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $this->initAinet();

        $ainet = $this->ainet;
        if ($ainet === null) {
            return AicliResult::fail(
                $this->ainetError ?? 'AINET init failed',
                'Fix config/ainet.config',
            );
        }

        return match ($command) {
            'net:status' => $this->doStatus($ainet),
            'image:search' => $this->doImageSearch($ainet, $args),
            'fetch' => $this->doFetch($ainet, $args),
            'image:download' => $this->doImageDownload($ainet, $args),
            default => AicliResult::fail('Unknown net command'),
        };
    }

    private function initAinet(): void
    {
        if ($this->ainetInitialized) {
            return;
        }
        $this->ainetInitialized = true;

        try {
            $this->ainet = AinetFacade::createFromConfig(
                $this->root . '/config/ainet.config',
                $this->root . '/var/ainet-cache',
            );
        } catch (\Throwable $e) {
            $this->ainetError = 'AINET init failed: ' . $e->getMessage();
        }
    }

    private function doStatus(AinetFacade $ainet): AicliResult
    {
        $status = $ainet->status();
        $ok = (bool) ($status['ok'] ?? false);

        return new AicliResult(ok: $ok, data: $status);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function doImageSearch(AinetFacade $ainet, array $args): AicliResult
    {
        $query = $this->stringArg($args, 'query');
        $count = $this->intArg($args, 'count', 5);
        $provider = isset($args['provider']) && is_string($args['provider'])
            ? $args['provider']
            : null;

        $result = $ainet->searchImages($query, $count, $provider);
        $ok = (bool) ($result['ok'] ?? false);

        return new AicliResult(ok: $ok, data: $result);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function doFetch(AinetFacade $ainet, array $args): AicliResult
    {
        $url = $this->stringArg($args, 'url');
        $type = $this->stringArg($args, 'type', 'json');

        $result = ($type === 'text')
            ? $ainet->fetchText($url)
            : $ainet->fetchJson($url);

        $ok = (bool) ($result['ok'] ?? false);

        return new AicliResult(ok: $ok, data: $result);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function doImageDownload(AinetFacade $ainet, array $args): AicliResult
    {
        $url = $this->stringArg($args, 'url');
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias', 'www');
        $category = $this->stringArg($args, 'category', 'general');
        $alt = $this->stringArg($args, 'alt');

        if (!in_array($domain, $this->allowedDomains, true)) {
            return AicliResult::fail(
                "Security violation: Domain '{$domain}' is not in the allowed list.",
                'Add domain to config/agent.config allowed_domains',
            );
        }

        $dl = $ainet->downloadImage($url, $domain, $allias, $category, $alt);
        if (($dl['ok'] ?? false) !== true) {
            return new AicliResult(
                ok: false,
                data: $dl,
                error: (string) ($dl['error'] ?? 'download failed'),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $dl['data'];
        $bytes = (string) $data['bytes'];
        $mime = (string) $data['mime'];
        $ext = (string) $data['ext'];
        $hash = (string) $data['hash'];

        $tmpDir = $this->root . '/var/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0o775, true) && !is_dir($tmpDir)) {
            return AicliResult::fail('Cannot create var/tmp', 'Check write permissions');
        }

        $tmpFile = $tmpDir . '/content_' . $hash . '_' . bin2hex(random_bytes(4)) . '.bin';
        if (@file_put_contents($tmpFile, $bytes) === false) {
            return AicliResult::fail('Cannot write tmp file', 'Check var/tmp permissions');
        }

        try {
            $cliArgs = 'content:store'
                . ' --domain=' . escapeshellarg($domain)
                . ' --allias=' . escapeshellarg($allias)
                . ' --category=' . escapeshellarg($category)
                . ' --data_file=' . escapeshellarg($tmpFile)
                . ' --mime=' . escapeshellarg($mime)
                . ' --alt=' . escapeshellarg($alt)
                . ' --source_url=' . escapeshellarg($url);
            $storeResult = $this->client->run($cliArgs);
        } finally {
            @unlink($tmpFile);
        }

        if (!$storeResult['ok']) {
            return new AicliResult(
                ok: false,
                data: ['cli_output' => $storeResult['output']],
                error: 'content:store failed',
                hint: $storeResult['hint'],
            );
        }

        $localUrl = '/content/' . $domain . '/' . $allias . '/' . $category . '/' . $hash . '.' . $ext;

        return AicliResult::ok([
            'local_url' => $localUrl,
            'path' => 'content/' . $domain . '/' . $allias . '/' . $category . '/' . $hash . '.' . $ext,
            'hash' => $hash,
            'ext' => $ext,
            'mime' => $mime,
            'width' => (int) $data['width'],
            'height' => (int) $data['height'],
            'size' => (int) $data['size'],
            'cached' => (bool) $data['cached'],
        ]);
    }
}
