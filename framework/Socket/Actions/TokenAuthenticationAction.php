<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphChats;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use InvalidArgumentException;

class TokenAuthenticationAction extends AbstractAction
{
    const ACTION_NAME = 'token-authentication';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $params): void
    {
        if (!isset($params['token'])) {
            throw new InvalidArgumentException('인증에 실패하였습니다. token');
        }
        if (!isset($params['tId'])) {
            throw new InvalidArgumentException('인증에 실패하였습니다. tId'); // Authentication failed
        }
    }

    public function execute(array $data): mixed
    {
        $this->validateData($data);
        $aData = $data;
        $triumphChats = new TriumphChats();
        $strToken = Arr::get($aData, 'token');
        $iTId = Arr::get($aData, 'tId');
        $aMember = $triumphChats->getUser(['token' => $strToken]);
        if (!$aMember) {
            $this->server->disconnect($this->fd);
            throw new InvalidArgumentException('Authentication failed. member'); // 인증에 실패하였습니다.
        }

        // [메모리] member
        if ($this->memberAssocPersistence != null) {
            $this->memberAssocPersistence->assoc($this->fd, [
                'member_id' => $aMember['member_id'],
                'token' => $strToken,
                'member_name' => $aMember['name'],
                'member_image_url' => $aMember['image_url']
            ]);
        }

        $this->send([
            'data' => [
                'member' => [
                    'memberName' => $aMember['name'],
                    'memberImageUrl' => $aMember['image_url'],
                ]
            ],
            'tId' => $iTId,
        ], $this->fd);
        return true;
    }
}
