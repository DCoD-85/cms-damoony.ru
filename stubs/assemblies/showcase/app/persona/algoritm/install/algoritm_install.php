<?php

declare(strict_types=1);

namespace {{namespace}}\persona;

use Damoony\Core\Http\Response;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Data\Persona\InstallationBuilder;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\UI\Page\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_install extends Page
{
    protected function content(): Node|Response|null
    {
        $token = (string) ($this->args['token'] ?? '');
        $paths = $this->context->service('paths');
        $persona = $this->context->service('persona');
        if (!$paths instanceof \Damoony\Core\Files\Paths
            || !$persona instanceof PersonaRepository
            || $token === '') {
            return Response::notFound();
        }

        $installation = $persona->installationByToken(\Damoony\Cli\Support\PersonaTokens::hash($token));
        if ($installation === []) {
            return Response::notFound();
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? $this->context->domain->domain);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $vendor = $scheme . '://' . $host;

        $downloadUrl = $vendor . '/install/' . $token . '?download=1&domain=' . urlencode((string) $installation['domain']);
        $scriptUrl = $vendor . '/install/' . $token . '?script=1';

        // Скачивание архива: только для подтверждённой установки и своего домена.
        if (($this->context->request->query['download'] ?? '') === '1') {
            $domain = SiteConfig::normalizeHost((string) ($this->context->request->query['domain'] ?? ''));
            if (($installation['status'] ?? '') !== 'active' || $domain !== (string) $installation['domain']) {
                return Response::notFound();
            }

            $builder = new InstallationBuilder($paths, $persona);
            $build = $builder->build($installation);
            $file = $builder->filePath((string) $build['file']);
            if (!is_file($file)) {
                return Response::notFound();
            }

            $persona->incrementDownload((int) $build['id']);

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename((string) $build['file']) . '"');
            header('X-Checksum-Sha256: ' . (string) $build['sha256']);
            header('Content-Length: ' . (string) filesize($file));
            readfile($file);
            exit;
        }

        // Скрипт установки.
        if (($this->context->request->query['script'] ?? '') === '1') {
            return new Response($this->script($vendor, $token), 200, [
                'Content-Type' => 'text/x-shellscript; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="install.sh"',
            ]);
        }

        // Claim (POST от install.sh): проверка домена и выдача ссылки на сборку.
        if ($this->isPost()) {
            $domain = SiteConfig::normalizeHost((string) $this->context->request->post('domain'));

            if (($installation['status'] ?? '') !== 'active') {
                return Response::json(['ok' => false, 'error' => 'installation not approved'], 403);
            }
            if ($domain === '' || $domain !== (string) $installation['domain']) {
                return Response::json(['ok' => false, 'error' => 'domain mismatch'], 403);
            }

            $persona->markClaimed((int) $installation['id']);
            $builder = new InstallationBuilder($paths, $persona);
            $build = $builder->build($installation);

            return Response::json([
                'ok' => true,
                'domain' => $domain,
                'assembly' => (string) $installation['assembly'],
                'langs' => array_values(array_filter(explode(',', (string) $installation['langs']))),
                'download_url' => $downloadUrl,
                'script_url' => $scriptUrl,
                'sha256' => (string) $build['sha256'],
                'size' => (int) $build['size'],
                'expires_at' => (string) $build['expires_at'],
            ]);
        }

        $wrap = Builder::div('persona-box');
        $wrap->append(Builder::h(1)->content('Installation'));
        $wrap->append(Builder::p()->content('Domain: ' . (string) $installation['domain']));
        $wrap->append(Builder::p()->content('Status: ' . (string) $installation['status']));
        $wrap->append(Builder::p()->content('Assembly: ' . (string) $installation['assembly']));
        $wrap->append(Builder::p()->content('Langs: ' . (string) $installation['langs']));
        $wrap->append(Builder::p()->content('Script: ' . $scriptUrl));
        $wrap->append(Builder::p()->content('Archive: ' . $downloadUrl));

        return $wrap;
    }

    private function script(string $vendor, string $token): string
    {
        return <<<SH
#!/usr/bin/env bash
set -euo pipefail

VENDOR="{$vendor}"
TOKEN="{$token}"
DOMAIN="\${1:-}"

if [ -z "\$DOMAIN" ]; then
  echo "usage: bash install.sh your-domain.ru"
  exit 1
fi

for cmd in php curl unzip mysql; do
  command -v "\$cmd" >/dev/null 2>&1 || { echo "missing: \$cmd"; exit 1; }
done

echo "[1/5] claim \$DOMAIN"
CLAIM=\$(curl -sS -X POST "\$VENDOR/install/\$TOKEN" --data-urlencode "domain=\$DOMAIN")
echo "\$CLAIM" | grep -q '"ok":true' || { echo "claim failed: \$CLAIM"; exit 1; }

URL=\$(echo "\$CLAIM" | php -r '\$j=json_decode(stream_get_contents(STDIN),true); echo \$j["download_url"] ?? "";')
SHA=\$(echo "\$CLAIM" | php -r '\$j=json_decode(stream_get_contents(STDIN),true); echo \$j["sha256"] ?? "";')
[ -n "\$URL" ] || { echo "no download url"; exit 1; }

echo "[2/5] download"
TMP=\$(mktemp -d)
curl -sSL "\$URL" -o "\$TMP/core.zip"
if [ -n "\$SHA" ]; then
  echo "\$SHA  \$TMP/core.zip" | sha256sum -c - >/dev/null
fi

echo "[3/5] unpack"
unzip -oq "\$TMP/core.zip" -d .
rm -rf "\$TMP"

echo "[4/5] database config"
read -p "MySQL root [root]: " DB_ROOT; DB_ROOT=\${DB_ROOT:-root}
read -sp "MySQL root password: " DB_ROOT_PASS; echo
DB_NAME="damoony_\$(echo "\$DOMAIN" | tr . _ | cut -c 1-30)"
DB_USER="\$DB_NAME"
DB_PASS=\$(php -r 'echo bin2hex(random_bytes(8));')
MYSQL_PWD="\$DB_ROOT_PASS" mysql -u "\$DB_ROOT" -e "
  CREATE DATABASE IF NOT EXISTS \$DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '\$DB_USER'@'localhost' IDENTIFIED BY '\$DB_PASS';
  ALTER USER '\$DB_USER'@'localhost' IDENTIFIED BY '\$DB_PASS';
  GRANT ALL PRIVILEGES ON \$DB_NAME.* TO '\$DB_USER'@'localhost';
  FLUSH PRIVILEGES;"

php -r '
\$cfg = ["db" => ["driver" => "mysql", "host" => "localhost", "dbname" => \$argv[1], "user" => \$argv[2], "password" => \$argv[3]], "security" => ["allow_php_blocks" => false]];
file_put_contents("config/engine.config", json_encode(\$cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "\$DB_NAME" "\$DB_USER" "\$DB_PASS"

echo "[5/5] migrate & check"
php bin/cli.php cli:init --name=installer
php bin/cli.php db:migrate --force
php bin/cli.php health:check

mkdir -p content var
chown -R www-data:www-data app content var www/media www 2>/dev/null || true
chown root:www-data config/engine.config config/site.config 2>/dev/null || true
chmod 640 config/engine.config config/site.config 2>/dev/null || true

echo "ready: https://\$DOMAIN/"
SH;
    }
}
