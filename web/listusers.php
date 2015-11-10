<?php

require __DIR__.'/../vendor/autoload.php';

use CL\Slack\Transport\ApiClient;
use Joli\SlackSecretSanta\UserExtractor;

$TOKEN = '__TOKEN__';

$apiClient = new ApiClient($TOKEN);

$channelId = $argc > 1 ? $argv[1] : null;

$userExtractor = new UserExtractor($apiClient);

if ($channelId) {
    echo 'getting members for channel ', $channelId, PHP_EOL;

    $users = $userExtractor->extractAllFromChannel($channelId);
} else {
    echo 'getting all members', PHP_EOL;

    $users = $userExtractor->extractAll();
}

dump($users);

//
//$payload = new ChatPostMessagePayload();
//$payload->setChannel('#general');
//$payload->setMessage('Hello world!');
//
///** @var ChatPostMessagePayloadResponse $response */
//$response = $apiClient->send($payload);
//
//$apiClient->addResponseListener(function (ResponseEvent $event) use ($output, $self) {
//    echo "Received payload response:\n";
//    var_dump($event->getRawPayloadResponse()); // array containing the data that was returned by Slack
//});
//
//// the following is very much up to you, this is just a very simple example
//if ($response->isOk()) {
//    echo sprintf('Successfully posted message on %s', $response->getChannelId());
//} else {
//    echo sprintf('Failed to post message to Slack: %s', $response->getErrorExplanation());
//}

