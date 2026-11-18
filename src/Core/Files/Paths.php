<?php

declare(strict_types=1);

namespace Damoony\Core\Files;

final class Paths
{
    public function __construct(private readonly string $root)
    {
    }

    public function root(): string
    {
        return $this->root;
    }

    public function siteConfig(): string
    {
        return $this->root . '/config/site.config';
    }

    public function engineConfig(): string
    {
        return $this->root . '/config/engine.config';
    }

    public function appRoot(): string
    {
        return $this->root . '/app';
    }

    public function lang(): string
    {
        return $this->root . '/lang';
    }

    public function sessionDir(string $tabId, string $real): string
    {
        return $this->appRoot() . '/.sess_' . $tabId . '.' . $real;
    }

    public function domain(string $real): string
    {
        return $this->appRoot() . '/' . $real;
    }

    public function allias(string $real, string $allias): string
    {
        return $this->domain($real) . '/' . $allias;
    }

    public function algoritm(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/algoritm';
    }

    /**
     * Инфраструктура (skins, admin, shared). Отдаётся статикой через www/media/.
     */
    public function mediaRoot(): string
    {
        return $this->root . '/www/media';
    }

    public function media(string $real, string $allias): string
    {
        return $this->mediaRoot() . '/' . $real . '/' . $allias;
    }

    /**
     * AI/user-контент (картинки страниц). Хранится ВНЕ webroot — только через www/content.php.
     */
    public function contentRoot(): string
    {
        return $this->root . '/content';
    }

    public function content(string $real, string $allias): string
    {
        return $this->contentRoot() . '/' . $real . '/' . $allias;
    }

    // ============================================================
    // v4 structure (per-domain)
    // ============================================================

    public function isV4(string $real): bool
    {
        return true; // Структура v4 теперь единственная и безальтернативная
    }

    /**
     * i18n global (domain-level). v4: .i18nglobal
     */
    public function i18nGlobal(string $real): string
    {
        return $this->domain($real) . '/.i18nglobal';
    }

    /**
     * i18n local (alias-level). v4: {a}/.i18nlocal
     */
    public function i18nLocal(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/.i18nlocal';
    }

    /**
     * DB global. v3: .db ; v4: .dbglobal (fallback .db if exists).
     */
    public function dbGlobal(string $real): string
    {
        $v4 = $this->domain($real) . '/.dbglobal';
        if ($this->isV4($real)) {
            return $v4;
        }
        $v3 = $this->domain($real) . '/.db';
        if (!is_dir($v3) && is_dir($v4)) {
            return $v4;
        }
        return $v3;
    }

    /**
     * DB local (alias-level). v3: не используется ; v4: {a}/.dblocal.
     */
    public function dbLocal(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/.dblocal';
    }

    /**
     * Blocks global (exists in v3 and v4 — same path).
     */
    public function blocksGlobal(string $real): string
    {
        return $this->domain($real) . '/.blocksglobal';
    }

    /**
     * Blocks local (alias-level, new in v4).
     */
    public function blocksLocal(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/.blockslocal';
    }

    /**
     * Schemes global (exists in v3 and v4 — same path).
     */
    public function schemesGlobal(string $real): string
    {
        return $this->domain($real) . '/.schemesglobal';
    }

    /**
     * Schemes local (alias-level, new in v4).
     */
    public function schemesLocal(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/.schemeslocal';
    }

    /**
     * Shared media (cross-domain): www/media/shared/{css,scripts,images}/.
     */
    public function mediaShared(): string
    {
        return $this->mediaRoot() . '/shared';
    }

    /**
     * Alias media root: www/media/{real}/{allias}/ (skin/, css/, scripts/, images/).
     */
    public function mediaAlias(string $real, string $allias): string
    {
        return $this->media($real, $allias);
    }
}
