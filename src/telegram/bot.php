<?php

class TelegramBot {
	public $data,
		   $token,
		   $final,
		   $results = array(),
		   $sents = array(),
		   $save = true,
		   $last,
		   $parser = true,
		   $notresponse = false,
		   $autoaction = false,
		   $handle = false,
		   $objective = true;
	
	const KEYBOARD = 'keyboard';
	const INLINE_KEYBOARD = 'inline_keyboard';
	const REMOVE_KEYBOARD = 'remove_keyboard';
	const FORCE_REPLY = 'force_reply';
	const RESIZE_KEYBOARD = 'resize_keyboard';
	const BTN_TEXT = 'text';
	const BTN_URL = 'url';
	const BTN_DATA = 'callback_data';
	const BTN_SWITCH = 'switch_inline_query';
	const BTN_SWITCH_CURRENT = 'switch_inline_query_current_chat';
	const HTML = 'HTML';
	const MARK_DOWN = 'MarkDown';

	public function setToken($token = ''){
		$this->last = $this->token;
		$this->token = $token;
		return $this;
	}
	public function backToken(){
		$token = $this->token;
		$this->token = $this->last;
		$this->last = $token;
		return $this;
	}
	public function __construct($token = ''){
		$this->token = $token;
	}
	public function isTelegram(){
		return net::ipcheck('
			149.154.160-175.*  # LLC GLOBALNET (United Kingdom)
			149.154.164-167.*  # Telegram Messenger Network (United Kingdom)
			91.108.4-7,56-59.* # Telegram Messenger Network (Netherlands)
			91.108.8-11.*      # LLC GLOBALNET (Netherlands)
		', getenv('REMOTE_ADDR'));
	}
	public function checkTelegram(){
		if(!$this->isTelegram())
			exit;
	}
	public function update($offset = -1, $limit = 1, $timeout = 0){
		if(isset($this->data) && apeip::$PUT)return $this->data;
		elseif($this->data = apeip::$PUT)return $this->data = aped::jsondecode($this->data, !$this->objective);
		else $res = $this->data = $this->request("getUpdates", array(
			"offset" => $offset,
			"limit" => $limit,
			"timeout" => $timeout
		), 3);
		return $res;
	}
	public function dataUpdate(){
		return $this->data ? $this->data : $this->update();
	}
	public function request($method, $args = array(), $level = 3){
		if(isset($args['parse_args'])){
			if($args['parse_args'])
				$args = $this->parse_args($method, $args);
			unset($args['parse_args']);
		}elseif($this->parser)
			$args = $this->parse_args($method, $args);
		$res = false;
		$func = $this->handle;
		if($this->autoaction && isset($args['chat_id'])) {
			switch(strtolower($method)) {
			case "sendmessage":
				$action = "typing";
			break;
			case "sendphoto":
				$action = "upload_photo";
			break;
			case "sendvoice":
				$action = "record_audio";
			break;
			case "sendvideo":
				$action = "upload_video";
			break;
			case "sendvideonote":
				$action = "uplaod_video_note";
			break;
			case "sendaudio":
				$action = "upload_audio";
			break;
			case "senddocument":
				$action = "upload_document";
			break;
			default:
				$action = false;
			}
			if($action)
				$this->request("sendChatAction", array(
					"chat_id" => $args['chat_id'],
					"action" => $action,
					"parse_args" => false
				));
		}
		if($level == 1) {
			$args['method'] = $method;
			print aped::jsonencode($args);
			ob_flush();
			$res = true;
		}elseif($level == 2) {
			$res = @fopen("https://api.telegram.org/bot{$this->token}/$method?" . http_build_query($args), 'r');
			if($res)fclose($res = true);
			else $res = false;
		}elseif($level == 3) {
			$c = curl_init("https://api.telegram.org/bot{$this->token}/$method");
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, $args);
			$res = aped::jsondecode(curl_exec($c), !$this->objective);
			curl_close($c);
		}elseif($level == 4) {
			$sock = fsockopen('tls://api.telegram.org', 443);
			$query = http_build_query($args);
			fwrite($sock, "GET /bot{$this->token}/$method?$query HTTP/1.1\r\nHost: api.telegram.org\r\nConnection: close\r\nContent-length: 0\r\n\r\n");
			$res = true;
		}else return false;
		$args['method'] = $method;
		$args['level'] = $level;
		if($this->save) {
			$this->sents[] = $args;
			$this->results[] = $this->final = $res;
		}
		if($func && ($res === false || $res))
			$func($this->objective ? (object)array(
				"method" => $method,
				"arguments" => $args,
				"level" => $level,
				"result" => $res,
			) : array(
				"method" => $method,
				"arguments" => $args,
				"level" => $level,
				"result" => $res,
			));
		if($res === false)return false;
		if($res === true)return true;
		if(!isset($res->ok)) {
			$server = array_value(array("OUTPUT", "api.telegram.org", "api.telegram.org"), $level - 1);
			new APError("TelegramBot", "can not Connect to $server", APError::NETWORK);
			return false;
		}elseif(!$res->ok) {
			new APError("TelegramBot", "$res->description [$res->error_code]", APError::NOTIC);
			return $res;
		}
		return $res;
	}
	public function reset(){
		$this->final = null;
		$this->results = array();
		$this->sents = array();
		$this->data = null;
	}
	public function close(){
		$this->__destruct();
	}
	public function __destruct(){
		$this->final = null;
		$this->results = null;
		$this->sents = null;
		$this->data = null;
		$this->token = null;
		if($this->notresponse){
			$notr = $this->notresponse;
			$notr();
		}
	}
	public function sendMessage($chat, $text, $args = array(), $level = 3){
		if(strlen($text) > 4096){
			$args['chat_id'] = $chat;
			if($this->parser){
				$args = $this->parse_args($args);
				$args['parse_args'] = false;
			}
			$texts = str_split($text, 4096);
			foreach($texts as $text) {
				$args['text'] = $text;
				$this->request("sendMessage", $args, $level);
			}
			return true;
		}
		$args['chat_id'] = $chat;
		$args['text'] = $text;
		return $this->request("sendMessage", $args, $level);
	}
	public function sendAction($chat, $action, $level = 3){
		return $this->request("sendChatAction", array(
			"chat_id" => $chat,
			"action" => $action,
			"parse_args" => false
		), $level);
	}
	public function sendTyping($chat, $level = 3){
		return $this->request("sendChatAction", array(
			"chat_id" => $chat,
			"action" => "typing",
			"parse_args" => false
		), $level);
	}
	public function setWebhook($url = '', $args = array(), $level = 3){
		$args['url'] = $url ? $url : '';
		return $this->request("setWebhook", $args, $level);
	}
	public function deleteWebhook($level = 3){
		return $this->request("setWebhook", array("parse_args" => false), $level);
	}
	public function getChat($chat, $level = 3){
		return $this->request("getChat", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function getMembersCount($chat, $level = 3){
		return $this->request("getChatMembersCount", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function getMember($chat, $user, $level = 3){
		return $this->request("getChatMember", array(
			"chat_id" => $chat,
			"user_id" => $user,
			"parse_args" => false
		), $level);
	}
	public function getProfile($user, $level = 3){
		$args['user_id'] = $user;
		$args['chat_id'] = $user;
		return $this->request("getUserProfilePhotos", $args, $level);
	}
	public function banMember($chat, $user, $time = false, $level = 3){
		$args = array("chat_id" => $chat, "user_id" => $user);
		if($time)$args['until_date'] = $time;
		return $this->request("kickChatMember", $args, $level);
	}
	public function unbanMember($chat, $user, $level = 3){
		return $this->request("unbanChatMember", array(
			"chat_id" => $chat,
			"user_id" => $user,
			"parse_args" => false
		), $level);
	}
	public function kickMember($chat, $user, $level = 3){
		return array($this->banMember($chat, $user, $level), $this->unbanMember($chat, $user, $level));
	}
	public function getMe($level = 3){
		return $this->request("getMe", array("parse_args" => false), $level);
	}
	public function getWebhook($level = 3){
		return $this->request("getWebhookInfo", array("parse_args" => false), $level);
	}
	public function resrictMember($chat, $user, $args, $time = false, $level = 3){
		foreach($args as $key => $val)$args["can_$key"] = $val;
		$args['chat_id'] = $chat;
		$args['user_id'] = $user;
		if($time)$args['until_date'] = $time;
		return $this->request("resrictChatMember", $args, $level);
	}
	public function promoteMember($chat, $user, $args = array(), $level = 3){
		foreach($args as $key => $val)$args["can_$key"] = $val;
		$args['chat_id'] = $chat;
		$args['user_id'] = $user;
		return $this->request("promoteChatMember", $args, $level);
	}
	public function exportInviteLink($chat, $level = 3){
		$this->request("exportChatInviteLink", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function setChatPhoto($chat, $photo, $level = 3){
		return $this->request("setChatPhoto", array(
			"chat_id" => $chat,
			"photo" => $photo,
			"parse_args" => false
		), $level);
	}
	public function deleteChatPhoto($chat, $level = 3){
		return $this->request("deleteChatPhoto", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function setTitle($chat, $title, $level = 3){
		return $this->request("setChatTitle", array(
			"chat_id" => $chat,
			"title" => $title,
			"parse_args" => false
		), $level);
	}
	public function setDescription($chat, $description, $level = 3){
		return $this->request("setChatDescription", array(
			"chat_id" => $chat,
			"description" => $description,
			"parse_args" => false
		), $level);
	}
	public function pinMessage($chat, $message, $disable = false, $level = 3){
		return $this->request("pinChatMessage", array(
			"chat_id" => $chat,
			"message_id" => $message,
			"disable_notification" => $disable,
			"parse_args" => false
		), $level);
	}
	public function unpinMessage($chat, $level = 3){
		return $this->request("unpinChatMessage", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function leaveChat($chat, $level = 3){
		return $this->request("leaveChat", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function getAdmins($chat, $level = 3){
		return $this->request("getChatAdministrators", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function setChatStickerSet($chat, $sticker, $level = 3){
		return $this->request("setChatStickerSet", array(
			"chat_id" => $chat,
			"sticker_set_name" => $sticker,
			"parse_args" => false
		), $level);
	}
	public function deleteChatStickerSet($chat, $level = 3){
		return $this->request("deleteChatStickerSet", array(
			"chat_id" => $chat,
			"parse_args" => false
		), $level);
	}
	public function answerCallback($id, $text, $args = array(), $level = 3){
		$args['callback_query_id'] = $id;
		$args['text'] = $text;
		return $this->request("answerCallbackQuery", $args, $level);
	}
	public function editText($text, $args = array(), $level = 3){
		$args['text'] = $text;
		return $this->request("editMessageText", $args, $level);
	}
	public function editMessageText($chat, $msg, $text, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['message_id'] = $msg;
		$args['text'] = $text;
		return $this->request("editMessageText", $args, $level);
	}
	public function editInlineText($msg, $text, $args = array(), $level = 3){
		$args['inline_message_id'] = $msg;
		$args['text'] = $text;
		return $this->request("editMessageText", $args, $level);
	}
	public function editCaption($caption, $args = array(), $level = 3){
		$args['caption'] = $caption;
		return $this->request("editMessageCaption", $args, $level);
	}
	public function editMessageCaption($chat, $msg, $caption, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$arsg['message_id'] = $msg;
		$args['caption'] = $caption;
		return $this->request("editMessageCaption", $args, $level);
	}
	public function editInlineCaption($msg, $caption, $args = array(), $level = 3){
		$arsg['inline_message_id'] = $msg;
		$args['caption'] = $caption;
		return $this->request("editMessageCaption", $args, $level);
	}
	public function editReplyMarkup($reply_makup, $args = array(), $level = 3){
		$args['reply_markup'] = $reply_markup;
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function editMessageReplyMarkup($chat, $msg, $reply_makup, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['message_id'] = $msg;
		$args['reply_markup'] = $reply_markup;
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function editInlineReplyMarkup($msg, $reply_makup, $args = array(), $level = 3){
		$args['inline_message_id'] = $msg;
		$args['reply_markup'] = $reply_markup;
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function editMedia($media, $args = array(), $level = 3){
		$args['media'] = $media;
		return $this->request("editMessageMedia",$args,$level);
	}
	public function editMessageMedia($chat, $message, $media, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['message_id'] = $message;
		$args['media'] = $media;
		return $this->request("editMessageMedia",$args,$level);
	}
	public function editInlineMedia($message, $media, $args = array(), $level = 3){
		$args['inline_message_id'] = $message;
		$args['media'] = $media;
		return $this->request("editMessageMedia",$args,$level);
	}
	public function editKeyboard($reply_makup, $args = array(), $level = 3){
		$args['reply_markup'] = is_array($reply_markup) ? isset($reply_markup['inline_keyboard']) ?
			$reply_markup : array("inline_keyboard" => $reply_markup) : $reply_markup;
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function editMessageKeyboard($chat, $msg, $reply_makup, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['message_id'] = $msg;
		$args['reply_markup'] = array("inline_keyboard" => $reply_markup);
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function editInlineKeyboard($msg, $reply_makup, $args = array(), $level = 3){
		$args['inline_message_id'] = $msg;
		$args['reply_markup'] = array("inline_keyboard" => $reply_markup);
		return $this->request("editMessageReplyMarkup", $args, $level);
	}
	public function deleteMessage($chat, $message, $level = 3){
		if(is_array($message)){
			$now = $this->getMessage();
			if($now === false)$now = 0;
			foreach($message as $msg)
				$this->request("deleteMessage", array(
					"chat_id"	 => $chat,
					"message_id" => $msg < 0 ? abs($now + $msg) : $msg,
					"parse_args" => false
				), $level);
			return true;
		}return $this->request("deleteMessage", array(
			"chat_id"	 => $chat,
			"message_id" => $message,
			"parse_args" => false
		), $level);
	}
	public function sendMedia($chat, $type, $file, $args = array(), $level = 3){
		$type = strtolower($type);
		if($type == "videonote")$type = "video_note";
		$args['chat_id'] = $chat;
		$args[$type] = $file;
		return $this->request("send" . str_replace('_', '', $type), $args, $level);
	}
	/*
	public function sendFile($chat, $file, $args = array(), $level = 3){
		$type = array_value(XNTelegram::botfileid_info($file), 'type');
		if(!$type)return false;
		$args['chat_id'] = $chat;
		$args[$type] = $file;
		return $this->request("send" . str_replace('_', '', $type), $args, $level);
	}
	*/
	public function getStickerSet($name, $level = 3){
		return $this->request("getStickerSet", array(
			"name" => $name,
			"parse_args" => false
		), $level);
	}
	public function sendDocument($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['document'] = $file;
		return $this->request("sendDocument", $args, $level);
	}
	public function sendPhoto($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['photo'] = $file;
		return $this->request("sendPhoto", $args, $level);
	}
	public function sendVideo($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['video'] = $file;
		return $this->request("sendVideo", $args, $level);
	}
	public function sendAudio($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['audio'] = $file;
		return $this->request("sendAudio", $args, $level);
	}
	public function sendVoice($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['voice'] = $file;
		return $this->request("sendVoice", $args, $level);
	}
	public function sendSticker($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['sticker'] = $file;
		return $this->request("sendSticker", $args, $level);
	}
	public function sendVideoNote($chat, $file, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['video_note'] = $file;
		return $this->request("sendVideoNote", $args, $level);
	}
	public function uploadStickerFile($user, $file, $level = 3){
		return $this->request("uploadStickerFile", array(
			"user_id" => $user,
			"png_sticker" => $file,
			"parse_args" => false
		), $level);
	}
	public function createNewStickerSet($user, $name, $title, $args = array(), $level = 3){
		$args['user_id'] = $user;
		$args['name'] = $name;
		$args['title'] = $title;
		return $this->request("createNewStickerSet", $args, $level);
	}
	public function addStickerToSet($user, $name, $args = array(), $level = 3){
		$args['user_id'] = $user;
		$args['name'] = $name;
		return $this->request("addStickerToSet", $args, $level);
	}
	public function setStickerPositionInSet($sticker, $position, $level = 3){
		return $this->request("setStickerPositionInSet", array(
			"sticker" => $sticker,
			"position" => $position,
			"parse_args" => false
		), $level);
	}
	public function deleteStickerFromSet($sticker, $level = 3){
		return $this->request("deleteStickerFromSet", array(
			"sticker" => $sticker,
			"parse_args" => false
		), $level);
	}
	public function answerInline($id, $results, $args = array(), $switch = array(), $level = 3){
		$args['inline_query_id'] = $id;
		$args['results'] = is_array($results) ? aped::jsonencode($results): $results;
		if($switch['text'])$args['switch_pm_text'] = $switch['text'];
		if($switch['parameter'])$args['switch_pm_parameter'] = $switch['parameter'];
		return $this->request("answerInlineQuery", $args, $level);
	}
	public function answerPreCheckout($id, $ok = true, $level = 3){
		if($ok === true)$args = array(
			"pre_checkout_query_id" => $id,
			"ok" => true,
			"parse_args" => false
		);
		else $args = array(
			"pre_checkout_query_id" => $id,
			"ok" => false,
			"error_message" => $ok,
			"parse_args" => false
		);
		return $this->request("answerPreCheckoutQuery", $args, $level);
	}
	public function setGameScore($user, $score, $args = array(), $level = 3){
		$args['user_id'] = $user;
		$args['score'] = $score;
		return $this->request("setGameScore", $args, $level);
	}
	public function getGameHighScores($user, $args = array(), $level = 3){
		$args['user_id'] = $user;
		return $this->request("getGameHighScores", $args, $level);
	}
	public function sendGame($chat, $name, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['name'] = $name;
		return $this->request("sendGame", $args, $level);
	}
	public function getFile($file, $level = 3){
		return $this->request("getFile", array(
			"file_id" => $file,
			"parse_args" => false
		), $level);
	}
	public function readFile($path){
		return fget("https://api.telegram.org/file/bot$this->token/$path");
	}
	public function copyFile($file, $dist){
		$file = $this->getFile($file)->result->file_path;
		return copy("https://api.telegram.org/file/bot$this->token/$path", $dist);
	}
	public function downloadFile($file){
		return $this->readFile($this->getFile($file)->result->file_path);
	}
	public function sendContact($chat, $phone, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['phone_number'] = $phone;
		return $this->request("sendContact", $args, $level);
	}
	public function sendVenue($chat, $latitude, $longitude, $title, $address, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['latitude'] = $latitude;
		$args['longitude'] = $longitude;
		$args['title'] = $title;
		$args['address'] = $address;
		return $this->request("sendVenue", $args, $level);
	}
	public function stopMessageLiveLocation($args, $level = 3){
		return $this->request("stopMessageLiveLocation", $args, $level);
	}
	public function editMessageLiveLocation($latitude, $longitude, $args = array(), $level = 3){
		$args['latitude'] = $latitude;
		$args['longitude'] = $longitude;
		return $this->request("editMessageLiveLocation", $args, $level);
	}
	public function sendLocation($chat, $latitude, $longitude, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['latitude'] = $latitude;
		$args['longitude'] = $longitude;
		$this->request("sendLocation", $args, $level);
	}
	public function sendMediaGroup($chat, $media, $args = array(), $level = 3){
		$args['chat_id'] = $chat;
		$args['media'] = aped::jsonencode($media);
		return $this->request("sendMediaGroup", $args, $level);
	}
	public function forwardMessage($chat, $from, $message, $args, $level = 3){
		$args['chat_id'] = $chat;
		$args['from_chat_id'] = $from;
		$args['message_id'] = $message;
		return $this->request("forwardMessage", $args, $level);
	}
	public function sendPoll($chat_id, $question, $options = array('Yes', 'No'), $args = array(), $level = 3){
		$args['chat_id'] = $chat_id;
		$args['question'] = $question;
		$args['options'] = $options;
		return $this->request("sendPoll", $args, $level);
	}
	public function stopPoll($chat_id, $message_id, $args = array(), $level = 3){
		$args['chat_id'] = $chat_id;
		$args['message_id'] = $message_id;
		return $this->request("stopPoll", $args, $level);
	}
	public function updateType($update = false){
		if(!$update)$update = $this->lastUpdate();
		if($this->objective)
			if(isset($update->message))return "message";
			elseif(isset($update->callback_query))return "callback_query";
			elseif(isset($update->chosen_inline_result))return "chosen_inline_result";
			elseif(isset($update->inline_query))return "inline_query";
			elseif(isset($update->channel_post))return "channel_post";
			elseif(isset($update->edited_message))return "edited_message";
			elseif(isset($update->edited_channel_post))return "edited_channel_post";
			elseif(isset($update->shipping_query))return "shipping_query";
			elseif(isset($update->pre_checkout_query))return "pre_checkout_query";
			elseif(isset($update->poll))return "poll";
			else return "unknown";
		else
			if(isset($update['message']))return "message";
			elseif(isset($update['callback_query']))return "callback_query";
			elseif(isset($update['chosen_inline_result']))return "chosen_inline_result";
			elseif(isset($update['inline_query']))return "inline_query";
			elseif(isset($update['channel_post']))return "channel_post";
			elseif(isset($update['edited_message']))return "edited_message";
			elseif(isset($update['edited_channel_post']))return "edited_channel_post";
			elseif(isset($update['shipping_query']))return "shipping_query";
			elseif(isset($update['pre_checkout_query']))return "pre_checkout_query";
			elseif(isset($update['poll']))return "poll";
		return "unknown";
	}
	public function updateInType($update = false){
		if(!$update)$update = $this->lastUpdate();
		if($this->objective)
			if(isset($update->message))return $update->message;
			elseif(isset($update->callback_query))return $update->callback_query;
			elseif(isset($update->chosen_inline_result))return $update->chosen_inline_result;
			elseif(isset($update->inline_query))return $update->inline_query;
			elseif(isset($update->channel_post))return $update->channel_post;
			elseif(isset($update->edited_message))return $update->edited_message;
			elseif(isset($update->edited_channel_post))return $update->edited_channel_post;
			elseif(isset($update->shipping_query))return $update->shipping_query;
			elseif(isset($update->pre_checkout_query))return $update->pre_checkout_query;
			elseif(isset($update->poll))return $update->poll;
			else return "unknown";
		else
			if(isset($update['message']))return $update['message'];
			elseif(isset($update['callback_query']))return $update['callback_query'];
			elseif(isset($update['chosen_inline_result']))return $update['chosen_inline_result'];
			elseif(isset($update['inline_query']))return $update['inline_query'];
			elseif(isset($update['channel_post']))return $update['channel_post'];
			elseif(isset($update['edited_message']))return $update['edited_message'];
			elseif(isset($update['edited_channel_post']))return $update['edited_channel_post'];
			elseif(isset($update['shipping_query']))return $update['shipping_query'];
			elseif(isset($update['pre_checkout_query']))return $update['pre_checkout_query'];
			elseif(isset($update['poll']))return $update['poll'];
		return "unknown";
	}
	public function readUpdates($func, $while = 0, $limit = 1, $timeout = 0){
		if($while == 0)$while = -1;
		$offset = 0;
		if($this->objective)
			while($while > 0 || $while < 0) {
				$updates = $this->update($offset, $limit, $timeout);
				if(isset($updates->message_id)) {
					if($offset == 0)$updates = (object)array("result" => array($updates));
					else return;
				}
				if(isset($updates->result)) {
					foreach($updates->result as $update) {
						$offset = $update->update_id + 1;
						if($func($update))return true;
					}
					--$while;
				}
			}
		else
			while($while > 0 || $while < 0) {
				$updates = $this->update($offset, $limit, $timeout);
				if(isset($updates['message_id'])) {
					if($offset == 0)$updates = array("result" => array($updates));
					else return;
				}
				if(isset($updates['result'])) {
					foreach($updates['result'] as $update) {
						$offset = $update['update_id'] + 1;
						if($func($update))return true;
					}
					--$while;
				}
			}
	}
	public function filterUpdates($filter = array(), $func = false){
		if(in_array($this->updateType(), $filter)) {
			if($func)$func($this->data);
			exit();
		}
	}
	public function unfilterUpdates($filter = array(), $func = false){
		if(!in_array($this->updateType(), $filter)) {
			if($func)$func($this->data);
			exit();
		}
	}
	public function getUser($update = false){
		$update = $this->updateInType($update);
		if(!$update)return false;
		if($this->objective){
			if(isset($update->message))return (object)array('chat' => $update->message->chat, 'from' => $update->message->from);
			if(isset($update->chat))return (object)array('chat' => $update->chat, 'from' => $update->from);
			if(isset($update->from))return (object)array('chat' => $update->from, 'from' => $update->from);
		}else{
			if(isset($update['message']))return array('chat' => $update['message']['chat'], 'from' => $update['message']['from']);
			if(isset($update['chat']))return array('chat' => $update['chat'], 'from' => $update['from']);
			if(isset($update['from']))return array('chat' => $update['from'], 'from' => $update['from']);
		}
		return false;
	}
	public function getMessage($update = false){
		$update = $this->updateInType($update);
		if(!$update)return false;
		if($this->objective){
			if(isset($update->message_id))return $update->message_id;
			if(isset($update->message))return $update->message->message_id;
		}else{
			if(isset($update['message_id']))return $update['message_id'];
			if(isset($update['message']))return $update['message']['message_id'];
		}
		return false;
	}
	public function getDate($update = false){
		$update = $this->updateInType($update);
		if(!$update)return false;
		if($this->objective){
			if(isset($update->date))return $update->date;
			if(isset($update->message))return $update->message->date;
		}else{
			if(isset($update['date']))return $update['date'];
			if(isset($update['message']))return $update['message']['date'];
		}
		return false;
	}
	public function getData($update = false){
		$update = $this->updateInType($update);
		if(!$update)return false;
		if($this->objective){
			if(isset($update->text))return $update->text;
			if(isset($update->query))return $update->query;
			if(isset($update->caption))return $update->caption;
		}else{
			if(isset($update['text']))return $update['text'];
			if(isset($update['query']))return $update['query'];
			if(isset($update['caption']))return $update['caption'];
		}
		return false;
	}
	public function isChat($user, $update = false){
		$chat = $this->getUser($update)->chat->id;
		if(is_array($user) && in_array($chat, $user))return true;
		elseif($user == $chat)return true;
		return false;
	}
	public function lastUpdate(){
		$update = $this->update();
		if($this->objective){
			if(isset($update->update_id))return $update;
			if(isset($update->result[0]->update_id))return $update->result[0];
		}else{
			if(isset($update['update_id']))return $update;
			if(isset($update['result'][0]['update_id']))return $update['result'][0];
		}
		return array();
	}
	public function getUpdates(){
		$update = $this->update(0, 999999999999, 0);
		if($this->objective){
			if(isset($update->update_id))return array($update);
			if($update->result[0]->update_id)return $update->result;
		}else{
			if(isset($update['update_id']))return array($update);
			if($update['result'][0]['update_id'])return $update['result'];
		}
		return array();
	}
	public function lastUpdateId($update = false){
		if(!$update)$update = $this->update(-1, 1, 0);
		if($this->objective){
			if($update->result[0]->update_id){
				$update = end($update->result);
				return $update->update_id;
			}if(isset($update->update_id))return $update->update_id;
		}else{
			if($update->result[0]->update_id){
				$update = end($update['result']);
				return $update['update_id'];
			}if(isset($update['update_id']))return $update['update_id'];
		}
		return 0;
	}
	public function fileType($message = false){
		if($this->objective){
			if(!$message){
				$message = $this->lastUpdate();
				if(!isset($message->message))return false;
				$message = $message->message;
			}elseif(isset($message->message))
				$message = $message->message;
			if(isset($message->photo))return "photo";
			if(isset($message->voice))return "voice";
			if(isset($message->audio))return "audio";
			if(isset($message->video))return "video";
			if(isset($message->sticker))return "sticker";
			if(isset($message->document))return "document";
			if(isset($message->video_note))return "videonote";
			if(isset($message->thumb_nail))return "thumb_nail";
		}else{
			if(!$message){
				$message = $this->lastUpdate();
				if(!isset($message['message']))return false;
				$message = $message['message'];
			}elseif(isset($message['message']))
				$message = $message['message'];
			if(isset($message['photo']))return "photo";
			if(isset($message['voice']))return "voice";
			if(isset($message['audio']))return "audio";
			if(isset($message['video']))return "video";
			if(isset($message['sticker']))return "sticker";
			if(isset($message['document']))return "document";
			if(isset($message['video_note']))return "videonote";
			if(isset($message['thumb_nail']))return "thumb_nail";
		}
		return false;
	}
	public function fileInfo($message = false){
		if($this->objective){
			if(!$message){
				$message = $this->lastUpdate();
				if(!isset($message->message))return false;
				$message = $message->message;
			}elseif(isset($message->message))
					$message = $message->message;
			if(isset($message->photo))return end($message->photo);
			if(isset($message->voice))return $message->voice;
			if(isset($message->audio))return $message->audio;
			if(isset($message->video))return $message->video;
			if(isset($message->sticker))return $message->sticker;
			if(isset($message->document))return $message->document;
			if(isset($message->video_note))return $message->video_note;
			if(isset($message->thumb_nail))return $message->thumb_nail;
		}else{
			if(!$message){
				$message = $this->lastUpdate();
				if(!isset($message['message']))return false;
				$message = $message['message'];
			}elseif(isset($message['message']))
					$message = $message['message'];
			if(isset($message['photo']))return end($message['photo']);
			if(isset($message['voice']))return $message['voice'];
			if(isset($message['audio']))return $message['audio'];
			if(isset($message['video']))return $message['video'];
			if(isset($message['sticker']))return $message['sticker'];
			if(isset($message['document']))return $message['document'];
			if(isset($message['video_note']))return $message['video_note'];
			if(isset($message['thumb_nail']))return $message['thumb_nail'];
		}
		return false;
	}
	public function isFile($message = false){
		if($this->objective){
			if(!$message){
				$message = $this->lastUpdate();
				if(!isset($message->message))return false;
				$message = $message->message;
			}elseif(isset($message->message))
					$message = $message->message;
			return !isset($message->text);
		}
		if(!$message){
			$message = $this->lastUpdate();
			if(!isset($message['message']))return false;
			$message = $message['message'];
		}elseif(isset($message['message']))
				$message = $message['message'];
		return !isset($message['text']);
	}
	public function convertFile($chat, $file, $name, $type = "document", $level = 3){
		if(file_exists($name))$read = fput($name);
		else $read = false;
		file_put_contents($name, $this->downloadFile($file, $level));
		$r = $this->sendMedia($chat, $type, new CURLFile($name), $level);
		if($read !== false)fput($name, $read);
		else unlink($name);
		return $r;
	}
	public function sendUpdate($url, $update = false){
		if($update === false)$update = $this->dataUpdate();
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($c, CURLOPT_POSTFIELDS, $update);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$r = curl_exec($c);
		curl_close($c);
		return $r;
	}
	public function requestFromUpdate($chat, $update = false, $args = array(), $level = 3){
		if($this->objective){
			if(!$update){
				$update = $this->lastUpdate();
				if(!isset($update->message))return false;
				$update = $update->message;
			}elseif(isset($update->message))
					$update = $update->message;
			if(isset($update->photo)){$method = 'sendPhoto';$obj = $update->photo;}
			elseif(isset($update->video)){$method = 'sendVideo';$obj = $update->video;}
			elseif(isset($update->voice)){$method = 'sendVoice';$obj = $update->voice;}
			elseif(isset($update->audio)){$method = 'sendAudio';$obj = $update->audio;}
			elseif(isset($update->video_note)){$method = 'sendVideoNote';$obj = $update->video_note;}
			elseif(isset($update->sticker)){$method = 'sendSticker';$obj = $update->sticker;}
			elseif(isset($update->document)){$method = 'sendDocument';$obj = $update->document;}
			elseif(isset($update->text)){$method = 'sendMessage';$obj = $update;}
			elseif(isset($update->contact)){$method = 'sendContact';$obj = $update->contact;}
			elseif(isset($update->location)){$method = 'sendLocation';$obj = $update->location;}
			elseif(isset($update->venue)){$method = 'sendVenue';$obj = $update->venue;}
			else return false;
			if(isset($update->caption))$args['caption'] = isset($args['caption']) ? $args['caption'] : $update->caption;
			if($chat !== '' && $chat !== 'chat')$args['chat'] = $chat;
			elseif($chat === 'from')$args['chat'] = $update->from->id;
			else $args['chat'] = $update->chat->id;
			$args = $this->parse_args($method, $args);
			$args['file_id'] = isset($args['file_id']) ? $args['file_id'] : $obj->file_id;
			if($method == 'sendContact'){
				$args['phone_number'] = isset($args['phone_number']) ? $args['phone_number'] : $obj->phone_number;
				$args['first_name'] = isset($args['first_name'])? $args['first_name'] : $obj->first_name;
				$args['last_name'] = isset($args['last_name']) ? $args['last_name'] : (isset($update->last_name) ? $update->last_name : false);
				if($args['last_name'] === false)unset($args['last_name']);
			}elseif($method == 'sendLocation'){
				$args['latitude'] = isset($args['latitude']) ? $args['latitude'] : $obj->latitude;
				$args['longitude'] = isset($args['longitude']) ? $args['longitude'] : $obj->longitude;
			}elseif($method == 'sendVenue'){
				$args['latitude'] = isset($args['latitude']) ? $args['latitude'] : $obj->latitude;
				$args['longitude'] = isset($args['longitude']) ? $args['longitude'] : $obj->longitude;
				$args['address'] = isset($args['address']) ? $args['address'] : $obj->address;
				$args['title'] = isset($args['title']) ? $args['title'] : $obj->title;
			}
		}else{
			if(!$update){
				$update = $this->lastUpdate();
				if(!isset($update['message']))return false;
				$update = $update['message'];
			}elseif(isset($update['message']))
					$update = $update['message'];
			if(isset($update['photo'])){$method = 'sendPhoto';$obj = $update['photo'];}
			elseif(isset($update['video'])){$method = 'sendVideo';$obj = $update['video'];}
			elseif(isset($update['voice'])){$method = 'sendVoice';$obj = $update['voice'];}
			elseif(isset($update['audio'])){$method = 'sendAudio';$obj = $update['audio'];}
			elseif(isset($update['video_note'])){$method = 'sendVideoNote';$obj = $update['video_note'];}
			elseif(isset($update['sticker'])){$method = 'sendSticker';$obj = $update['sticker'];}
			elseif(isset($update['document'])){$method = 'sendDocument';$obj = $update['document'];}
			elseif(isset($update['text'])){$method = 'sendMessage';$obj = $update;}
			elseif(isset($update['contact'])){$method = 'sendContact';$obj = $update['contact'];}
			elseif(isset($update['location'])){$method = 'sendLocation';$obj = $update['location'];}
			elseif(isset($update['venue'])){$method = 'sendVenue';$obj = $update['venue'];}
			else return false;
			if(isset($update['caption']))$args['caption'] = isset($args['caption']) ? $args['caption'] : $update['caption'];
			if($chat !== '' && $chat !== 'chat')$args['chat'] = $chat;
			elseif($chat === 'from')$args['chat'] = $update['from']['id'];
			else $args['chat'] = $update['chat']['id'];
			$args = $this->parse_args($method, $args);
			$args['file_id'] = isset($args['file_id']) ? $args['file_id'] : $obj['file_id'];
			if($method == 'sendContact'){
				$args['phone_number'] = isset($args['phone_number']) ? $args['phone_number'] : $obj['phone_number'];
				$args['first_name'] = isset($args['first_name'])? $args['first_name'] : $obj['first_name'];
				$args['last_name'] = isset($args['last_name']) ? $args['last_name'] : (isset($update['last_name']) ? $update['last_name'] : false);
				if($args['last_name'] === false)unset($args['last_name']);
			}elseif($method == 'sendLocation'){
				$args['latitude'] = isset($args['latitude']) ? $args['latitude'] : $obj['latitude'];
				$args['longitude'] = isset($args['longitude']) ? $args['longitude'] : $obj['longitude'];
			}elseif($method == 'sendVenue'){
				$args['latitude'] = isset($args['latitude']) ? $args['latitude'] : $obj['latitude'];
				$args['longitude'] = isset($args['longitude']) ? $args['longitude'] : $obj['longitude'];
				$args['address'] = isset($args['address']) ? $args['address'] : $obj['address'];
				$args['title'] = isset($args['title']) ? $args['title'] : $obj['title'];
			}
		}
		return $this->requset($method, $args, $level);
	}
	public function parse_args($method, $args = array()){
		$method = strtolower($method);
		array_key_alias($args, 'user_id', 'user');
		array_key_alias($args, 'chat_id', 'chat', 'peer');
		array_key_alias($args, 'message_id', 'message', 'msg', 'msg_id');
		if(!isset($args['chat_id']))
			array_key_alias($args, 'inline_message_id', 'message_id');
		if($method == 'answercallbackquery')
			array_key_alias($args, 'callback_query_id', 'id');
		elseif($method == 'answerinlinequery')
			array_key_alias($args, 'inline_query_id', 'id');
		elseif(isset($args['id']))
			unset($args['id']);
		array_key_alias($args, 'show_alert', 'alert');
		array_key_alias($args, 'parse_mode', 'parse', 'mode');
		array_key_alias($args, 'reply_markup', 'markup');
		array_key_alias($args, 'reply_to_message_id', 'reply_to_message', 'reply_to_msg_id', 'reply_to_msg', 'reply_to', 'reply');
		array_key_alias($args, 'from_chat_id', 'from_chat');
		array_key_alias($args, 'phone_number', 'phone');
		if(isset($args['allowed_updates']) && (is_array($args['allowed_updates']) || is_object($args['allowed_updates'])))
			$args['allowed_updates'] = aped::jsonencode($args['allowed_updates']);
		if(isset($args['reply_markup']) && is_string($args['reply_markup']) && $this->menu->exists($args['reply_markup']))
			$args['reply_markup'] = $this->menu->get($args['reply_markup']);
		if(isset($args['reply_markup']) && (is_array($args['reply_markup']) || is_object($args['reply_markup'])))
			$args['reply_markup'] = aped::jsonencode($args['reply_markup']);
		switch($method){
			case 'getFile':
				array_key_alias($args, 'file_id', 'file');
			break;
			default:
				switch($method){
					case 'sendphoto': $argname = 'photo_id'; break;
					case 'sendaudio': $argname = 'audio_id'; break;
					case 'sendvideo': $argname = 'video_id'; break;
					case 'sendvoice': $argname = 'voice_id'; break;
					case 'sendsticker': $argname = 'sticker_id'; break;
					case 'senddocuement': $argname = 'document_id'; break;
					case 'sendvideonote': $argname = 'video_note_id'; break;
					default: break 2;
				}
				array_key_alias($args, 'file', $argname, 'file_id');
				if(isset($args['file'])){
					$file = $args['file'];
					unset($args['file']);
				}else break;
				if(is_string($file) && file_exists($file))
					$file = new CURLFile($file);
				$args[$argname] = $file;
		}
		$user = $this->getUser();
		if($user !== false){
			if(isset($args['chat_id']) && ($args['chat_id'] == 'chat' || $args['chat_id'] === ''))
				$args['chat_id'] = $this->getUser()->chat->id;
			elseif(isset($args['chat_id']) && $args['chat_id'] == 'user')
				$args['chat_id'] = $this->getUser()->from->id;
			if(isset($args['from_chat_id']) && ($args['from_chat_id'] == 'chat' || $args['from_chat_id'] === ''))
				$args['from_chat_id'] = $this->getUser()->chat->id;
			elseif(isset($args['from_chat_id']) && $args['from_chat_id'] == 'user')
				$args['from_chat_id'] = $this->getUser()->from->id;
			if(isset($args['user_id']) && $args['user_id'] == 'chat')
				$args['user_id'] = $this->getUser()->chat->id;
			elseif(isset($args['user_id']) && ($args['user_id'] == 'user' || $args['user_id'] === ''))
				$args['user_id'] = $this->getUser()->from->id;
		}$msg = $this->getMessage();
		if($msg !== false){
			if(isset($args['message_id']) && ($args['message_id'] == 'message' || $args['message_id'] === ''))
				$args['message_id'] = $msg;
			if(isset($args['reply_to_message_id']) && ($args['reply_to_message_id'] == 'message' || $args['reply_to_message_id'] === ''))
				$args['reply_to_message_id'] = $msg;
		}return $args;
	}
}

?>
