<?php

namespace App\Http\Controllers;

use App\Events\CustomEvent;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Message;
use App\Events\SendMessage;
use App\Models\UserMessage;
use Illuminate\Http\Request;
use Laravel\Ui\Presets\React;
use App\Events\SendMessageGroup;
use App\Models\ReactionMessage;
use App\Models\RoomCallChat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Groups\GroupsInterface;
use App\Repositories\Messages\MessagesInterface;
use stdClass;

class MessagesController extends Controller
{
    public function __construct(GroupsInterface $dav2_gr, MessagesInterface $dav2_msg)
    {
        $this->dav2_group = $dav2_gr;
        $this->dav2_messages = $dav2_msg;
    }
    public function index($type, $conversationId, Request $request)
    {
        try {
            $type = $type;
            $item_page = 20;
            $end_page = 0;
            $messenger_media = [];
            $arrayMessages = array();
            $page =  $request->has('page') && $request->page != null ? $request->page : 1;
            $limit = $page * $item_page;
            $setupRelation = ['message', 'message_parent', 'message.reaction', 'message.reaction.user', 'call_infor'];
            if ($type == 0) {
                $queryMsg = UserMessage::where(function ($q) use ($conversationId) {
                    $q->where('sd_id', '=', Auth::id())
                        ->where('rcv_id', '=', $conversationId)
                        ->where('type', 0);
                })->orWhere(function ($q) use ($conversationId) {
                    $q->where('sd_id', '=', $conversationId)
                        ->where('rcv_id', '=', Auth::id())
                        ->where('type', 0);
                })->with($setupRelation);
                $count = $queryMsg->count();
                if ($limit >= $count) {
                    $end_page = 1;
                    $messages = $queryMsg->get();
                } else {
                    $offset = $count - $limit;
                    $messages = $queryMsg->offset($offset)->limit($limit)->get();
                }
                $messages->page = $page;
            } else {
                $setupRelation[] = "sender";
                $queryMsg = UserMessage::with($setupRelation)->where('rcv_group_id', $conversationId)->where('type', 1);
                $count = $queryMsg->count();
                if ($limit >= $count) {
                    $end_page = 1;
                    $messages = $queryMsg->get();
                } else {
                    $offset = $count - $limit;
                    $messages = $queryMsg->offset($offset)->limit($limit)->get();
                }
            }
            $messages = collect($messages)->groupBy('created_at');
            foreach ($messages as $key => $msg) {
                $obj = new stdClass();
                $obj->created_at = $key;
                $msg->map(function ($item, $key) {
                    return collect($item->message->reaction)->groupBy('reaction');
                });
                $obj->messages = $msg;
                $arrayMessages[] = $obj;
                unset($obj);
            }
            $messages->arrayMessages = $arrayMessages;
            if ($page == 1) {
                $messenger_media = $this->dav2_messages->getAllMessageMedia($conversationId, $type);
            }
            return response()->json(['data' => $messages->arrayMessages,   'page' => $page, 'endPage' => $end_page, 'messenger_media' => $messenger_media,], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $created_time = $this->dav2_messages->created_at();
        $message = new Message();
        $message_images = new Message();
        $user_message = new UserMessage();
        $rcv_id =  $request->to;
        $for = $request->for;
        $seen = $request->has('seen') ? $request->seen : 0;
        $haveImages = $request->has('images') ? true : false;
        $haveMessageText = !empty($request->message) ? true : false;
        $parent_id = $request->parent_id == 'null' || $request->parent_id == null ? NULL : (int) $request->parent_id;
        if ($request->type == 1) {
            if ($haveMessageText && !$haveImages) {
                $message->message = $request->message;
                $message->type = 1;
            } elseif ($haveImages && !$haveMessageText) {
                $images = $request->images;
                $arrayImages = [];
                foreach ($images as $image) {
                    $urlImgUploaded =  $image->storeOnCloudinary("user-" . Auth::id())->getSecurePath();
                    $arrayImages[] = $urlImgUploaded;
                }
                $message->message = implode(",", $arrayImages);
                $message->type = 2;
            } elseif ($haveImages && $haveMessageText) {
                $message->message = $request->message;
                $message->type = 1;
                $images = $request->images;
                $arrayImages = [];
                foreach ($images as $image) {
                    $urlImgUploaded =  $image->storeOnCloudinary("user-" . Auth::id())->getSecurePath();
                    $arrayImages[] = $urlImgUploaded;
                }
                $message_images->message = implode(",", $arrayImages);
                $message_images->parent_id =  $parent_id;
                $message_images->type = 2;
            }
        }
        if ($request->type == 3) {
            $audio = $request->audio;
            $urlAudioUploaded =  $audio->storeOnCloudinary("user-" . Auth::id())->getSecurePath();
            $message->message = $urlAudioUploaded;
            $message->type = 3;
        }
        $message->created_at =  $created_time;
        $user_message =  $this->dav2_messages->store_message($rcv_id, $message->message, $message->type, $parent_id, $seen, $for);
        if ($user_message) {
            try {
                if ($haveMessageText && $haveImages) {
                    $user_message_images =   $this->dav2_messages->store_message($rcv_id, $message_images->message, $message_images->type, $parent_id, $seen, $for);
                    $user_message->message_images = $user_message_images;
                }
                if ($request->for == 0) {
                    broadcast(new SendMessage($user_message))->toOthers();
                } else {
                    broadcast(new SendMessageGroup($user_message))->toOthers();
                }
                return response()->json(['data' => $user_message], 200);
            } catch (\Exception $e) {
                $message->delete();
                $message_images->delete();
                return response()->json(['error' => $e->getMessage()]);
            }
        } else {
            $message->delete();
            return response()->json(['error' => "L???i l??u d??? li???u"], 500);
        }
    }
    public function storeReaction(Request $request)
    {
        $action = $request->action;
        $type = $request->type;
        $rcv_id = $request->rcvId;
        $reactionIcon = $request->has('reaction') ? $request->reaction : null;
        $msg_id = $request->msgId;
        $reaction = new ReactionMessage();
        try {
            if ($action == "store") {
                $reaction->reaction = $reactionIcon;
                $reaction->users_id = Auth::id();
                $reaction->message_id = $msg_id;
                $existReaction = ReactionMessage::with('user')->where('message_id', $msg_id)->where('users_id', Auth::id())->first();
                if (!$existReaction) {
                    $existReaction = $reaction->save();
                } else {
                    ReactionMessage::where('message_id', $msg_id)->where('users_id', Auth::id())->update(['reaction' => $reactionIcon]);
                }
            }
            if ($action == "delete") {
                ReactionMessage::where('message_id', $msg_id)->where('users_id', Auth::id())->delete();
            }
            $data = new stdClass();
            $data->action = $action;
            if ($type == 0) {
                $data->message = UserMessage::with(['message', 'message_parent', 'message.reaction', 'message.reaction.user'])->where('msg_id', $msg_id)->first();
            } else {
                $data->message = UserMessage::with(['message', 'message_parent', 'message.reaction', 'message.reaction.user', 'sender'])->where('msg_id', $msg_id)->first();
            }
            $data->created_at =  $this->dav2_messages->format_created_at($data->message->created_at);
            $event = "reaction.message";
            if ($type == 0) {
                $channel = "chat-" . $rcv_id;
            } else {
                $channel = "group-chat-" . $rcv_id;
            }
            broadcast(new CustomEvent($data, $event, $channel))->toOthers();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => "L???i x??? l?? d??? li???u"], 500);
        }
    }
    public function message_delete_reaction(Request $request)
    {
        $type = $request->type;
        $rcv_id = $request->rcvId;
        $msg_id = $request->msgId;
        $deleted = ReactionMessage::where('message_id', $msg_id)->where('users_id', Auth::id())->delete();
        if ($deleted) {
            $data = new stdClass();
            $data->message = UserMessage::with(['message', 'message_parent', 'message.reaction', 'message.reaction.user'])->where('msg_id', $msg_id)->first();
            $data->created_at =  $this->dav2_messages->format_created_at($data->message->created_at);
            $data->reaction = ReactionMessage::with('user')->where('message_id', $msg_id)->get();
            $event = "reaction.message.delete";
            if ($type == 0) {
                $channel = "chat-" . $rcv_id;
            } else {
                $channel = "group-chat-" . $rcv_id;
            }
            broadcast(new CustomEvent($data, $event, $channel))->toOthers();
            return response()->json($data);
        }
        return response()->json(['error' => "G??? c???m x??c th???t b???i"], 500);
    }
    public function create__room__call__chat(Request $request)
    {
        $to = (int) $request->to;
        try {
            $room = new RoomCallChat();
            $room->from = Auth::id();
            $room->to = $to;
            $room->save();
            return response()->json($room, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => "T???o call chat th???t b???i"], 500);
        }
    }
}
