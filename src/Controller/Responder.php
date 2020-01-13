<?php

namespace Drupal\tagbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\tagbot\TwitterClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responder service.
 */
class Responder extends ControllerBase {

  /**
   * The tagbot.twitter_client service.
   *
   * @var \Drupal\tagbot\TwitterClient
   */
  protected $tagbotTwitterClient;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $http_client;

  /**
   * Constructs a Responder object.
   *
   * @param \Drupal\tagbot\TwitterClient $tagbot_twitter_client
   *   The tagbot.twitter_client service.
   */
  public function __construct(TwitterClient $tagbot_twitter_client, HandlerStack $stack) {
    $this->tagbotTwitterClient = $tagbot_twitter_client;
    $config = [
      'verify' => FALSE,
      'timeout' => 10,
      'headers' => [
        'User-Agent' => 'Drupal/' . \Drupal::VERSION . ' (+https://www.drupal.org/) ' . \GuzzleHttp\default_user_agent(),
      ],
      'handler' => $stack,
      // Security consideration: prevent Guzzle from using environment variables
      // to configure the outbound proxy.
      'proxy' => [
        'http' => NULL,
        'https' => NULL,
        'no' => [],
      ],
    ];
    $this->http_client = new Client($config);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tagbot.twitter_client'),
      $container->get('http_handler_stack')
    );
  }

  /**
   * Method description.
   */
  public function respondToMentions($record = TRUE) {
    $mentions = [];
    try {
      $since_id = \Drupal::state()->get('tagbot_last_mention');
      $mentions = $this->tagbotTwitterClient->getMentions($since_id);
    }
    catch (\Exception $e) {
      watchdog_exception('tagbot', $e);
      return [];
    }
    if (empty($mentions)) {
      return;
    }
    $count = count($mentions);
    \Drupal::logger('tagbot')->notice("Found $count mentions");
    foreach ($mentions as $mention) {
      $this->respondToMention($mention, $record);
    }
    return [
      '#markup' => 'foo'
    ];
  }

  public function respondToMention(array $mention, $record = TRUE) {
    $tag_ids = $this->extractTagIdsFromMention($mention);
    if (empty($tag_ids)) {
      \Drupal::logger('tagbot')->notice('No tag ids found in mention ' . $mention['id'] . ' : ' . $mention['full_text']);
      if ($record) {
        \Drupal::state()->set('tagbot_last_mention', $mention['id']);
      }
      // If there aren't any tags in this mention, don't bother replying.
      return;
    }
    foreach ($tag_ids as $tag_id) {
      $lookupResult = $this->lookupTag($tag_id);
      if ($lookupResult) {
        $this->tweetReplyWithResults($mention, $tag_id, $lookupResult);
      }
      else {
        $this->tweetReplyWithNoResults($mention, $tag_id);
      }
    }
  }

  protected function extractTagIdsFromMention($mention) {
//    dpm($mention);
    // preg match for plate ids
    $matches = [];
    preg_match_all('/([A-Za-z]{2}:[a-zA-Z0-9]+)/', $mention['full_text'], $matches);
//    dpm($matches[0]);
    return $matches[0];
  }

  protected function lookupTag($tag_id) {
    // Call out to PPA site
    list($state, $tag) = explode(':', strtoupper($tag_id), 2);
    if (empty($state) || empty($tag)) {
      return NULL;
    }
    $search_url = 'https://onlineserviceshub.com/ParkingPortal/Philadelphia/Home/DoSearch';
    $params = [
      'searchBy' => 'ticketNumber',
      'OtherFirstField' => '',
      'OtherSecondField' => $tag,
      'X-Requested-With' => 'XMLHttpRequest',
      'State' => $state,
    ];
    if (\Drupal::state()->get('TAGBOT_TEST_MODE')) {
      $search_result = file_get_contents(__DIR__ . '/../../example_search.html');
      //dpm($search_result, __LINE__);
    }
    else {
      try {
        \Drupal::logger('tagbot')->notice("Querying for violations for " . $tag . ' : ' . print_r($params, 1));
        $response = $this->http_client->post($search_url, ['form_params' => $params]);
      }
      catch (\Exception $e) {
        watchdog_exception('tagbot', $e);
        return [];
      }
      $cookie = $response->getHeader('Set-Cookie');
      $search_result = $response->getBody()->getContents();
    }
    \Drupal::logger('tagbot')->notice("Violation result " . $search_result);
    return $this->getTicketDetailsFromSearchResult($search_result, $cookie);
  }

  protected function getTicketDetailsFromSearchResult($search_result, $cookie) {
    // Extract ticket detail keys
    // For each key, post to ticket details endpoint
    $detail_url = 'https://onlineserviceshub.com/ParkingPortal/Philadelphia/Home/DoTicketDetails';
    $matches = [];
    preg_match_all('/data\-key=\"([0-9a-zA-Z]+)\"/', $search_result, $matches);
    if (empty($matches[1])) {
      return [];
    }
    foreach ($matches[1] as $match) {
      if (\Drupal::state()->get('TAGBOT_TEST_MODE')) {
        $detail = file_get_contents(__DIR__ . '/../../example_detail.html');
        //dpm($detail, __LINE__);
      }
      else {
        $headers = [
          'X-Requested-With' => 'XMLHttpRequest',
          'Referer' => 'https://onlineserviceshub.com/ParkingPortal/Philadelphia',
          'Cookie' => $cookie,
          'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $params = [
          'detailKey' => $match
        ];
        try {
          \Drupal::logger('tagbot')->notice("Querying ticket details " . print_r($params, 1) . print_r($headers, 1));
          $response = $this->http_client->post($detail_url, ['headers' => $headers, 'form_params' => $params]);
        }
        catch (\Exception $e) {
          watchdog_exception('tagbot', $e);
          continue;
        }
        $detail = $response->getBody()->getContents();
      }
      $dom = new \DOMDocument();
      $dom->loadHTML($detail);
      //dpm($dom->saveHTML(), __LINE__);
      $xpath = new \DOMXPath($dom);
      $result = $xpath->query("//div[contains(@class, 'ticket-details')]/div");
      $data_item = [
        'Violation' => '',
        'Amount Due' => '',
      ];
      $cap_next = FALSE;
      $key = '';
      foreach ($result as $item) {
        //dpm($item, __LINE__);
        $content = trim($item->textContent);
        if (array_key_exists($content, $data_item)) {
          $cap_next = TRUE;
          $key = $content;
          continue;
        }
        if ($cap_next) {
          $data_item[$key] = $content;
          $cap_next = FALSE;
        }
      }
      $data[] = $data_item;
    }
    \Drupal::logger('tagbot')->notice("Ticket detail " . print_r($data, 1));
    return $data;
  }

  protected function tweetReplyWithResults($mention, $tag, $lookupResult) {
    $total = 0.0;
    $count_by_type = [];
    $total_count = count($lookupResult);
    foreach ($lookupResult as $data_item) {
      if (empty($count_by_type[$data_item['Violation']])) {
        $count_by_type[$data_item['Violation']] = 1;
      }
      else {
        $count_by_type[$data_item['Violation']]++;
      }
      $total += floatval(preg_replace('/[^\d.]/', '', $data_item['Amount Due']));
    }
    $replies = [];
    $username = $mention['user']['screen_name'];
    $text = '@' . $username . ' ' . $total_count . ' known violations totalling $' . number_format($total, 2) . ' for tag #' . str_replace(':', '_', $tag) . ' : ' . $total_count . PHP_EOL . PHP_EOL;
    foreach ($count_by_type as $type => $count) {
      $next_line = $count . ' | ' . $type . PHP_EOL;
      if (strlen($text . $next_line) > 280) {
        $replies[] = $text;
        $text = '@' . $username . ' Violations for tag #' . str_replace(':', '_', $tag) . ", cont'd" . PHP_EOL . PHP_EOL . $next_line;
      }
      else {
        $text .= $next_line;
      }
    }
    $replies[] = $text;
    if (count($replies) == 1) {
      $this->sendReply($mention, $text, TRUE);
    }
    else {
      $this->sendThreadedReplies($mention, $replies);
    }
  }

  protected function tweetReplyWithNoResults($mention, $tag) {
    $username = $mention['user']['screen_name'];
    $text = '@' . $username . ' No violations found for #' . str_replace(':', '_', $tag);
    $this->sendReply($mention, $text);
  }

  protected function sendReply($mention, $text, $quote = FALSE) {
    try {
      $last_id = $mention['id'];
      if (\Drupal::state()->get('TAGBOT_TEST_MODE')) {
        dpm('Would have replied to mention : ' . $mention['id'] . ' with text "' . $text . '"');
      }
      else {
        $mention = $this->tagbotTwitterClient->sendTweet($text, $mention['id'], $quote);
        \Drupal::state()->set('tagbot_last_mention', $last_id);
      }
    }
    catch (\Exception $e) {
      watchdog('tagbot', $e);
    }
    return $mention;
  }

  protected function sendThreadedReplies($mention, $replies) {
    $quote = TRUE;
    foreach ($replies as $reply) {
      $mention = $this->sendReply($mention, $reply, $quote);
      // Quote-tweet the first reply only.
      $quote = FALSE;
    }
    return $mention;
  }

}
