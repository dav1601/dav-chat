<?php

namespace App\Repositories\Eloquents;

use App\Models\Groups;
use App\Repositories\Groups\GroupsInterface;


class GroupsRepository implements GroupsInterface
{
    public function all()
    {
        return Groups::with(['members', 'founder', 'requestsJoin', 'requestsJoin.sender', 'members.info'])->get();
    }

    public function group($group_id)
    {
        return Groups::with(['members', 'founder', 'requestsJoin', 'requestsJoin.sender', 'members.info'])->where('id', $group_id)->firstOrFail();
    }
}
