<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Files\Paths;
use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Scheme\SchemeLoader;
use Damoony\UI\Scheme\SchemeRenderer;

/**
 * Базовый класс для страниц со схемой.
 * Разработчику достаточно реализовать метод content().
 * Схема и обертка (header/footer) подключатся автоматически.
 */
abstract class PageWithScheme extends Page
{
    /**
     * Возвращает контент, который будет помещен в слот 'center'.
     */
    abstract protected function content(): Node;

    final public function default(): Node|Response
    {
        // 1. Получаем контент от разработчика
        $node = $this->content();

        // 2. Кладем его в слот 'center' по умолчанию
        $this->setContent('center', $node);

        // 3. Загружаем и рендерим схему
        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $loader = new SchemeLoader($paths);
        $manifest = $loader->load($this->context->domain, $this->context->domain->scheme);
        $renderer = new SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
