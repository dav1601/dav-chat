<?php

namespace App\Repositories\Eloquents;

use App\Models\User;
use App\Models\Groups;
use App\Models\Message;
use App\Models\UserMessage;
use App\Repositories\Messages\MessagesInterface;

class MessagesRepository implements MessagesInterface
{

    public function createMessageSystemGroup($group_id, $user_id, $action)
    {
        $group = Groups::with('founder')->where('id', $group_id)->first();
        $username =  User::select('name')->where('id', $user_id)->first()->name;
        $message = new Message();
        $user_message = new UserMessage();
        $arrayMessage = [
            "kick" => " đã bị quản trị viên xoá khỏi nhóm",
            "add" => " đã được quản trị viên thêm vào nhóm",
            "out" => " đã rời khỏi nhóm",
        ];
        if (!$group || !$username || !array_key_exists($action, $arrayMessage))
            return false;
        $message_system = $username . $arrayMessage[$action];
        $message->message = $message_system;
        $message->type = 4;
        if (!$message->save()) {
            $message->delete();
            return false;
        }
        $user_message->msg_id = (int)$message->id;
        $user_message->sd_id = (int)$group->founder->id;
        $user_message->rcv_group_id = (int) $group_id;
        $user_message->seen = 1;
        $user_message->type = 1;
        $user_message->type_msg = 4;
        if (!$user_message->save()) {
            $user_message->delete();
            return false;
        }
        $user_message->message = $message;
        $user_message->sender = $group->founder;
        return $user_message;
    }
}