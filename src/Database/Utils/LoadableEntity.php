<?php

namespace App\Database\Utils;

use App\Database\Exceptions\ForeignKeyFailedException;
use App\Database\Exceptions\InvalidQueryException;
use App\Database\Exceptions\UniqueFailedException;
use Iterator;
use JetBrains\PhpStorm\Pure;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use Laminas\Hydrator\ReflectionHydrator;
use Laminas\Hydrator\Strategy\StrategyInterface;
use PDO;
use PDOException;

abstract class LoadableEntity
{
    public const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';
    public static ?PDO $pdo;
    public int|string $id = -1;

    /**
     * @param int $id
     * @return object|null
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public static function findById(int $id): ?object;

    /**
     * Gets all entities that match the given keyword
     *
     * @param string $keyword
     * @return Iterator
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public static function findByKeyword(string $keyword): Iterator;

    /**
     * Gets all entities of the given type
     *
     * @return Iterator
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public static function findAll(): Iterator;

    /**
     * Executes the given sql statement
     *
     * @param string $sql
     * @return array|int
     */
    public static function executeSqlString(string $sql): array|int
    {
        $pdo = self::getPdo();
        $stmt = $pdo->query($sql);
        if ($stmt->columnCount() > 0) {
            return $stmt->fetchAll();
        }

        return $stmt->rowCount();
    }

    /**
     * @return PDO
     */
    protected static function getPdo(): PDO
    {
        if (isset(self::$pdo) && self::$pdo !== null) {
            return self::$pdo;
        }

        $database = getenv('MYSQL_DATABASE');
        $user = getenv('MYSQL_USER');
        $password = getenv('MYSQL_PASSWORD');
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: 3306;
        $charset = getenv('MYSQL_CHARSET') ?: 'utf8mb4';
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$database",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("set names $charset");
        self::$pdo = $pdo;

        return $pdo;
    }

