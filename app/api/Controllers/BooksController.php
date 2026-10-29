<?php

class BooksController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): array
    {
        $category = $_GET['category'] ?? null;
        if ($category !== null && !in_array($category, ['textbook', 'literature'], true)) {
            throw new ApiException('category must be textbook or literature', 400);
        }

        $grade = $_GET['grade'] ?? null;
        if ($grade !== null && $grade !== '') {
            $grade = filter_var($grade, FILTER_VALIDATE_INT);
            if ($grade === false || $grade < 1 || $grade > 11) {
                throw new ApiException('grade must be an integer from 1 to 11', 400);
            }

        }

        $conditions = [];
        $parameters = [];
        if ($category !== null) {
            $conditions[] = 'category = :category';
            $parameters['category'] = $category;
        }
        if ($grade !== null && $grade !== '') {
            $conditions[] = 'grade = :grade';
            $parameters['grade'] = $grade;
        }

        $sql = 'SELECT b.id, b.title, b.year, b.category, b.author, b.cover_path, b.grade, b.pdf_url,
                       b.total_copies,
                       GREATEST(b.total_copies - COALESCE(SUM(bl.quantity - bl.returned_quantity), 0), 0) AS available_copies,
                       COALESCE(SUM(bl.quantity - bl.returned_quantity), 0) AS active_loans_count,
                       b.created_at, b.updated_at
                FROM books b
                LEFT JOIN book_loans bl ON bl.book_id = b.id';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', array_map(static fn ($item) => 'b.' . $item, $conditions));
        }
        $sql .= ' GROUP BY b.id ORDER BY b.grade IS NULL, b.grade, b.title, b.id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $books = $statement->fetchAll();
        $this->attachTags($books);
        if (empty($_SESSION['admin_authenticated'])) {
            foreach ($books as &$publicBook) {
                unset($publicBook['active_loans_count']);
            }
            unset($publicBook);
        }
        $grades = [];

        if ($category === 'textbook') {
            $gradeStatement = $this->pdo->query(
                "SELECT DISTINCT grade FROM books
                 WHERE category = 'textbook' AND grade IS NOT NULL
                 ORDER BY grade"
            );
            $grades = array_map('intval', $gradeStatement->fetchAll(PDO::FETCH_COLUMN));
        }

        return ['data' => $books, 'grades' => $grades];
    }

    public function show(string $id): array
    {
        $bookId = $this->parseId($id);
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.title, b.year, b.category, b.author, b.cover_path, b.grade, b.pdf_url,
                    b.total_copies,
                    GREATEST(b.total_copies - COALESCE(SUM(bl.quantity - bl.returned_quantity), 0), 0) AS available_copies,
                    COALESCE(SUM(bl.quantity - bl.returned_quantity), 0) AS active_loans_count,
                    b.created_at, b.updated_at
             FROM books b LEFT JOIN book_loans bl ON bl.book_id = b.id
             WHERE b.id = :id GROUP BY b.id'
        );
        $statement->execute(['id' => $bookId]);
        $book = $statement->fetch();

        if ($book === false) {
            throw new ApiException('Book not found', 404);
        }
        $books = [$book];
        $this->attachTags($books);
        $book = $books[0];
        if (empty($_SESSION['admin_authenticated'])) {
            unset($book['active_loans_count']);
        }

        return ['data' => $book];
    }

    public function create(): array
    {
        $this->authorizeWrite();
        $book = $this->validatedInput();
        $tagIds = $book['tag_ids'];
        unset($book['tag_ids']);
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO books (title, year, category, author, cover_path, grade, pdf_url, total_copies)
                 VALUES (:title, :year, :category, :author, :cover_path, :grade, :pdf_url, :total_copies)'
            );
            $statement->execute($book);
            $id = (int)$this->pdo->lastInsertId();
            $this->syncTags($id, $tagIds);
            $this->pdo->commit();
            return $this->show((string)$id);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function update(string $id): array
    {
        $this->authorizeWrite();
        $bookId = $this->parseId($id);
        $book = $this->validatedInput();
        $tagIds = $book['tag_ids'];
        unset($book['tag_ids']);
        $book['id'] = $bookId;

        $activeStatement = $this->pdo->prepare('SELECT COALESCE(SUM(quantity - returned_quantity), 0) FROM book_loans WHERE book_id = :id');
        $activeStatement->execute(['id' => $bookId]);
        $activeLoans = (int)$activeStatement->fetchColumn();
        if ($book['total_copies'] < $activeLoans) {
            throw new ApiException('Кількість примірників не може бути меншою за кількість активних видач', 409);
        }

        $statement = $this->pdo->prepare(
            'UPDATE books
             SET title = :title, year = :year, category = :category, author = :author, cover_path = :cover_path,
                 grade = :grade, pdf_url = :pdf_url, total_copies = :total_copies
             WHERE id = :id'
        );
        $this->pdo->beginTransaction();
        try {
            $statement->execute($book);
            $this->syncTags($bookId, $tagIds);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return $this->show((string)$bookId);
    }

    private function validatedInput(): array
    {
        try {
            $decoded = json_decode(file_get_contents('php://input') ?: '', false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Request body must be valid JSON', 400);
        }

        if (!$decoded instanceof stdClass) {
            throw new ApiException('Request body must be a JSON object', 400);
        }
        $input = (array)$decoded;

        $title = $this->requiredText($input, 'title', 190);
        $category = $input['category'] ?? null;
        if (!is_string($category) || !in_array($category, ['textbook', 'literature'], true)) {
            throw new ApiException('category must be textbook or literature', 400);
        }
        $year = $input['year'] ?? null;
        if ($year !== null && $year !== '') {
            $year = filter_var($year, FILTER_VALIDATE_INT);
            if ($year === false || $year < 1000 || $year > 9999) {
                throw new ApiException('year must be an integer from 1000 to 9999 or null', 400);
            }
        } else {
            $year = null;
        }
        $author = $this->optionalText($input, 'author', 190);
        $grade = $input['grade'] ?? null;
        if ($grade !== null && $grade !== '') {
            $grade = filter_var($grade, FILTER_VALIDATE_INT);
            if ($grade === false || $grade < 1 || $grade > 11) {
                throw new ApiException('grade must be an integer from 1 to 11 or null', 400);
            }
        } else {
            $grade = null;
        }

        $pdfUrl = $this->optionalText($input, 'pdf_url', 2048);
        if ($pdfUrl !== null) {
            $urlParts = parse_url($pdfUrl);
            if (
                filter_var($pdfUrl, FILTER_VALIDATE_URL) === false ||
                !is_array($urlParts) ||
                strtolower($urlParts['scheme'] ?? '') !== 'https' ||
                empty($urlParts['host']) ||
                isset($urlParts['user']) ||
                isset($urlParts['pass'])
            ) {
                throw new ApiException('pdf_url must be a valid HTTPS URL or null', 400);
            }
        }

        $coverPath = $this->optionalText($input, 'cover_path', 500);
        if ($coverPath !== null && preg_match('/[\x00-\x1F\x7F]/', $coverPath)) {
            throw new ApiException('cover_path contains invalid characters', 400);
        }

        $totalCopies = $input['total_copies'] ?? 1;
        $totalCopies = filter_var($totalCopies, FILTER_VALIDATE_INT);
        if ($totalCopies === false || $totalCopies < 0 || $totalCopies > 65535) {
            throw new ApiException('total_copies must be an integer from 0 to 65535', 400);
        }
        $tagIds = [];
        if ($category === 'literature') {
            $rawTagIds = $input['tag_ids'] ?? [];
            if (!is_array($rawTagIds)) throw new ApiException('tag_ids must be an array', 400);
            foreach ($rawTagIds as $tagId) {
                $parsedTagId = filter_var($tagId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsedTagId === false) throw new ApiException('tag_ids must contain positive integers', 400);
                $tagIds[] = (int)$parsedTagId;
            }
            $tagIds = array_values(array_unique($tagIds));
        }
        return [
            'title' => $title,
            'year' => $year,
            'category' => $category,
            'author' => $author,
            'cover_path' => $coverPath,
            'grade' => $grade,
            'pdf_url' => $pdfUrl,
            'total_copies' => $totalCopies,
            'tag_ids' => $tagIds,
        ];
    }

    private function attachTags(array &$books): void
    {
        if ($books === []) return;
        $ids = array_map(static fn (array $book): int => (int)$book['id'], $books);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT bt.book_id, t.id, t.name, t.color FROM book_tags bt JOIN tags t ON t.id = bt.tag_id
             WHERE bt.book_id IN ({$placeholders}) ORDER BY t.name, t.id"
        );
        $statement->execute($ids);
        $tagsByBook = [];
        foreach ($statement->fetchAll() as $tag) {
            $tagsByBook[(int)$tag['book_id']][] = ['id' => (int)$tag['id'], 'name' => $tag['name'], 'color' => $tag['color']];
        }
        foreach ($books as &$book) $book['tags'] = $tagsByBook[(int)$book['id']] ?? [];
        unset($book);
    }

    private function syncTags(int $bookId, array $tagIds): void
    {
        if ($tagIds !== []) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM tags WHERE id IN ({$placeholders})");
            $statement->execute($tagIds);
            if ((int)$statement->fetchColumn() !== count($tagIds)) throw new ApiException('Один або кілька тегів не знайдено', 400);
        }
        $delete = $this->pdo->prepare('DELETE FROM book_tags WHERE book_id = :book_id');
        $delete->execute(['book_id' => $bookId]);
        if ($tagIds === []) return;
        $insert = $this->pdo->prepare('INSERT INTO book_tags (book_id, tag_id) VALUES (:book_id, :tag_id)');
        foreach ($tagIds as $tagId) $insert->execute(['book_id' => $bookId, 'tag_id' => $tagId]);
    }

    private function requiredText(array $input, string $field, int $maxLength): string
    {
        $value = $this->optionalText($input, $field, $maxLength);
        if ($value === null) {
            throw new ApiException("{$field} is required and must be at most {$maxLength} characters", 400);
        }

        return $value;
    }

    private function optionalText(array $input, string $field, int $maxLength): ?string
    {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return null;
        }
        if (!is_string($input[$field])) {
            throw new ApiException("{$field} must be text or null", 400);
        }

        $value = trim($input[$field]);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new ApiException("{$field} must be at most {$maxLength} characters", 400);
        }

        return $value;
    }

    private function parseId(string $id): int
    {
        $parsed = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new ApiException('Book id must be a positive integer', 400);
        }

        return $parsed;
    }

    private function authorizeWrite(): void
    {
        if (empty($_SESSION['admin_authenticated'])) {
            throw new ApiException('Unauthorized', 401);
        }
    }
}
