<?php

class Nex1Music {
    private static function openWeb($path){
        if(extension_loaded('curl')){
            $ch = curl_init("https://nex1music.ir/$path");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res;
        }
        return fget("https://nex1music.ir/$path");
    }
    private static function getRedirect($path){
        if(extension_loaded('curl')){
            $ch = curl_init("https://nex1music.ir/$path");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            $res = array_value(explode("\r\n\r\n", $res, 2), 0);
        }else
            $res = implode("\n", get_headers("https://nex1music.ir/$path"));
        $p = stripos($res, "\nlocation: https://nex1music.ir/", 10) + 32;
        return crypt::urldecode(substr($res, $p, strpos($res, "\n", $p) - $p - 2));
    }

    public static function pageCount($search = ''){
        $search = explode('#', $search, 2);
        if(isset($search[1]))
            $url = "tag/{$search[1]}/";
        else $url = '';
        if($search[0])
            $url .= "?s={$search[0]}";
        $g = self::openWeb($url);
        $x = new DOMDocument;
        @$x->loadHTML($g);
        $x = new DOMXPath($x);
        $btns = $x->query("//div[@class='pn']/a");
        return (int)str_replace(',', '', $btns[1]->nodeValue);
    }
    public static function musicCount($search = ''){
        $page = self::pagesCount();
        $search = explode('#', $search, 2);
        if(isset($search[1]))
            $url = "tag/{$search[1]}/";
        else $url = '';
        $url .= $page <= 1 ? '' : "page/$page";
        if($search[0])
            $url .= "?s={$search[0]}";
        $g = self::openWeb($url);
        $x = new DOMDocument;
        @$x->loadHTML($g);
        $x = new DOMXPath($x);
        $btns = $x->query("//div[@class='sdr']/div[@class='ps anm']");
        return $btns->length + ($page - 1) * 15;
    }
    public static function search($search = '', $page = 1){
        $search = explode('#', $search, 2);
        if(isset($search[1]))
            $url = "tag/{$search[1]}/";
        else $url = '';
        $url .= $page <= 1 ? '' : "page/$page";
        if($search[0])
            $url .= "?s={$search[0]}";
        $g = self::openWeb($url);
        $x = new DOMDocument;
        @$x->loadHTML($g);
        $x = new DOMXPath($x);
        $path = "//div[@class='sdr']";
        $baners = array();
        $list = $x->query("$path/div[@class='ps anm']");
        foreach($list as $num => $baner){
            $div = $x->query("./div", $baner);
            $h2 = $x->query("./h2", $div[0]);
            $title = $h2[0]->nodeValue;
            if(strpos($title, ' به نام ') === false){
                if(strpos($title, 'دانلود') === 0)
                    continue;
                $title = crypt::utf8iso88591($title);
            }
            $album = strpos($title, 'دانلود آلبوم') === 1;
            $title = $album ? substr($title, 25) : substr($title, 23);
            $p = strpos($title, " به نام ");
            $artist = substr($title, 0, $p);
            $title = substr($title, $p + 13);
            $div[0]->removeChild($h2[0]);
            $a = $x->query("./a", $div[0]);
            $likes = (int)substr($a[0]->nodeValue, 2);
            $post = (int)$a[0]->getAttribute('rel');
            $div[0]->removeChild($a[0]);
            $time = explode(' | ', $div[0]->nodeValue, 2);
            $opinions = (int)substr($time[1], 0, strpos($time[1], ' '));
            $link = "https://nex1music.ir/" . strtr(($album ? 'آلبوم-' : 'آهنگ-') . $artist . '-' . $title, ' ', '-');
            $p = $x->query("./div[@class='center']/p", $div[1]);
            if($p->length < 4)continue;
            $artisten = explode(' - ', $p[2]->nodeValue, 2);
            if(!isset($artisten[1]))
                unset($artisten);
            else
                list($titleen, $artisten) = $artisten;
            $img = $x->query("./img", $p[$p->length - 1]);
            $image = $img[0]->getAttribute('data-src');
            if($image == '')continue;
            $time = substr($image, $p = strrpos($image, '/', 34) + 1, strrpos($image, '.') - $p);
            $time = array_slice(explode('-', $time), -6);
            $time = strtotime("{$time[0]}/{$time[1]}/{$time[2]} {$time[3]}:{$time[4]}:{$time[5]} +00:00");
            $baner = array();
            $baner['number'] = ($page - 1) * 15 + $num + 1;
            $baner['artist'] = $artist;
            $baner['title'] = $title;
            if(isset($artisten))$baner['artist_en'] = $artisten;
            if(isset($titleen))$baner['title_en'] = $titleen;
            $baner['link'] = $link;
            $baner['image'] = $image;
            $baner['time'] = $time;
            $baner['likes'] = $likes;
            $baner['opinions'] = $opinions;
            $baner['post'] = $post;
            $baner['album'] = $album;
            $baners[] = $baner;
        }
        $result = array(
            'page' => $page,
            'link' => "https://nex1music.ir/$url",
            'banners' => $baners
        );
        $btns = $x->query("//div[@class='pn']/a");
        $result['last_page'] = (int)str_replace(',', '', $btns[1]->nodeValue);
        return $result;
    }
    public static function page($what, $arg1 = null, $arg2 = null){
        $what = strtolower($what);
        if($arg1 === null && $arg2 === null){
            $what = explode(' ', $what, 2);
            if(!isset($what[1])){
                $what = 'mousic';
                $url = $what[0];
            }else list($what, $url) = $what;
            $url = str_replace(array('https://nex1music.ir/', 'http://nex1music.ir/', 'https://nex1music.ir', 'http://nex1music.ir'), '', $url);
        }else switch($what){
            case 'post':
                $url = "post/$arg1";
                $url = self::getRedirect($url);
            break;
            case 'album':
                $url = strtr('آلبوم-' . $arg1 . '-' . $arg2, ' ', '-');
            break;
            default:
                $what = 'music';
                $url = strtr('آهنگ-' . $arg1 . '-' . $arg2, ' ', '-');
        }
        $g = self::openWeb($url);
        $g = str_replace('<br />', "\n", $g);
        $x = new DOMDocument;
        @$x->loadHTML($g);
        $x = new DOMXPath($x);
        $path = "//div[@class='sdr']";
        $baner = $x->query("$path/div[@class='ps anm']");
        $div = $x->query("./div", $baner[0]);
        $h2 = $x->query("./h2", $div[0]);
        $title = $h2[0]->nodeValue;
        if(strpos($title, ' به نام ') === false){
            if(strpos($title, 'دانلود') === 0)
                return false;
            $title = crypt::utf8iso88591($title);
        }
        $album = strpos($title, 'دانلود آلبوم') === 1;
        $title = $album ? substr($title, 25) : substr($title, 23);
        $p = strpos($title, " به نام ");
        $artist = substr($title, 0, $p);
        $title = substr($title, $p + 13);
        $div[0]->removeChild($h2[0]);
        $a = $x->query("./a", $div[0]);
        $likes = (int)substr($a[0]->nodeValue, 2);
        $post = (int)$a[0]->getAttribute('rel');
        $div[0]->removeChild($a[0]);
        $time = explode(' | ', $div[0]->nodeValue, 2);
        $opinions = (int)substr($time[1], 0, strpos($time[1], ' '));
        $link = "https://nex1music.ir/" . strtr(($album ? 'آلبوم-' : 'آهنگ-') . $artist . '-' . $title, ' ', '-');
        $p = $x->query("./div[@class='center']/p", $div[1]);
        if($p->length < 4)return false;
        $artisten = explode(' - ', $p[2]->nodeValue, 2);
        if(!isset($artisten[1]))
            unset($artisten);
        else
            list($titleen, $artisten) = $artisten;
        $img = $x->query("./img", $p[$p->length - 1]);
        $image = $img[0]->getAttribute('src');
        if($image == '')return false;
        $time = substr($image, $p = strrpos($image, '/', 34) + 1, strrpos($image, '.') - $p);
        $time = array_slice(explode('-', $time), -6);
        $time = strtotime("{$time[0]}/{$time[1]}/{$time[2]} {$time[3]}:{$time[4]}:{$time[5]} +00:00");
        $dls = $x->query("//div[@class='lnkdl animate']/a");
        if($what == 'post')
            $what = $album ? 'album' : 'music';
        if($what == 'music'){
            $download = array();
            foreach($dls as $dl){
                $value = substr($dl->nodeValue, 39);
                $value = $value == 'اصلی' ? 'main' : (int)$value;
                $download[$value] = $dl->getAttribute('href');
            }
        }else{
            $download = array(
                'all' => array(),
                'list' => array()
            );
            foreach($dls as $dl){
                $value = substr($dl->nodeValue, 39);
                $value = $value == 'اصلی' ? 'main' : (int)$value;
                $all = substr($value, -11) == " (یکجا)";
                $download[$all ? 'all' : 'list'][$value] = $dl->getAttribute('href');
            }
        }
        $text = $x->query("//div[@class='lyrics']");
        if($text->length == 0)
            unset($text);
        else{
            $h2 = $x->query("./h2", $text[0]);
            $text[0]->removeChild($h2[0]);
            $text = substr($text[0]->nodeValue, 2);
        }
        $result = array();
        $result['artist'] = $artist;
        $result['title'] = $title;
        if(isset($artisten))$result['artist_en'] = $artisten;
        if(isset($titleen))$result['title_en'] = $titleen;
        $result['link'] = $link;
        $result['image'] = $image;
        $result['time'] = $time;
        $result['likes'] = $likes;
        $result['opinions'] = $opinions;
        $result['post'] = $post;
        $result['album'] = $album;
        if(isset($text))$result['text'] = $text;
        $result['download'] = $download;
        return $result;
    }
    public static function searchDownload($search = '', $page = 1, $maxlimit = 15){
        $search = self::search($search, $page);
        $search['banners'] = array_slice($search['banners'], 0, $maxlimit);
        foreach($search['banners'] as $k => &$baner){
            $page = self::page($baner['album'] ? 'album' : 'music', $baner['artist'], $baner['title']);
            if(isset($page['text']))$baner['text'] = $page['text'];
            $baner['download'] = $page['download'];
        }
        return $search;
    }
    public static function fileManager($link){
        $g = fget($link);
        $x = new DOMDocument;
        @$x->loadHTML($g);
        $x = new DOMXPath($x);
        $tr = $x->query("//tr");
        $files = array();
        for($i = 4; $i < $tr->length - 1; ++$i){
            $td = $x->query("./td", $tr[$i]);
            $file = $x->query("./a", $td[1]);
            $size = $td[2]->nodeValue;
            $link = $file[0]->getAttribute('href');
            $file = $file[0]->nodeValue;
            if(substr($link, 0, 2) == './')
                $link = 'http://dl.nex1music.ir' . substr($link, 1);
            $files[$file] = array(
                'link' => $link,
                'size' => $size
            );
        }
        return $files;
    }
}

?>
