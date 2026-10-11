-- ------------- MIGRATION 1 -------------- --
-- ------------- MIGRATION 1 -------------- --

CREATE TABLE IF NOT EXISTS books (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    author VARCHAR(190) NULL,
    cover_path VARCHAR(500) NULL,
    grade TINYINT UNSIGNED NULL,
    pdf_url VARCHAR(2048) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_grade_title (grade, title),
    CONSTRAINT chk_books_grade CHECK (grade IS NULL OR grade BETWEEN 1 AND 11)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;