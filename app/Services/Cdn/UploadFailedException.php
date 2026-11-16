<?php

namespace App\Services\Cdn;

use RuntimeException;

class UploadFailedException extends RuntimeException
{
    /**
     * @param  bool  $accountIsDead  true khi lỗi do cookie hết hạn / tài khoản bị khóa (không nên thử lại bằng tài khoản này)
     */
    public function __construct(string $message, public readonly bool $accountIsDead = false)
    {
        parent::__construct($message);
    }
}
