<?php

class IMDB {
    public static $apikey = false;

    public static function request($args){
        if(!isset($args['apikey']))
            if(self::$apikey)
                $args['apikey'] = self::$apikey;
            else
                $args['apikey'] = 'BanMePls';
        return aped::jsondecode(fget('http://www.omdbapi.com/?' . http_build_query($args)));
    }
    public static function byTitle($title, $params = array()){
        $args = array('t' => $title);
        if(isset($params['apikey']))$args['apikey'] = $params['apikey'];
        if(isset($params['type']))$args['type'] = $params['type'];
        if(isset($params['year']))$args['y'] = $params['year'];
        if(isset($params['plot']))$args['plot'] = $params['plot'];
        return self::request($args);
    }
    public static function byId($id, $params = array()){
        $args = array('i' => $id);
        if(isset($params['apikey']))$args['apikey'] = $params['apikey'];
        if(isset($params['type']))$args['type'] = $params['type'];
        if(isset($params['year']))$args['y'] = $params['year'];
        if(isset($params['plot']))$args['plot'] = $params['plot'];
        return self::request($args);
    }
    public static function search($query, $params = array()){
        $args = array('s' => $query);
        if(isset($params['apikey']))$args['apikey'] = $params['apikey'];
        if(isset($params['type']))$args['type'] = $params['type'];
        if(isset($params['year']))$args['y'] = $params['year'];
        if(isset($params['page']))$args['page'] = $params['page'];
        return self::request($args);
    }
    public static function postById($id){
        return "https://www.imdb.com/title/$id";
    }
}

?>
