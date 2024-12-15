<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\BroadcastAction;
use framework\Socket\Actions\BaseAction;
use Exception;

class ExceptionAction extends BaseAction
{
    const ACTION_NAME = 'exception-action';
    protected string $name = self::ACTION_NAME;

    public function execute(array $data): mixed
    {
        $this->send([
            'message' => $data['data'],
            'success' => false,
        ], $this->fd);
        return null;
    }
}
