<?php

class PlacementsController
{
    public function __construct(private PDO $pdo) {}

    public function index(): array
    {
        $this->authorize();
        return ['data' => $this->pdo->query("SELECT id, name FROM placements ORDER BY name = 'Загальне' DESC, name, id")->fetchAll()];
    }

    public function create(): array
    {
        $this->authorize();
        $name = $this->validatedName();
        try {
            $statement = $this->pdo->prepare('INSERT INTO placements (name) VALUES (:name)');
            $statement->execute(['name' => $name]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) throw new ApiException('Розміщення з такою назвою вже існує', 409);
            throw $error;
        }
        return ['data' => ['id' => (int)$this->pdo->lastInsertId(), 'name' => $name]];
    }

    public function update(string $placementId): array
    {
        $this->authorize();
        $id = $this->parseId($placementId);
        $this->assertMutable($id);
        $name = $this->validatedName();
        try {
            $statement = $this->pdo->prepare('UPDATE placements SET name = :name WHERE id = :id');
            $statement->execute(['name' => $name, 'id' => $id]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) throw new ApiException('Розміщення з такою назвою вже існує', 409);
            throw $error;
        }
        if ($statement->rowCount() === 0) $this->assertExists($id);
        return ['data' => ['id' => $id, 'name' => $name]];
    }

    public function delete(string $placementId): array
    {
        $this->authorize();
        $id = $this->parseId($placementId);
        $this->assertMutable($id);
        try {
            $statement = $this->pdo->prepare('DELETE FROM placements WHERE id = :id');
            $statement->execute(['id' => $id]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1451) throw new ApiException('Неможливо видалити розміщення, яке використовується книгами', 409);
            throw $error;
        }
        if ($statement->rowCount() === 0) throw new ApiException('Розміщення не знайдено', 404);
        return ['ok' => true];
    }

    private function validatedName(): string
    {
        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Request body must be valid JSON', 400);
        }
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) throw new ApiException('Назва розміщення має містити від 1 до 80 символів', 400);
        return $name;
    }

    private function parseId(string $id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new ApiException('Placement id must be a positive integer', 400);
        return $value;
    }

    private function assertMutable(int $id): void
    {
        $statement = $this->pdo->prepare('SELECT name FROM placements WHERE id = :id');
        $statement->execute(['id' => $id]);
        $name = $statement->fetchColumn();
        if ($name === false) throw new ApiException('Розміщення не знайдено', 404);
        if ($name === 'Загальне') throw new ApiException('Розміщення «Загальне» не можна змінити або видалити', 409);
    }

    private function assertExists(int $id): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM placements WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) throw new ApiException('Розміщення не знайдено', 404);
    }

    private function authorize(): void
    {
        if (empty($_SESSION['admin_authenticated'])) throw new ApiException('Unauthorized', 401);
    }
}
