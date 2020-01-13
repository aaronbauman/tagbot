# tagbot

Tagbot is a Drupal module that negotiates between a twitter bot and PPA's payment form to provide information about unpaid traffic violations. Visit the bot live at https://twitter.com/HowsMyDrivingPA

## Usage

<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Tag me (<a href="https://twitter.com/HowsMyDrivingPA?ref_src=twsrc%5Etfw">@HowsMyDrivingPA</a>) and include vehicle information like this:<br><br>&lt;state|province|territory&gt;:&lt;plate&gt;, e.g. PA:abc1234</p>&mdash; Hows My Driving PA (@HowsMyDrivingPA) <a href="https://twitter.com/HowsMyDrivingPA/status/1205878681723424768?ref_src=twsrc%5Etfw">December 14, 2019</a></blockquote> 

## Contributing

Install tagbot on your Drupal site to start tinkering:

`composer require aaronbauman/tagbot`

## Requirements

- A twitter developer account with OAuth creds
- A Drupal site, running cron as frequently as possible. (HowsMyDrivingPA runs every minute.)

## Install

Define the following constants, e.g. in your settings.php file:
```
define('TAGBOT_CONSUMER_KEY', 'consumer key from twitter developer account');
define('TAGBOT_CONSUMER_SECRET', 'consumer secret...');
define('TAGBOT_ACCOUNT_TOKEN', 'oauth token for the account that will be the bot');
define('TAGBOT_ACCOUNT_SECRET', 'oauth secret for account that will be the bot');
```
