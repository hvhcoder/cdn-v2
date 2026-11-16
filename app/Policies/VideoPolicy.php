<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    /**
     * Chỉ VIP và admin được upload video.
     */
    public function upload(User $user): bool
    {
        return $user->isAdmin() || $user->isVip();
    }

    public function view(User $user, Video $video): bool
    {
        return $user->isAdmin() || $video->user_id === $user->id;
    }

    public function update(User $user, Video $video): bool
    {
        return $this->view($user, $video);
    }

    public function process(User $user, Video $video): bool
    {
        return $this->upload($user) && $this->view($user, $video);
    }

    public function delete(User $user, Video $video): bool
    {
        return $this->view($user, $video);
    }
}
