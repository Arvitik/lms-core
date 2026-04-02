-- ============================================================
-- Таблица уведомлений для модуля уведомлений
-- Применить: через phpMyAdmin (http://localhost:8081)
--            или: docker exec lms_app php artisan migrate
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(10) UNSIGNED NOT NULL,
  `type`       VARCHAR(50)      NOT NULL,
  `title`      VARCHAR(255)     NOT NULL,
  `body`       TEXT             NOT NULL,
  `data`       TEXT             NULL,
  `is_read`    TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP        NULL,
  `updated_at` TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_is_read_index` (`user_id`, `is_read`),
  CONSTRAINT `notifications_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
