<?php

class TelegramWeb {
	public static function getMessage($chat, $message){
		if(@$chat[0] == '@')$chat = substr($chat, 1);
		try {
			$g = file_get_contents("https://t.me/$chat/$message?embed=1");
			$x = new DOMDocument;
			@$x->loadHTML($g);
			$x = @new DOMXPath($x);
			$path = "//div[@class='tgme_widget_message_bubble']";
			$enti = array_value($x->query("$path//div[@class='tgme_widget_message_text']"), 0);
			$entities = array();
			$last = 0;
			$pos = false;
			$line = 0;
			$entit = new DOMDocument;
			$entit->appendChild($entit->importNode($enti, true));
			$text = trim(html_entity_decode(strip_tags(str_replace('<br/>', "\n", $entit->saveXML()))));
			$tmp = new DOMXPath($entit);
			foreach($tmp->query("//code|i|b|a") as $num => $el) {
				$len = strlen($el->nodeValue);
				$pos = strpos(substr($enti->nodeValue, $last), $el->nodeValue) + $last;
				$last = $pos + $len;
				$entities[$num] = array("offset" => $pos, "length" => $len);
				if($el->tagName == 'a')$entities[$num]['url'] = $el->getAttribute("href");
				elseif($el->tagName == 'b')$entities[$num]['type'] = 'bold';
				elseif($el->tagName == 'i')$entities[$num]['type'] = 'italic';
				elseif($el->tagName == 'code')$entities[$num]['type'] = 'code';
				elseif($el->tagName == 'a')$entities[$num]['type'] = 'link';
			}
			if($entities == array())$entities = false;
			$date = strtotime(array_value(array_value($x->query("$path//a[@class='tgme_widget_message_date']"), 0)->getElementsByTagName('time'), 0)->getAttribute("datetime"));
			$views = $x->query("$path//span[@class='tgme_widget_message_views']");
			if(isset($views[0]))$views = $views[0]->nodeValue;
			else $views = false;
			$author = $x->query("$path//span[@class='tgme_widget_message_from_author']");
			if(isset($author[0]))$author = $author[0]->nodeValue;
			else $author = false;
			$via = $x->query("$path//a[@class='tgme_widget_message_via_bot']");
			if(isset($via[0]))$via = substr($via[0]->nodeValue, 1);
			else $via = false;
			$forward = array_value($x->query("$path//a[@class='tgme_widget_message_forwarded_from_name']"), 0);
			if($forward) {
				$forwardname = $forward->nodeValue;
				$forwarduser = $forward->getAttribute("href");
				$forwarduser = end(explode('/', $forwarduser));
				$forward = $forwardname ? array("title" => $forwardname, "username" => $forwarduser) : false;
			}
			else $forward = false;
			$replyid = $x->query("$path//a[@class='tgme_widget_message_reply']");
			if(isset($replyid[0])) {
				$replyid = $replyid[0]->getAttribute("href");
				$replyid = explode('/', $replyid);
				$replyid = end($replyid);
				$replyname = array_value($x->query("$path//a[@class='tgme_widget_message_reply']//span[@class='tgme_widget_message_author_name']"), 0)->nodeValue;
				$replytext = array_value($x->query("$path//a[@class='tgme_widget_message_reply']//div[@class='tgme_widget_message_text']"), 0)->nodeValue;
				$replymeta = array_value($x->query("$path//a[@class='tgme_widget_message_reply']//div[@class='tgme_widget_message_metatext']"), 0)->nodeValue;
				$replyparse = explode(' ', $replymeta);
				$replythumb = array_value($x->query("$path//a[@class='tgme_widget_message_reply']//i[@class='tgme_widget_message_reply_thumb']"), 0);
				if($replythumb)$replythumb = $replythumb->getAttribute('style');
				else $replythumb = false;
				preg_match('/url\(\'(.{1,})\'\)/', $replythumb, $pr);
				$replythumb = $pr[1];
				$reply = array("message_id" => $replyid, "title" => $replyname);
				if($replytext)$reply['text'] = $replytext;
				elseif($replyparse[0] == 'Service' || $replyparse[0] == 'Channel')$reply['service_message'] = true;
				elseif($replyparse[1] == 'Sticker') {
					$reply['emoji'] = $replyparse[0];
					$reply['sticker'] = $replythumb;
				}
				elseif($replyparse[0] == 'Photo')$reply['photo'] = $replythumb;
				elseif($replyparse[0] == 'Voice')$reply['voice'] = true;
				elseif($replythumb)$reply['document'] = $replythumb;
			}
			else $reply = false;
			$service = $x->query("$path//div[@class='message_media_not_supported_label']");
			if(isset($service[0]))$service = $service[0]->nodeValue == 'Service message';
			else $service = false;
			$photo = array_value($x->query("$path//a[@class='tgme_widget_message_photo_wrap']"), 0);
			if($photo) {
				$photo = $photo->getAttribute('style');
				preg_match('/url\(\'(.{1,})\'\)/', $photo, $pr);
				$photo = array("photo" => $pr[1]);
			}
			else $photo = false;
			$voice = $x->query("$path//audio[@class='tgme_widget_message_voice']");
			if(isset($voice[0])) {
				$voice = $voice[0]->getAttribute("src");
				$voiceduration = array_value($x->query("$path//time[@class='tgme_widget_message_voice_duration']"), 0)->nodeValue;
				$voiceex = explode(':', $voiceduration);
				if(count($voiceex) == 3)$voiceduration = $voiceex[0] * 3600 + $voiceex[1] * 60 + $voiceex[2];
				else $voiceduration = $voiceex[0] * 60 + $voiceex[1];
				$voice = array("voice" => $voice, "duration" => $voiceduration);
			}
			else $voice = false;
			$sticker = $x->query("$path//div[@class='tgme_widget_message_sticker_wrap']");
			if(isset($sticker[0])) {
				$stickername = array_value($sticker[0]->getElementsByTagName("a"), 0);
				$sticker = array_value($stickername->getElementsByTagName('i'), 0)->getAttribute("style");
				preg_match('/url\(\'(.{1,})\'\)/', $sticker, $pr);
				$sticker = $pr[1];
				$stickername = $stickername->getAttribute("href");
				$stickername = explode('/', $stickername);
				$stickername = end($stickername);
				$sticker = array("sticker" => $sticker, "setname" => $stickername);
			}
			else $sticker = false;
			$document = $x->query("$path//div[@class='tgme_widget_message_document_title']");
			if(isset($document[0])) {
				$document = $document[0]->nodeValue;
				$documentsize = array_value($x->query("$path//div[@class='tgme_widget_message_document_extra']"), 0)->nodeValue;
				$document = array("title" => $document, "size" => $documentsize);
			}
			else $document = false;
			$video = $x->query("$path//a[@class='tgme_widget_message_video_player']");
			if(isset($video[0])) {
				$video = array_value($video[0]->getElementsByTagName("i"), 0)->getAttribute("style");
				preg_match('/url\(\'(.{1,})\'\)/', $video, $pr);
				$video = $pr[1];
				$videoduration = array_value($vide->getElementsByTagName("time"), 0)->nodeValue;
				$videoex = explode(':', $videoduration);
				if(count($videoex) == 3)$videoduration = $videoex[0] * 3600 + $videoex[1] * 60 + $videoex[2];
				else $videoduration = $videoex[0] * 60 + $videoex[1];
				$video = array("video" => $video, "duration" => $videoduration);
			}
			else $video = false;
			if($text && ($document || $sticker || $photo || $voice || $video)) {
				$caption = $text;
				$text = false;
			}
			$r = array("username" => $chat, "message_id" => $message);
			if($author)$r['author'] = $author;
			if($text)$r['text'] = $text;
			if(isset($caption) && $caption)$r['caption'] = $caption;
			if($views)$r['views'] = $views;
			if($date)$r['date'] = $date;
			if($photo)$r['photo'] = $photo;
			if($voice)$r['voice'] = $photo;
			if($video)$r['video'] = $video;
			if($sticker)$r['sticker'] = $sticker;
			if($document)$r['document'] = $document;
			if($forward)$r['forward'] = $forward;
			if($reply)$r['reply'] = $reply;
			if($entities)$r['entities'] = $entities;
			if($service)$r['service_message'] = true;
			return (array)$r;
		}
		catch(Error $e) {
			return false;
		}
	}
	public static function getChat($chat){
		if(@$chat[0] == '@')$chat = substr($chat, 1);
		$g = file_get_contents("https://t.me/$chat");
		$g = str_replace('<br/>', "\n", $g);
		$x = new DOMDocument;
		$x->loadHTML($g);
		$x = new DOMXPath($x);
		$path = "//div[@class='tgme_page_wrap']";
		$photo = $x->query("$path//img[@class='tgme_page_photo_image']");
		if(isset($photo[0]))$photo = $photo[0]->getAttribute("src");
		else $photo = false;
		$title = $x->query("$path//div[@class='tgme_page_title']");
		if(!isset($title[0]))return false;
		$title = trim($title[0]->nodeValue);
		$description = array_value($x->query("$path//div[@class='tgme_page_description']"), 0);
		$members = explode(' ', array_value($x->query("$path//div[@class='tgme_page_extra']"), 0)->nodeValue);
		unset($members[count($members) - 1]);
		$members = (int)implode('', $members);
		$r = array("title" => $title);
		if($photo)$r['photo'] = $photo;
		if(isset($description->nodeValue))$r['description'] = $description->nodeValue;
		if($members > 0)$r['members'] = $members;
		return (array)$r;
	}
	public static function getJoinChat($code){
		return self::getChat("joinchat/$code");
	}
	public static function getSticker($name){
		$g = file_get_contents("https://t.me/addstickers/$name");
		$x = new DOMDocument;
		$x->loadHTML($g);
		$x = new DOMXPath($x);
		$title = $x->query("//div[@class='tgme_page_description']");
		if(!isset($title[0]))return false;
		$title = array_value($title[0]->getElementsByTagName("strong"), 1)->nodeValue;
		return (object)array("setname" => $name, "title" => $title);
	}
	public static function channelCreatedDate($channel){
		return self::getMessage($channel, 1)->date;
	}
	public $logged = false,$hash = "",$creation_hash = "",$token = "",$number;
	public function __construct($number){
		$number = str_replace(array("+","(",")"," "),'',$number);
		$this->number = $number;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/auth/send_password');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('phone' => $number));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER,array(
			'Origin: https://my.telegram.org',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Referer: https://my.telegram.org/auth',
			'X-Requested-With: XMLHttpRequest',
			'Connection: keep-alive',
			'Dnt: 1'));
		$result = curl_exec($ch);
		curl_close($ch);
		if(!$result)
			new APError("MyTelegram login", "can not Connect to https://my.telegram.org", APError::NETWORK);
		$res = aped::jsondecode($result,true);
		if(!isset($res['random_hash'])) 
			new APEError("MyTelegram login", $result, APError::NOTIC);
		return $this->hash = $res['random_hash'];
	}
	public function complete_login($password){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/auth/login');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('phone' => $this->number, 'random_hash' => $this->hash, 'password' => $password)));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Origin: https://my.telegram.org',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Referer: https://my.telegram.org/auth',
			'X-Requested-With: XMLHttpRequest',
			'Connection: keep-alive',
			'Dnt: 1'
		));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		if(!$result)
			new APError("MyTelegram login", "can not Connect to https://my.telegram.org", APError::NETWORK);
		$header = explode("\r\n\r\n",$result,2);
		$content = $header[1];
		if($content != 'true')
			new APError("MyTelegram CompleteLogin", $content, APError::NETWORK);
		$header = $header[0];
		$this->logged = true;
		$token = strpos($header,'stel_token=') + 11;
		$token = substr($header,$token,strpos($header,';',$token) - $token);
		return $this->token = $token;
	}
	public function isLogged(){
		return $this->logged;
	}
	public function has_app(){
		if(!$this->token)return false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/apps');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Dnt: 1',
			'Accept-Encoding: gzip, deflate, sdch, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Referer: https://my.telegram.org/',
			'Cookie: stel_token='.$this->token,
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		));
		$result = curl_exec($ch);
		curl_close($ch);
		$title = strpos($result,'<title>') + 7;
		$title = substr($result,$title,strpos($result,'</title>',$title) - $title);
		switch($title){
			case 'App configuration':
				return true;
			case 'Create new application':
				$hash = strpos($resut,'<input type="hidden" name="hash" value="') + 40;
				$hash = substr($resut,$hash,strpos($result,'"/>',$hash) - $hash);
				$this->creation_hash = $hash;
				return false;
		}
		return false;
	}
	public function get_app(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/apps');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Dnt: 1',
			'Accept-Encoding: gzip, deflate, sdch, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Referer: https://my.telegram.org/',
			'Cookie: stel_token='.$this->token,
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		));
		$result = curl_exec($ch);
		curl_close($ch);
		$cose = explode('<label for="app_id" class="col-md-4 text-right control-label">App api_id:</label>
	  <div class="col-md-7">
		<span class="form-control input-xlarge uneditable-input" onclick="this.select();"><strong>', $result);
		$asd = explode('</strong></span>', $cose['1']);
		$api_id = $asd['0'];
		$cose = explode('<label for="app_hash" class="col-md-4 text-right control-label">App api_hash:</label>
	  <div class="col-md-7">
		<span class="form-control input-xlarge uneditable-input" onclick="this.select();">', $result);
		$asd = explode('</span>', $cose['1']);
		$api_hash = $asd['0'];
		return array('api_id'=>(int)$api_id, 'api_hash'=>$api_hash);
	}
	public function create_app($title,$shortname,$url,$platform,$desc){
		if(!$this->logged)
			new APError("MyTelegram CompleteLogin", 'Not logged in!', APError::NOTIC);
		if($this->has_app())
			new APError("MyTelegram CompleteLogin", 'The app was already created!', APError::NOTIC);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/apps/create');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'hash'=>$this->creation_hash,
			'app_title'=>$title,
			'app_shortname'=>$shortname,
			'app_url'=>$url,
			'app_platform'=>$platform,
			'app_desc'=>$desc
		));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Cookie: stel_token='.$this->token,
			'Origin: https://my.telegram.org',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Accept: */*',
			'Referer: https://my.telegram.org/apps',
			'X-Requested-With: XMLHttpRequest',
			'Connection: keep-alive',
			'Dnt: 1'
		));
		$result = curl_exec($ch);
		curl_close($ch);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/apps');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Dnt: 1',
			'Accept-Encoding: gzip, deflate, sdch, br',
			'Accept-Language: it-IT,it;q=0.8,en-US;q=0.6,en;q=0.4',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Referer: https://my.telegram.org/',
			'Cookie: stel_token='.$this->token,
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		));
		$result = curl_exec($ch);
		curl_close($ch);
		$cose = explode('<label for="app_id" class="col-md-4 text-right control-label">App api_id:</label>
	  <div class="col-md-7">
		<span class="form-control input-xlarge uneditable-input" onclick="this.select();"><strong>', $result);
		$asd = explode('</strong></span>', $cose['1']);
		$api_id = $asd['0'];
		$cose = explode('<label for="app_hash" class="col-md-4 text-right control-label">App api_hash:</label>
	  <div class="col-md-7">
		<span class="form-control input-xlarge uneditable-input" onclick="this.select();">', $result);
		$asd = explode('</span>', $cose['1']);
		$api_hash = $asd['0'];
		return array('api_id'=>(int)$api_id, 'api_hash'=>$api_hash);
	}
}

?>
