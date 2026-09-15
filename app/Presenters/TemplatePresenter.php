<?php

declare(strict_types=1);

namespace App\Presenters;

use Arris\Presenter\Headers;
use Arris\Presenter\Template;

/**
 * Презентер HTML-страниц поверх Arris\Presenter\Template.
 *
 * Соглашение: payload содержит ключ 'template' (имя .tpl) и любые
 * переменные шаблона. Необязательно задаёт HTTP-статус и заголовки.
 */
final class TemplatePresenter
{
    public function __construct(private Template $template)
    {
    }

    /**
     * Глобальная переменная шаблона (видна во всех страницах текущего запроса).
     */
    public function assign(string $key, mixed $value): void
    {
        $this->template->assign($key, $value);
    }

    public function present(array $payload, int $statusCode = 200): void
    {
        $this->template->assign('year', date('Y'));

        foreach ($payload as $key => $value) {
            if ($key === 'template') {
                continue;
            }

            $this->template->assign((string)$key, $value);
        }

        if (isset($payload['template'])) {
            $this->template->setTemplate((string)$payload['template']);
        }

        if ($statusCode !== 200) {
            $this->template->addHeader(Headers::_, code: $statusCode);
        }

        echo $this->template->render(send_headers: true);
    }
}