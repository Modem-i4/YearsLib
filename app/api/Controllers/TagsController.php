<?php

class TagsController
{
    private const COLORS = ['blue', 'coral', 'yellow', 'green', 'violet', 'pink', 'teal', 'orange', 'slate', 'brown'];

    public function __construct(private PDO $pdo) {}

    public function index(): array
    {
        $this->authorize();
        return ['data' => $this->pdo->query('SELECT id, name, color FROM tags ORDER BY name, id')->fetchAll()];
    }

    public function create(): array
    {
        $this->authorize();
        $tag = $this->validatedTag();
        try {
            $statement = $this->pdo->prepare('INSERT INTO tags (name, color) VALUES (:name, :color)');
            $statement->execute($tag);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) throw new ApiException('Тег із такою назвою вже існує', 409);
            throw $error;
        }
        return ['data' => ['id' => (int)$this->pdo->lastInsertId()] + $tag];
    }

    public function update(string $tagId): array
    {
        $this->authorize();
        $id = $this->parseId($tagId);
        $tag = $this->validatedTag();
        try {
            $statement = $this->pdo->prepare('UPDATE tags SET name = :name, color = :color WHERE id = :id');
            $statement->execute($tag + ['id' => $id]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) throw new ApiException('Тег із такою назвою вже існує', 409);
            throw $error;
        }
        if ($statement->rowCount() === 0) $this->assertExists($id);
        return ['data' => ['id' => $id] + $tag];
    }

    public function delete(string $tagId): array
    {
        $this->authorize();
        $id = $this->parseId($tagId);
        $statement = $this->pdo->prepare('DELETE FROM tags WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) throw new ApiException('Тег не знайдено', 404);
        return ['ok' => true];
    }

    private function validatedTag(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Request body must be valid JSON', 400);
        }
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) throw new ApiException('Назва тегу має містити від 1 до 80 символів', 400);
        $color = $input['color'] ?? 'blue';
        if (!is_string($color) || !in_array($color, self::COLORS, true)) throw new ApiException('Неприпустимий колір тегу', 400);
        return ['name' => $name, 'color' => $color];
    }

    private function parseId(string $id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new ApiException('Tag id must be a positive integer', 400);
        return $value;
    }

    private function assertExists(int $id): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM tags WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) throw new ApiException('Тег не знайдено', 404);
    }

    private function authorize(): void
    {
        if (empty($_SESSION['admin_authenticated'])) throw new ApiException('Unauthorized', 401);
    }
}
