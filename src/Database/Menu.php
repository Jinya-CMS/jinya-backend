<?php

namespace App\Database;

use Exception;
use Iterator;

class Menu extends Utils\LoadableEntity implements Utils\FormattableEntityInterface
{

    public string $name;
    public ?int $logo;

    /**
     * @inheritDoc
     * @return Menu
     */
    public static function findById(int $id): ?object
    {
        return self::fetchSingleById('menu', $id, new self());
    }

    /**
     * @inheritDoc
     */
    public static function findByKeyword(string $keyword): Iterator
    {
        $sql = 'SELECT id, name, logo FROM menu WHERE name LIKE :keyword';

        $result = self::executeStatement($sql, ['keyword' => "%$keyword%"]);

        return self::hydrateMultipleResults($result, new self());
    }

    /**
     * @inheritDoc
     */
    public static function findAll(): Iterator
    {
        return self::fetchArray('menu', new self());
    }

    /**
     * @return array
     * @throws Exceptions\ForeignKeyFailedException
     * @throws Exceptions\InvalidQueryException
     * @throws Exceptions\UniqueFailedException
     */
    public function format(): array
    {
        $logo = $this->getLogo();
        $logoData = [];
        if ($logo) {
            $logoData['id'] = $logo->getIdAsInt();
            $logoData['name'] = $logo->name;

            return [
                'name' => $this->name,
                'id' => $this->getIdAsInt(),
                'logo' => $logoData,
            ];
        }

        return [
            'name' => $this->name,
            'id' => $this->getIdAsInt(),
        ];
    }

    /**
     * Gets the logo file
     *
     * @return File|null
     * @throws Exceptions\ForeignKeyFailedException
     * @throws Exceptions\InvalidQueryException
     * @throws Exceptions\UniqueFailedException
     */
    public function getLogo(): ?File
    {
        if ($this->logo === null) {
            return null;
        }

        return File::findById($this->logo);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function create(): void
    {
        $this->internalCreate('menu');
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->internalDelete('menu');
    }

    /**
     * @inheritDoc
     */
    public function update(): void
    {
        $this->internalUpdate('menu');
    }

    /**
     * Gets the menu items
     *
     * @return Iterator
     * @throws Exceptions\ForeignKeyFailedException
     * @throws Exceptions\InvalidQueryException
     * @throws Exceptions\UniqueFailedException
     */
    public function getItems(): Iterator
    {
        $sql = 'SELECT id, menu_id, parent_id, title, highlighted, position, artist_id, page_id, form_id, gallery_id, segment_page_id, route FROM menu_item WHERE menu_id = :id ORDER BY position';

        $result = self::executeStatement($sql, ['id' => $this->id]);

        return self::hydrateMultipleResults($result, new MenuItem());
    }
}