    /**
     * Fetches a single entity by the given id
     *
     * @param string $table
     * @param int $id
     * @param mixed $prototype
     * @param StrategyInterface[] $additionalStrategies
     * @return object|null
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    protected static function fetchSingleById(
        string $table,
        int $id,
        mixed $prototype,
        array $additionalStrategies = []
    ): ?object {
        $sql = "SELECT * FROM $table WHERE id = :id";
        $result = self::executeStatement($sql, ['id' => $id]);

        if (count($result) === 0) {
            return null;
        }

        return self::hydrateSingleResult($result[0], $prototype, $additionalStrategies);
    }

    /**
     * Executes the given statement and returns the result
     *
     * @param string $statement
     * @param array $parameters
     * @return array|int
     * @throws ForeignKeyFailedException
     * @throws UniqueFailedException
     * @throws InvalidQueryException
     */
    protected static function executeStatement(string $statement, array $parameters = []): array|int
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare($statement);
        try {
            $stmt->execute($parameters);
            if ($stmt->errorCode() !== '00000') {
                $ex = new InvalidQueryException(errorInfo: $stmt->errorInfo());
                throw self::convertInvalidQueryExceptionToException($ex);
            }

            if ($stmt->columnCount() > 0) {
                return $stmt->fetchAll();
            }

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            $ex = new InvalidQueryException(errorInfo: $exception->errorInfo);
            throw self::convertInvalidQueryExceptionToException($ex);
        }
    }

    /**
     * @param InvalidQueryException $exception
     * @return UniqueFailedException|ForeignKeyFailedException|InvalidQueryException
     */
    #[Pure]
    protected static function convertInvalidQueryExceptionToException(
        InvalidQueryException $exception
    ): UniqueFailedException|ForeignKeyFailedException|InvalidQueryException {
        switch ($exception->errorInfo[1]) {
            case 1062:
                return new UniqueFailedException($exception);
            case 1452:
                return new ForeignKeyFailedException($exception);
        }

        return $exception;
    }

    /**
     * Hydrates the result using the given prototype and returns the object that was hydrated
     *
     * @param array $result
     * @param $prototype
     * @param StrategyInterface[] $additionalStrategies
     * @return object|null
     */
    protected static function hydrateSingleResult(
        array $result,
        mixed $prototype,
        array $additionalStrategies = []
    ): ?object {
        $hydrator = self::getHydrator($additionalStrategies);
        foreach ($result as $key => $item) {
            if (!is_string($key)) {
                unset($result[$key]);
            }
        }

        if ($result === null) {
            return null;
        }
        return $hydrator->hydrate($result, $prototype);
    }

    /**
     * @param array $additionalStrategies
     * @return HydratorInterface
     */
    protected static function getHydrator(array $additionalStrategies): HydratorInterface
    {
        $hydrator = new ReflectionHydrator();
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());
        foreach ($additionalStrategies as $key => $additionalStrategy) {
            $hydrator->addStrategy($key, $additionalStrategy);
        }

        return $hydrator;
    }

    /**
     * Fetches all data in a table and creates a array
     *
     * @param string $table
     * @param $prototype
     * @param StrategyInterface[] $additionalStrategies
     * @return Iterator
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    protected static function fetchArray(
        string $table,
        mixed $prototype,
        array $additionalStrategies = []
    ): Iterator {
        $result = self::executeStatement("SELECT * FROM $table");

        return self::hydrateMultipleResults($result, $prototype, $additionalStrategies);
    }

    /**
     * Hydrates the result using the given prototype as array
     *
     * @param array $result
     * @param mixed $prototype
     * @param StrategyInterface[] $additionalStrategies
     * @return Iterator
     */
    protected static function hydrateMultipleResults(
        array $result,
        mixed $prototype,
        array $additionalStrategies = []
    ): Iterator {
        $hydrator = self::getHydrator($additionalStrategies);

        foreach ($result as $item) {
            foreach ($item as $key => $field) {
                if (!is_string($key)) {
                    unset($item[$key]);
                }
            }
            $proto = clone $prototype;
            yield $hydrator->hydrate($item, $proto);
        }
    }

    public function getIdAsInt(): int
    {
        return (int)$this->id;
    }

    public function getIdAsString(): string
    {
        return (string)$this->id;
    }

    /**
     * Creates the given entity
     *
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public function create(): void;

    /**
     * Deletes the given entity
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public function delete(): void;

    /**
     * Updates the given entity
     *
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    abstract public function update(): void;

    /**
     * @param string $table
     * @param array $strategies
     * @param array $skippedFields
     * @return int
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    protected function internalCreate(string $table, array $strategies = [], array $skippedFields = []): int
    {
        $hydrator = self::getHydrator($strategies);
        $hydrator->addFilter('excludes', new SkipFieldFilter([...$skippedFields, 'id', 'pdo']));

        $extracted = $hydrator->extract($this);
        $keys = array_keys($extracted);
        $columns = implode(',', $keys);
        $values = implode(',', array_map(static fn(string $value) => ":$value", $keys));
        $sql = "INSERT INTO $table ($columns) VALUES ($values)";
        self::executeStatement($sql, $extracted);

        $this->id = (int)self::getPdo()->lastInsertId();

        return $this->id;
    }

    /**
     * @param string $table
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    protected function internalDelete(string $table): void
    {
        $sql = "DELETE FROM $table WHERE id = :id";
        self::executeStatement($sql, ['id' => $this->id]);
    }

    /**
     * @param string $table
     * @param array $strategies
     * @param array $skippedFields
     * @throws ForeignKeyFailedException
     * @throws InvalidQueryException
     * @throws UniqueFailedException
     */
    protected function internalUpdate(string $table, array $strategies = [], array $skippedFields = []): void
    {
        $hydrator = self::getHydrator($strategies);
        $hydrator->addFilter('excludes', new SkipFieldFilter([...$skippedFields, 'id', 'pdo']));

        $extracted = $hydrator->extract($this);
        $update = [];
        foreach ($extracted as $key => $item) {
            $update[] = "$key=:$key";
        }
        $setInstructions = implode(',', $update);
        $sql = "UPDATE $table SET $setInstructions WHERE id = :id";
        $extracted['id'] = $this->id;
        self::executeStatement($sql, $extracted);
    }
}