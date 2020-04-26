<?php

namespace App\Web\Actions\File;

use App\Database\File;
use App\Web\Actions\Action;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpNotFoundException;

class GetFileByIdAction extends Action
{

    /**
     * @inheritDoc
     * @throws JsonException
     * @throws HttpNotFoundException
     */
    protected function action(): Response
    {
        $artist = File::findById((int)$this->args['id']);
        if ($artist === null) {
            throw new HttpNotFoundException($this->request, 'Artist not found');
        }

        return $this->respond($artist->format());
    }
}