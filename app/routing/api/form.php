<?php

use App\Web\Actions\Form\CreateFormAction;
use App\Web\Actions\Form\DeleteFormAction;
use App\Web\Actions\Form\GetFormByIdAction;
use App\Web\Actions\Form\Items\CreateItemAction;
use App\Web\Actions\Form\Items\DeleteItemAction;
use App\Web\Actions\Form\Items\GetItemsAction;
use App\Web\Actions\Form\Items\UpdateItemAction;
use App\Web\Actions\Form\ListAllFormsAction;
use App\Web\Actions\Form\UpdateFormAction;
use App\Web\Middleware\AuthenticationMiddleware;
use App\Web\Middleware\CheckRequiredFieldsMiddleware;
use App\Web\Middleware\RoleMiddleware;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $api) {
    $api->group('form', function (RouteCollectorProxy $group) {
        $group->get('', ListAllFormsAction::class);
        $group->post('', CreateFormAction::class)->add(new CheckRequiredFieldsMiddleware(['title', 'toAddress']));
        $group->get('/{id}', GetFormByIdAction::class);
        $group->put('/{id}', UpdateFormAction::class);
        $group->delete('/{id}', DeleteFormAction::class);
        $group->group('/{id}/item', function (RouteCollectorProxy $item) {
            $item->get('', GetItemsAction::class);
            $item->post('', CreateItemAction::class)->add(new CheckRequiredFieldsMiddleware(['label', 'position']));
            $item->delete('/{position}', DeleteItemAction::class);
            $item->put('/{position}', UpdateItemAction::class);
        });
    })->add(new RoleMiddleware(RoleMiddleware::ROLE_WRITER))->add(AuthenticationMiddleware::class);
};