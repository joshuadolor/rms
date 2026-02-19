<?php

namespace App\Application\MenuItem;

use App\Models\User;

final readonly class DeleteUserMenuItem
{
    public function __construct(
        private GetUserMenuItem $getUserMenuItem
    ) {}

    public function handle(User $user, string $itemUuid): bool
    {
        $item = $this->getUserMenuItem->handle($user, $itemUuid);
        if ($item === null) {
            return false;
        }

        return $item->delete();
    }
}
