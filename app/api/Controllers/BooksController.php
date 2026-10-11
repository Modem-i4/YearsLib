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

        $sql = 'SELECT id, title, year, category, author, cover_path, grade, pdf_url, created_at, updated_at
                FROM books';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY grade IS NULL, grade, title, id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $books = $statement->fetchAll();
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
            'SELECT id, title, year, category, author, cover_path, grade, pdf_url, created_at, updated_at
             FROM books WHERE id = :id'
        );
        $statement->execute(['id' => $bookId]);
        $book = $statement->fetch();

        if ($book === false) {
            throw new ApiException('Book not found', 404);
        }

        return ['data' => $book];
    }

    public function create(): array
    {
        $this->authorizeWrite();
        $book = $this->validatedInput();
        $statement = $this->pdo->prepare(
            'INSERT INTO books (title, year, category, author, cover_path, grade, pdf_url)
             VALUES (:title, :year, :category, :author, :cover_path, :grade, :pdf_url)'
        );
        $statement->execute($book);

        return $this->show((string)$this->pdo->lastInsertId());
    }

    public function update(string $id): array
    {
        $this->authorizeWrite();
        $bookId = $this->parseId($id);
        $book = $this->validatedInput();
        $book['id'] = $bookId;

        $statement = $this->pdo->prepare(
            'UPDATE books
             SET title = :title, year = :year, category = :category, author = :author, cover_path = :cover_path,
                 grade = :grade, pdf_url = :pdf_url
             WHERE id = :id'
        );
        $statement->execute($book);

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

        return [
            'title' => $title,
            'year' => $year,
            'category' => $category,
            'author' => $author,
            'cover_path' => $coverPath,
            'grade' => $grade,
            'pdf_url' => $pdfUrl,
        ];
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