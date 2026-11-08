-- 006_protonews_articles.sql
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_published (published_at),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (id, name) VALUES 
    (1, 'Технологии'),
    (2, 'Бизнес'),
    (3, 'Наука')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO articles (category_id, title, content) VALUES
    (1, 'Наш движок переходит на PHP 8.5', 'Мы успешно обновили ядро до последней версии PHP. Теперь проект работает на PHPStan Level 8 без единой ошибки.'),
    (1, 'AJAX-админка без React и Vue', 'Мы сделали SPA-подобную навигацию на чистом PHP и JavaScript. Скорость загрузки страниц - 30-50 мс.'),
    (2, 'Первая версия DataProvider готова', 'Наш универсальный DataProvider умеет делать CRUD для любой таблицы. Без ORM-магии, только чистые PDO-запросы.'),
    (3, 'Планы на тестирование', 'Будем тестировать движок на реальном проекте protonews.damoony.ru, чтобы найти и исправить все слабые места.')
