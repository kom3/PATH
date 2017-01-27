<?php

namespace Path;

class API {

    private $path_host = 'https://beta.path.com/a/';
    public $session = NULL;
    public $userdata = [];

    private $logged = false;
    private $cookies = [];

    public function login($username, $password){
        $post = '{"emailId":"'.$username.'","password":"'.$password.'"}';
        $http = $this->response($this->http('login', $post));
        if(count($this->cookies) == 1){
            $ck = $this->cookies[0][1];
            $this->session = str_replace('connect.sid=', '', $ck);
            $this->logged = true;
            $this->userdata = $http['user'];
        } else {
            throw new Exception('Invalid username or password');
        }
    }
    public function likeAllTimeline($max, $reaction, callable $callback, $userid = NULL){
        $this->isLogin();
        if(!is_numeric($max) OR $max <= 0) throw new Exception("Invalid Maximum");
        $this->timeline($userid, function($data) use($max, $reaction, $callback){
            $n = 0;
            foreach($data as $dds){
                $id = $dds['id'];
                if($n >= $max) break;
                $this->like($id, $reaction, $callback);
                $n++;
            }
        });
    }
    public function friends(callable $callback){
        $this->isLogin();
        $http = $this->response($this->http('friends?locale=en&meId=' . $this->getID()));
        if(!isset($http['users'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['users']);
        }
    }
    public function friends_favorite($friends_id, callable $callback){
        $this->isLogin();
        $post = '{"user_id":"'.$friends_id.'","meId":"'.$this->getID().'"}';
        $http = $this->response($this->http('user/friends/ic/add', $post));
        if(!isset($http['icList'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['icList']);
        }
    }
    public function set_activity(callable $callback){
        $this->isLogin();
        $post = '{"meId":"'.$this->getID().'"}';
        $http = $this->response($this->http('activity/read', $post));
        $callback(true);
    }
    public function activity(callable $callback){
        $this->isLogin();
        $http = $this->response($this->http('activity?meId=' . $this->getID()));
        if(!isset($http['activities']['list'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['activities']['list']);
        }
    }
    public function like($moment_id, $emoticon = 0, callable $callback){
        $this->isLogin();
        $emot = $this->getEmoticon();
        if(!in_array($emoticon, $emot)) throw new Exception('Invalid Emoticon');
        if($emoticon === 0) $emoticon = array_slice($emot, 1)[array_rand(array_slice($emot, 1), 1)];
        $post = '{"moment_id":"'.$moment_id.'","emotion_type":"'.$emoticon.'","meId":"'.$this->getID().'"}';
        $http = $this->response($this->http('moment/emotion/add', $post));
        if(empty($http['emotions'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['emotions']);
        }
    }
    public function comment($moment_id, $text, callable $callback){
        $this->isLogin();
        if(empty($text)) throw new Exception('Invalid Comment');
        $post = '{"moment_id":"'.$moment_id.'","comment_body":"'.$text.'","meId":"'.$this->getID().'"}';
        $http = $this->response($this->http('moment/comment/add', $post));
        if(empty($http['commentSet'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['commentSet']);
        }
    }
    public function timeline($user = NULL, callable $callback){
        $this->isLogin();
        $http = $this->response($this->http('feed/home?ww=1360&wh=672&user_id='.(($user == NULL) ? $this->getID() : $user).'&meId=' . $this->getID()));
        if(!isset($http['moments'])){
            throw new Exception("Invalid Response");
        } else {
            $callback($http['moments']);
        }
    }
    private function getEmoticon(){
        return [0, 'happy', 'laugh', 'surprise', 'sad', 'love'];
    }
    private function getID(){
        return $this->userdata['id'];
    }
    private function isLogin(){
        if($this->logged == false) throw new Exception("Please login first!");

        return $this->logged;
    }
    private function response($http){
        $data = json_decode($http[1], true);
        if(!empty($data['error_code']) OR empty($data)){
            throw new Exception((!empty($data['display_message'])) ? $data['display_message'] : 'Error Occured');
        } else {
            return $data;
        }
    }
    private function parse_cookies($ch, $headerLine){
        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1)
            $this->cookies[] = $cookie;
        return strlen($headerLine);
    }
    private function http($path, $postdata = ''){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->path_host . $path);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$this, 'parse_cookies']);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaderList($postdata));
        if(!empty($postdata)){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        }
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array($http, $response);
    }
    private function getHeaderList($p){
        $ht = 'Host: beta.path.com
            Connection: keep-alive
            accept: application/json
            Origin: https://beta.path.com
            User-Agent: Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36
            content-type: application/json
            Referer: https://beta.path.com/
            Accept-Encoding: gzip, deflate, br
            Accept-Language: en,id;q=0.8,ja;q=0.6';
        if(!empty($this->session)){
            $ht .= "\nCookie: connect.sid=".$this->session.";";
        }
        if(!empty($p) && strlen($p) > 0){
            $ht .= "\nContent-Length: " . strlen($p);
        }
        return array_map(function($val){
            return trim($val);
        }, explode("\n", $ht));
    }
}

class Exception extends \Exception {

}
