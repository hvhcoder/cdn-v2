<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

/**
 * Thư mục là dữ liệu riêng của từng user: chỉ chủ sở hữu mới xem/sửa/xóa (kể cả admin cũng không xem thư mục của người khác).
 */
class FolderPolicy
{
    public function view(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    public function update(User $user, Folder $folder): bool
    {
        return $this->view($user, $folder);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->view($user, $folder);
    }
}
