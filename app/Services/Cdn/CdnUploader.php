<?php

namespace App\Services\Cdn;

use App\Models\UploadConfig;

/**
 * Giao diện chung cho CDN đích, để đổi CDN sau này chỉ sửa một chỗ.
 */
interface CdnUploader
{
    /**
     * Upload một file ảnh và trả về URL công khai.
     *
     * @throws UploadFailedException
     */
    public function upload(string $filePath, string $filename, UploadConfig $account): string;

    /**
     * Upload nhiều file với tối đa $concurrency request cùng lúc (cửa sổ trượt), báo từng kết quả ngay khi xong.
     *
     * @param  iterable<int, array{key: int|string, path: string, filename: string, account: UploadConfig}>  $items
     * @param  callable(int|string, string|UploadFailedException): void  $onResult
     */
    public function uploadStream(iterable $items, int $concurrency, callable $onResult): void;
}
