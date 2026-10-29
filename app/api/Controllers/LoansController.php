<?php

class LoansController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(string $bookId): array
    {
        $this->authorize();
        $book = $this->literatureBook($bookId);
        $statement = $this->pdo->prepare(
            'SELECT id, book_id, borrower_name, issued_at, returned_at,
                    CASE WHEN returned_at IS NOT NULL THEN "returned" ELSE "active" END AS status,
                    created_at, updated_at
             FROM book_loans WHERE book_id = :book_id
             ORDER BY returned_at IS NOT NULL, issued_at DESC, id DESC'
        );
        $statement->execute(['book_id' => $book['id']]);

        $peopleStatement = $this->pdo->query(
            'SELECT borrower_name FROM book_loans GROUP BY borrower_name ORDER BY MAX(updated_at) DESC, borrower_name'
        );
        return ['book' => $this->stats($book), 'data' => $statement->fetchAll(), 'people' => $peopleStatement->fetchAll(PDO::FETCH_COLUMN)];
    }

    public function create(string $bookId): array
    {
        $this->authorize();
        $input = $this->validatedInput();
        $id = $this->parseId($bookId, 'Book');

        $this->pdo->beginTransaction();
        try {
            $bookStatement = $this->pdo->prepare(
                "SELECT id, title, author, cover_path, total_copies FROM books WHERE id = :id AND category = 'literature' FOR UPDATE"
            );
            $bookStatement->execute(['id' => $id]);
            $book = $bookStatement->fetch();
            if ($book === false) throw new ApiException('Книгу не знайдено', 404);

            $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM book_loans WHERE book_id = :id AND returned_at IS NULL');
            $countStatement->execute(['id' => $id]);
            if ((int)$countStatement->fetchColumn() >= (int)$book['total_copies']) {
                throw new ApiException('Усі примірники цієї книги вже видані', 409);
            }

            $hasLegacyDueDate = $this->hasLegacyDueDateColumn();
            $statement = $this->pdo->prepare($hasLegacyDueDate
                ? 'INSERT INTO book_loans (book_id, borrower_name, issued_at, due_at)
                   VALUES (:book_id, :borrower_name, :issued_at, :due_at)'
                : 'INSERT INTO book_loans (book_id, borrower_name, issued_at)
                   VALUES (:book_id, :borrower_name, :issued_at)'
            );
            $parameters = ['book_id' => $id] + $input;
            if ($hasLegacyDueDate) {
                // Older installations required due_at, although it is no longer used by the application.
                $parameters['due_at'] = $input['issued_at'];
            }
            $statement->execute($parameters);
            $loanId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $this->show($loanId);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function update(string $loanId): array
    {
        $this->authorize();
        $id = $this->parseId($loanId, 'Loan');
        $input = $this->validatedInput();
        $statement = $this->pdo->prepare(
            'UPDATE book_loans SET borrower_name = :borrower_name, issued_at = :issued_at WHERE id = :id'
        );
        $statement->execute($input + ['id' => $id]);
        if ($statement->rowCount() === 0) $this->assertLoanExists($id);
        return $this->show($id);
    }

    public function returnBook(string $loanId): array
    {
        $this->authorize();
        $id = $this->parseId($loanId, 'Loan');
        $input = $this->jsonInput();
        $returnedAt = $input['returned_at'] ?? date('Y-m-d');
        $this->assertDate($returnedAt, 'returned_at');

        $statement = $this->pdo->prepare(
            'UPDATE book_loans SET returned_at = :returned_at
             WHERE id = :id AND returned_at IS NULL AND :returned_at_check >= issued_at'
        );
        $statement->execute([
            'returned_at' => $returnedAt,
            'returned_at_check' => $returnedAt,
            'id' => $id,
        ]);
        if ($statement->rowCount() === 0) {
            $loan = $this->show($id)['data'];
            if ($loan['returned_at'] !== null) throw new ApiException('Книгу вже повернено', 409);
            throw new ApiException('Дата повернення не може бути раніше дати видачі', 400);
        }
        return $this->show($id);
    }

    private function show(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, book_id, borrower_name, issued_at, returned_at,
                    CASE WHEN returned_at IS NOT NULL THEN "returned" ELSE "active" END AS status
             FROM book_loans WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $loan = $statement->fetch();
        if ($loan === false) throw new ApiException('Запис видачі не знайдено', 404);
        return ['data' => $loan];
    }

    private function literatureBook(string $id): array
    {
        $bookId = $this->parseId($id, 'Book');
        $statement = $this->pdo->prepare(
            "SELECT id, title, author, cover_path, total_copies FROM books WHERE id = :id AND category = 'literature'"
        );
        $statement->execute(['id' => $bookId]);
        $book = $statement->fetch();
        if ($book === false) throw new ApiException('Книгу не знайдено', 404);
        return $book;
    }

    private function stats(array $book): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS active_loans_count
             FROM book_loans WHERE book_id = :id AND returned_at IS NULL'
        );
        $statement->execute(['id' => $book['id']]);
        $stats = $statement->fetch();
        $active = (int)$stats['active_loans_count'];
        return $book + [
            'active_loans_count' => $active,
            'available_copies' => max(0, (int)$book['total_copies'] - $active),
        ];
    }

    private function validatedInput(): array
    {
        $input = $this->jsonInput();
        $name = trim((string)($input['borrower_name'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
            throw new ApiException('Вкажіть, хто взяв книгу (до 190 символів)', 400);
        }
        $issuedAt = $input['issued_at'] ?? null;
        $this->assertDate($issuedAt, 'issued_at');
        return ['borrower_name' => $name, 'issued_at' => $issuedAt];
    }

    private function jsonInput(): array
    {
        try {
            $value = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Request body must be valid JSON', 400);
        }
        if (!is_array($value)) throw new ApiException('Request body must be a JSON object', 400);
        return $value;
    }

    private function assertDate(mixed $value, string $field): void
    {
        if (!is_string($value)) throw new ApiException("{$field} must be a date", 400);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) throw new ApiException("{$field} must use YYYY-MM-DD", 400);
    }

    private function parseId(string $id, string $label): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new ApiException("{$label} id must be a positive integer", 400);
        return $value;
    }

    private function assertLoanExists(int $id): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM book_loans WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) throw new ApiException('Запис видачі не знайдено', 404);
    }

    private function hasLegacyDueDateColumn(): bool
    {
        $statement = $this->pdo->query("SHOW COLUMNS FROM book_loans LIKE 'due_at'");
        return $statement->fetchColumn() !== false;
    }

    private function authorize(): void
    {
        if (empty($_SESSION['admin_authenticated'])) throw new ApiException('Unauthorized', 401);
    }
}
