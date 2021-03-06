<?php

namespace App\Database;

use App\Database\Utils\FormattableEntityInterface;
use Exception;
use Iterator;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;

class KnownDevice extends Utils\LoadableEntity implements FormattableEntityInterface
{
    public int $userId;
    public string $deviceKey;
    public string $userAgent = '';
    public string $remoteAddress = '';

    /**
     * @inheritDoc
     */
    public static function findById(int $id): ?object
    {
        return self::fetchSingleById('known_device', $id, new self());
    }

    /**
     * @inheritDoc
     */
    public static function findByKeyword(string $keyword): Iterator
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * Gets all known devices for the given artist
     *
     * @param int $artistId
     * @return Iterator
     * @throws Exceptions\ForeignKeyFailedException
     * @throws Exceptions\InvalidQueryException
     * @throws Exceptions\UniqueFailedException
     */
    public static function findByArtist(int $artistId): Iterator
    {
        $sql = 'SELECT id, user_id, device_key, user_agent, remote_address FROM known_device WHERE user_id = :artistId';

        $result = self::executeStatement($sql, ['artistId' => $artistId]);

        return self::hydrateMultipleResults($result, new self());
    }

    /**
     * @inheritDoc
     */
    public static function findAll(): Iterator
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * Gets a known device by code
     *
     * @param string $knownDeviceCode
     * @return KnownDevice|null
     * @throws Exceptions\ForeignKeyFailedException
     * @throws Exceptions\InvalidQueryException
     * @throws Exceptions\UniqueFailedException
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public static function findByCode(string $knownDeviceCode): ?KnownDevice
    {
        $sql = 'SELECT id, user_id, device_key, user_agent, remote_address FROM known_device WHERE device_key = :knownDeviceCode';
        $result = self::executeStatement(
            $sql,
            ['knownDeviceCode' => $knownDeviceCode]
        );
        if (count($result) === 0) {
            return null;
        }

        return self::hydrateSingleResult($result[0], new self());
    }

    /**
     * @inheritDoc
     */
    public function create(): void
    {
        $this->id = $this->internalCreate('known_device');
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->internalDelete('known_device');
    }

    /**
     * @inheritDoc
     */
    public function update(): void
    {
        $this->internalUpdate('known_device');
    }

    /**
     * Securely sets the device code
     * @throws Exception
     */
    public function setDeviceKey(): void
    {
        $this->deviceKey = bin2hex(random_bytes(20));
    }

    /**
     * @inheritdoc
     */
    #[ArrayShape(['remoteAddress' => "string", 'userAgent' => "string", 'key' => "string"])]
    public function format(): array
    {
        return [
            'remoteAddress' => $this->remoteAddress,
            'userAgent' => $this->userAgent,
            'key' => $this->deviceKey,
        ];
    }
}