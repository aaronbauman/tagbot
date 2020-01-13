<?php

namespace Drupal\tagbot;

/**
 * TwitterClient service.
 */
class TwitterClient {

  /**
   * @var \tmhOAuth
   */
  private $tmhOAuth;

  public function __construct() {
    $this->config = array_merge(
      [
        // change the values below to ones for your application
        'consumer_key'    => TAGBOT_CONSUMER_KEY,
        'consumer_secret' => TAGBOT_CONSUMER_SECRET,
        'token'           => TAGBOT_ACCOUNT_TOKEN,
        'secret'          => TAGBOT_ACCOUNT_SECRET,
        'bearer'          => 'N/A',
        'user_agent'      => 'tmhOAuth ' . \tmhOAuth::VERSION . ' Examples 0.1',
      ]
    );
    $this->tmhOAuth = new \tmhOAuth($this->config);
  }

  public function getStatus($id) {
    $request = [
      'url' => $this->tmhOAuth->url('1.1/statuses/show/' . $id),
      'method' => 'GET',
      'params' => [
        'tweet_mode' => 'extended',
      ]
    ];
    $code = $this->tmhOAuth->user_request($request);
    if ($code == 200) {
      return json_decode($this->tmhOAuth->response['response'], true);
    }
  }

  /**
   * Method description.
   */
  public function getMentions($since_id = '') {
    $request = [
      'method' => 'GET',
      'url' => $this->tmhOAuth->url('1.1/statuses/mentions_timeline'),
      'params' => [
        'tweet_mode' => 'extended'
      ]
    ];
    if ($since_id) {
      $request['params']['since_id'] = $since_id;
    }
    $code = $this->tmhOAuth->user_request($request);
    if ($code == 200) {
      return json_decode($this->tmhOAuth->response['response'], true);
    }
  }

  public function sendTweet($status, $in_reply_to_status_id = '', $quote = FALSE) {
    $request = [
      'method' => 'POST',
      'url' => $this->tmhOAuth->url('1.1/statuses/update'),
    ];
    $request['params'] = [
      'status' => $status
    ];
    if ($in_reply_to_status_id) {
      $request['params']['in_reply_to_status_id'] = $in_reply_to_status_id;
      if ($quote) {
        // Quote the original tweet, e.g. for image context.
        $request['params']['attachment_url'] = 'https://twitter.com/i/web/status/' . $in_reply_to_status_id;
      }
    }
    $code = $this->tmhOAuth->user_request($request);
    if ($code == 200) {
      return json_decode($this->tmhOAuth->response['response'], true);
    }
  }

  /**
   * Below are helper functions for rendering the response from the Twitter API.
   *
   * Copied from tmhOAuth.
   */
  public function render_response() {
    self::eko('Request Headers', false, '=');
    self::eko_kv($this->convert_headers($this->response['info']['request_header']), 0, TMH_INDENT);
    self::eko('');

    self::eko('Request Data', false, '=');
    $d = $this->tmhOAuth->response['info'];
    unset($d['request_header']);
    self::eko_kv($d, 0, TMH_INDENT);
    self::eko('');

    self::eko('Response Headers', false, '=');
    self::eko_kv($this->tmhOAuth->response['headers'], 0, TMH_INDENT);
    self::eko('');

    if (defined(JSON_PRETTY_PRINT)) {
      self::eko('Response Body (Formatted)', false, '=');
      $d = json_decode($this->tmhOAuth->response['response']);
      $d = json_encode($d, JSON_PRETTY_PRINT);
      self::eko($d);
    } else {
      self::eko('Response Body (As an Object)', false, '=');
      $d = json_decode($this->tmhOAuth->response['response'], true);
      var_dump($d);
    }

    self::eko('');
    self::eko('Raw response', true, '=');
    self::eko($this->tmhOAuth->response['raw'], true);
  }

  private static function eko($text, $newline=false, $underline=NULL) {
    echo $text . PHP_EOL;

    if (!empty($underline))
      echo str_pad('', strlen($text), $underline) . PHP_EOL;

    if ($newline)
      echo PHP_EOL;
  }

  private static function eko_kv($items, $indent=0, $padding=10) {
    foreach ((array)$items as $k => $v) {
      if (is_array($v) && !empty($v)) {
        $text = str_pad('', $indent) . str_pad($k, $padding);
        self::eko($text);

        foreach ($v as $k2 => $v2) {
          self::eko_kv(array($k2 => $v2), $indent+5, $padding);
        }
      } else {
        $text = str_pad('', $indent) . str_pad($k, $padding) . implode('',(array)$v);
        self::eko($text);
      }
    }
  }

  private function convert_headers($headers) {
    $headers = explode(PHP_EOL, $headers);
    $_headers = array();
    foreach ($headers as $header) {
      list($key, $value) = array_pad(explode(':', trim($header), 2), 2, null);
      $_headers[trim($key)] = trim($value);
    }
    return $_headers;
  }

}
