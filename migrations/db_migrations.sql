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

-- ------------- MIGRATION 7 -------------- --

ALTER TABLE tags
    ADD COLUMN show_in_filters TINYINT(1) NOT NULL DEFAULT 0 AFTER color;

-- ------------- MIGRATION 8 -------------- --

ALTER TABLE book_loans
    ADD COLUMN notes TEXT NULL AFTER returned_at;

-- ------------- MIGRATION 9 -------------- --

CREATE TABLE IF NOT EXISTS placements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_placements_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO placements (name) VALUES
    ('Загальне'), ('1А'), ('1Б'), ('2А'), ('2Б'), ('3А'), ('3Б'), ('4А'), ('4Б'),
    ('5А'), ('5Б'), ('6А'), ('6Б'), ('7'), ('8'), ('9');

ALTER TABLE books
    ADD COLUMN placement_id BIGINT UNSIGNED NULL AFTER grade,
    ADD KEY idx_books_placement (placement_id),
    ADD CONSTRAINT fk_books_placement FOREIGN KEY (placement_id) REFERENCES placements(id) ON DELETE RESTRICT;

UPDATE books
    SET placement_id = (SELECT id FROM placements WHERE name = 'Загальне')
    WHERE category = 'literature' AND placement_id IS NULL;

-- ------------- MIGRATION 10 -------------- --

ALTER TABLE books
    ADD COLUMN is_used TINYINT(1) NOT NULL DEFAULT 1 AFTER placement_id;
