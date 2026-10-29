-- ------------- MIGRATION 1 -------------- --
-- ------------- MIGRATION 1 -------------- --

CREATE TABLE IF NOT EXISTS books (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    category ENUM('textbook', 'literature') NOT NULL DEFAULT 'literature',
    author VARCHAR(190) NULL,
    cover_path VARCHAR(500) NULL,
    grade TINYINT UNSIGNED NULL,
    pdf_url VARCHAR(2048) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_grade_title (grade, title),
    KEY idx_books_category_grade_title (category, grade, title),
    CONSTRAINT chk_books_grade CHECK (grade IS NULL OR grade BETWEEN 1 AND 11)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------- MIGRATION 2 -------------- --
-- ------------- MIGRATION 2 -------------- --

ALTER TABLE books
    ADD COLUMN year SMALLINT UNSIGNED NULL AFTER title;
INSERT INTO books (title, year, author, grade, category, pdf_url) VALUES
    ('Українська література', 2022, 'О. Калинич', 5, 'textbook', 'https://drive.google.com/file/d/1G72EGSr-YE-voKmFoUJ5Pcc01t7fQgpP'),
    ('Українська мова', 2022, 'А. Онатій', 5, 'textbook', 'https://files.pidruchnyk.com.ua/uploads/book/5_ukrmova_onatiy_2022.pdf'),
    ('Пізнаємо природу', 2025, 'Д. Біда', 5, 'textbook', 'https://drive.google.com/file/d/1xEMrmFZgjiE7ELU7EdRldycVAipLY8MR'),
    ('Зарубіжна література', 2023, 'О. Ніколенко', 5, 'textbook', 'https://drive.google.com/file/d/14lsfeR5zXjl2V30xlhl2LlHgEi4sPHiI'),
    ('Вступ до історії України та громадянської освіти', NULL, 'І. Щупак', 5, 'textbook', 'https://drive.google.com/file/d/19WlA_Loec43MEYiJXTx3MXqNFcPa00I1'),
    ('Математика', 2022, 'Д. Біос', 5, 'textbook', 'https://drive.google.com/file/d/1nSp9u1Bm_XZ4Z-ny3ZhnQnNMO5I2F9wb'),

    ('Географія', 2024, 'Т. Гільберг', 6, 'textbook', 'https://drive.google.com/file/d/1XHmBouAYAbv1kgQ6wbmI3ktRq0pIp3dQ'),
    ('Пізнаємо природу', 2023, 'Т. Гільберг', 6, 'textbook', 'https://www.geneza.ua/sites/default/files/ebooks/%D0%93%D1%96%D0%BB%D1%8C%D0%B1%D0%B5%D1%80%D0%B3_%D0%9F%D1%96%D0%B7%D0%BD_%D0%9F%D1%80%D0%B8%D1%80_%D0%9F_6.pdf'),
    ('Зарубіжна література', 2023, 'О. Ніколенко', 6, 'textbook', 'https://drive.google.com/file/d/1aP3hUnoHAqt8EG83juIo7TofBUZYvCNM'),
    ('Українська література', 2023, 'О. Калинич', 6, 'textbook', 'https://drive.google.com/file/d/1kH4xeTWddioh3ZJ886RWoTiLUfpyqKLk'),
    ('Історія України. Всесвітня історія', 2023, 'І. Щупак', 6, 'textbook', 'https://drive.google.com/file/d/1aKATCxhnguwK58xc7rtqddyjKT7qP-X9'),
    ('Українська мова', NULL, 'А. Онатій', 6, 'textbook', 'https://drive.google.com/file/d/1vlZ-d9sowgLw8Z_djPIrqxRoXTrAR7V7'),

    ('Історія України', 2024, 'В. Власов', 7, 'textbook', 'https://drive.google.com/file/d/1GjDHDy3fD79JMHYKMbALEQIpqYKZSmJU'),
    ('Література (інтегрований курс). Частина 1', 2024, 'О. Ніколенко', 7, 'textbook', 'https://files.pidruchnyk.com.ua/uploads/book/7-klas-literatura-nikolenko-2024-1.pdf'),
    ('Література (інтегрований курс). Частина 2', 2024, 'О. Ніколенко', 7, 'textbook', 'https://files.pidruchnyk.com.ua/uploads/book/7-klas-literatura-nikolenko-2024-2.pdf'),
    ('Всесвітня історія', 2024, 'О. Гісем', 7, 'textbook', 'https://drive.google.com/file/d/1Bbn9kJErU3S57R3_UqCDm7ZgEjd2O-FH'),
    ('Українська мова', 2024, 'А. Онатій', 7, 'textbook', 'https://drive.google.com/file/d/1v5aLHuUFrvTTfy2mrcIV9hKO-HzZoQly/view'),
    ('Фізика', 2024, 'С. Довгий', 7, 'textbook', 'https://drive.google.com/file/d/18VgPghEh3DY97waOTsTvbWkAa9zmdibr'),
    ('Біологія', 2024, 'К. Задорожний', 7, 'textbook', 'https://file.4book.org/images/shcoolbook_ua/7/7_b_z_2024.pdf'),
    ('Хімія', 2024, 'О. Григорович', 7, 'textbook', 'https://drive.google.com/file/d/1QywhDmt0J4TQxkMoNpchXPZH2AfKBoWG'),
    ('Географія', 2024, 'Т. Гільберг', 7, 'textbook', 'https://drive.google.com/file/d/1CIQ1iGC4B6P0OV5BosGCdIXvgSoDshgl'),
    ('Алгебра', 2024, 'О. Істер', 7, 'textbook', 'https://drive.google.com/file/d/1_3DIkxqbolB7NGACXDW2Ju_rrKfdfEXt'),
    ('Геометрія', 2024, 'О. Істер', 7, 'textbook', 'https://drive.google.com/file/d/1LEtTcZ53Ea04L2vnF94Ap92QAmu-xk4k'),

    ('Фізика', 2025, 'С. Довгий', 8, 'textbook', 'https://drive.google.com/file/d/1CuZE80vcGVUkGCniw-m4o5Hf0g-NxCRA'),
    ('Географія', 2025, 'Т. Гільберг', 8, 'textbook', 'https://drive.google.com/file/d/1nggvJEFGlePyuxdDry-5zdP0_otfOBhs'),
    ('Мистецтво', 2025, 'Л. Кондратова', 8, 'textbook', 'https://drive.google.com/file/d/1WfomwcmUdTbFHdwen8oVJZ2FB2e2zXQ-'),
    ('Біологія', 2025, 'К. Задорожний', 8, 'textbook', 'https://drive.google.com/file/d/1bQc7xKfzQTmVmXJb_q8LiTjJmM8I3fVP'),
    ('Хімія', 2025, 'О. Григорович', 8, 'textbook', 'https://drive.google.com/file/d/1C_dZMBqvNZE4uYoSp4G9KC6cN9KjYAj0'),
    ('Всесвітня історія', 2025, 'О. Гісем', 8, 'textbook', 'https://drive.google.com/file/d/1n2E2CddNlMSibnUrCgA-FnHc3ICCxxyN'),
    ('Історія України', 2025, 'Акім Галімов', 8, 'textbook', 'https://drive.google.com/file/d/1WJD1m7OkX2yCVxu7opZGtFAa6xcV5kSu'),
    ('Здоров’я, безпека та добробут', 2025, 'Л. Задорожна', 8, 'textbook', 'https://drive.google.com/file/d/1z2dO_N2ua6c39G_H3eSHfKejhkkUoFtD'),
    ('Українська мова', 2025, 'А. Онатій', 8, 'textbook', 'https://drive.google.com/file/d/1zQ5E3tdkwZF_7UE5Kq6ulq6V3RKBIsCe'),
    ('Література (інтегрований курс). Частина 2', 2025, 'О. Ніколенко', 8, 'textbook', 'https://drive.google.com/file/d/1YaE03Xw3dj40aOpSFLe9dd25_c5Xnomg'),
    ('Геометрія', 2025, 'О. Істер', 8, 'textbook', 'https://drive.google.com/file/d/1MuhwpuWiAN1JjbpTYmKRe5qL6UKL-3Sd'),
    ('Алгебра', 2025, 'О. Істер', 8, 'textbook', 'https://www.geneza.ua/sites/default/files/ebooks/%D0%86%D1%81%D1%82%D0%B5%D1%80_%D0%90%D0%BB%D0%B3%D0%B5%D0%B1%D1%80%D0%B0_%D0%9F_8_%28064-24%29_S.pdf'),

    ('Мистецтво', 2025, 'Л. Кондратова', 9, 'textbook', 'https://drive.google.com/file/d/1gxAxf9GNtooEzvVxaYaTNfbi6MXStYLg'),
    ('Географія', 2025, 'Т. Гільберг', 9, 'textbook', 'https://drive.google.com/file/d/1OPF44Nm2mysEKEzV5CFFgIFyUQPyj8tZ'),
    ('Алгебра', 2026, 'О. Істер', 9, 'textbook', 'https://drive.google.com/file/d/1MDHZWkiY5MsSuziTyb0xnATu0OM5wita'),
    ('Геометрія', NULL, 'О. Істер', 9, 'textbook', 'https://drive.google.com/file/d/1R0xMQIgyCNtNgmz52P2dOtivf3pkM9tU'),
    ('Всесвітня історія', 2026, 'О. Гісем', 9, 'textbook', 'https://drive.google.com/file/d/18dgdlWeZer0PGA7a-TMCupqSR6IzQMHp'),
    ('Правознавство', 2026, 'Т. Філіпенко', 9, 'textbook', 'https://drive.google.com/file/d/15OqJTbqDf6crAb6YgsqaDxFyRh9NZEyO'),
    ('Історія України', 2026, 'В. Власов', 9, 'textbook', 'https://drive.google.com/file/d/1QzugtzCz4rcdcxMhFpoCUP214ReMuEJQ');

-- ------------- MIGRATION 3 -------------- --

ALTER TABLE books
    ADD COLUMN total_copies SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER pdf_url;

CREATE TABLE IF NOT EXISTS book_loans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id BIGINT UNSIGNED NOT NULL,
    borrower_name VARCHAR(190) NOT NULL,
    issued_at DATE NOT NULL,
    returned_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_book_loans_book_active (book_id, returned_at),
    KEY idx_book_loans_active (returned_at),
    CONSTRAINT fk_book_loans_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT,
    CONSTRAINT chk_book_loans_returned CHECK (returned_at IS NULL OR returned_at >= issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------- MIGRATION 4 -------------- --

ALTER TABLE book_loans
    ADD COLUMN quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER issued_at,
    ADD COLUMN returned_quantity SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity,
    ADD CONSTRAINT chk_book_loans_quantity CHECK (quantity >= 1),
    ADD CONSTRAINT chk_book_loans_returned_quantity CHECK (returned_quantity <= quantity);

UPDATE book_loans SET returned_quantity = quantity WHERE returned_at IS NOT NULL;

-- ------------- MIGRATION 5 -------------- --

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_tags (
    book_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, tag_id),
    KEY idx_book_tags_tag (tag_id),
    CONSTRAINT fk_book_tags_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    CONSTRAINT fk_book_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------- MIGRATION 6 -------------- --

ALTER TABLE tags
    ADD COLUMN color VARCHAR(20) NOT NULL DEFAULT 'blue' AFTER name;
