<?php

class LoansController
{
    public function __construct(private PDO $pdo) {}

    public function index(string $bookId): array
    {
        $this->authorize();
        $book = $this->book($bookId);
        $statement = $this->pdo->prepare(
            'SELECT id, book_id, borrower_name, issued_at, quantity, returned_quantity,
                    quantity - returned_quantity AS remaining_quantity, returned_at,
                    CASE WHEN returned_quantity >= quantity THEN "returned"
                         WHEN returned_quantity > 0 THEN "partial" ELSE "active" END AS status,
                    created_at, updated_at
             FROM book_loans WHERE book_id = :book_id
             ORDER BY returned_quantity >= quantity, issued_at DESC, id DESC'
        );
        $statement->execute(['book_id' => $book['id']]);
        $people = $this->pdo->query(
            'SELECT borrower_name FROM book_loans GROUP BY borrower_name ORDER BY MAX(updated_at) DESC, borrower_name'
        );
        return ['book' => $this->stats($book), 'data' => $statement->fetchAll(), 'people' => $people->fetchAll(PDO::FETCH_COLUMN)];
    }

    public function create(string $bookId): array
    {
        $this->authorize();
        $input = $this->validatedInput();
        $id = $this->parseId($bookId, 'Book');
        $this->pdo->beginTransaction();
        try {
            $bookStatement = $this->pdo->prepare(
                'SELECT id, title, author, cover_path, category, grade, total_copies FROM books WHERE id = :id FOR UPDATE'
            );
            $bookStatement->execute(['id' => $id]);
            $book = $bookStatement->fetch();
            if ($book === false) throw new ApiException('Книгу не знайдено', 404);
            if ($this->activeQuantity($id) + $input['quantity'] > (int)$book['total_copies']) {
                throw new ApiException('Недостатньо доступних примірників цієї книги', 409);
            }

            $legacyDueDate = $this->hasLegacyDueDateColumn();
            $statement = $this->pdo->prepare($legacyDueDate
                ? 'INSERT INTO book_loans (book_id, borrower_name, issued_at, due_at, quantity)
                   VALUES (:book_id, :borrower_name, :issued_at, :due_at, :quantity)'
                : 'INSERT INTO book_loans (book_id, borrower_name, issued_at, quantity)
                   VALUES (:book_id, :borrower_name, :issued_at, :quantity)'
            );
            $parameters = ['book_id' => $id] + $input;
            if ($legacyDueDate) $parameters['due_at'] = $input['issued_at'];
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
        $input = $this->validatedInput(true);
        $this->pdo->beginTransaction();
        try {
            $loanStatement = $this->pdo->prepare(
                'SELECT bl.book_id, bl.returned_quantity, bl.returned_at, b.total_copies
                 FROM book_loans bl JOIN books b ON b.id = bl.book_id
                 WHERE bl.id = :id FOR UPDATE'
            );
            $loanStatement->execute(['id' => $id]);
            $loan = $loanStatement->fetch();
            if ($loan === false) throw new ApiException('Запис видачі не знайдено', 404);
            $returnedQuantity = $input['returned_quantity'];
            $other = $this->pdo->prepare(
                'SELECT COALESCE(SUM(quantity - returned_quantity), 0)
                 FROM book_loans WHERE book_id = :book_id AND id <> :id'
            );
            $other->execute(['book_id' => $loan['book_id'], 'id' => $id]);
            $active = (int)$other->fetchColumn() + $input['quantity'] - $returnedQuantity;
            if ($active > (int)$loan['total_copies']) throw new ApiException('Недостатньо доступних примірників цієї книги', 409);

            $returnedAt = null;
            if ($returnedQuantity === $input['quantity']) {
                $returnedAt = max($loan['returned_at'] ?? date('Y-m-d'), $input['issued_at']);
            }

            $statement = $this->pdo->prepare(
                'UPDATE book_loans
                 SET borrower_name = :borrower_name, issued_at = :issued_at, quantity = :quantity,
                     returned_quantity = :returned_quantity, returned_at = :returned_at
                 WHERE id = :id'
            );
            $statement->execute($input + ['returned_at' => $returnedAt, 'id' => $id]);
            $this->pdo->commit();
            return $this->show($id);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function returnBook(string $loanId): array
    {
        $this->authorize();
        $id = $this->parseId($loanId, 'Loan');
        $input = $this->jsonInput();
        $returnedAt = $input['returned_at'] ?? date('Y-m-d');
        $this->assertDate($returnedAt, 'returned_at');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT quantity, returned_quantity, issued_at FROM book_loans WHERE id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $id]);
            $loan = $statement->fetch();
            if ($loan === false) throw new ApiException('Запис видачі не знайдено', 404);
            if ($returnedAt < $loan['issued_at']) throw new ApiException('Дата повернення не може бути раніше дати видачі', 400);

            $remaining = (int)$loan['quantity'] - (int)$loan['returned_quantity'];
            if ($remaining <= 0) throw new ApiException('Усі примірники вже повернено', 409);
            $quantity = $this->positiveQuantity($input['quantity'] ?? $remaining, 'Кількість повернених примірників');
            if ($quantity > $remaining) throw new ApiException('Не можна повернути більше примірників, ніж залишилося', 409);

            $newReturned = (int)$loan['returned_quantity'] + $quantity;
            $update = $this->pdo->prepare(
                'UPDATE book_loans SET returned_quantity = :returned_quantity, returned_at = :returned_at WHERE id = :id'
            );
            $update->execute([
                'returned_quantity' => $newReturned,
                'returned_at' => $newReturned === (int)$loan['quantity'] ? $returnedAt : null,
                'id' => $id,
            ]);
            $this->pdo->commit();
            return $this->show($id);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function show(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, book_id, borrower_name, issued_at, quantity, returned_quantity,
                    quantity - returned_quantity AS remaining_quantity, returned_at,
                    CASE WHEN returned_quantity >= quantity THEN "returned"
                         WHEN returned_quantity > 0 THEN "partial" ELSE "active" END AS status
             FROM book_loans WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $loan = $statement->fetch();
        if ($loan === false) throw new ApiException('Запис видачі не знайдено', 404);
        return ['data' => $loan];
    }

    private function book(string $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, year, category, author, cover_path, grade, pdf_url, total_copies FROM books WHERE id = :id'
        );
        $statement->execute(['id' => $this->parseId($id, 'Book')]);
        $book = $statement->fetch();
        if ($book === false) throw new ApiException('Книгу не знайдено', 404);
        return $book;
    }

    private function stats(array $book): array
    {
        $active = $this->activeQuantity((int)$book['id']);
        return $book + ['active_loans_count' => $active, 'available_copies' => max(0, (int)$book['total_copies'] - $active)];
    }

    private function activeQuantity(int $bookId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantity - returned_quantity), 0) FROM book_loans WHERE book_id = :id'
        );
        $statement->execute(['id' => $bookId]);
        return (int)$statement->fetchColumn();
    }

    private function validatedInput(bool $includeReturnedQuantity = false): array
    {
        $input = $this->jsonInput();
        $name = trim((string)($input['borrower_name'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 190) throw new ApiException('Вкажіть, хто взяв книгу (до 190 символів)', 400);
        $issuedAt = $input['issued_at'] ?? null;
        $this->assertDate($issuedAt, 'issued_at');
        $quantity = $this->positiveQuantity($input['quantity'] ?? 1, 'Кількість виданих примірників');
        $result = [
            'borrower_name' => $name,
            'issued_at' => $issuedAt,
            'quantity' => $quantity,
        ];
        if ($includeReturnedQuantity) {
            $returnedQuantity = filter_var($input['returned_quantity'] ?? 0, FILTER_VALIDATE_INT);
            if ($returnedQuantity === false || $returnedQuantity < 0 || $returnedQuantity > $quantity) {
                throw new ApiException('Кількість повернених примірників має бути від 0 до кількості виданих', 400);
            }
            $result['returned_quantity'] = $returnedQuantity;
        }
        return $result;
    }

    private function positiveQuantity(mixed $value, string $label): int
    {
        $quantity = filter_var($value, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 65535) {
            throw new ApiException("{$label} має бути цілим числом від 1 до 65535", 400);
        }
        return $quantity;
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

    private function hasLegacyDueDateColumn(): bool
    {
        return $this->pdo->query("SHOW COLUMNS FROM book_loans LIKE 'due_at'")->fetchColumn() !== false;
    }

    private function authorize(): void
    {
        if (empty($_SESSION['admin_authenticated'])) throw new ApiException('Unauthorized', 401);
    }
}
