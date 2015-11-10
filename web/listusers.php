<?php

require __DIR__ . '/../vendor/autoload.php';

use CL\Slack\Payload\ChannelsInfoPayload;
use CL\Slack\Payload\ChannelsInfoPayloadResponse;
use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Payload\ChatPostMessagePayloadResponse;
use CL\Slack\Payload\UsersListPayload;
use CL\Slack\Payload\UsersListPayloadResponse;
use CL\Slack\Transport\ApiClient;
use CL\Slack\Transport\Events\ResponseEvent;

$TOKEN = '__TOKEN__';

$apiClient = new ApiClient($TOKEN);

$channelId = $argc > 1 ? $argv[1] : null;

if ($channelId) {
    echo 'getting members for channel ', $channelId, PHP_EOL;

    $payload = new ChannelsInfoPayload();
    $payload->setChannelId($channelId);

    /** @var $response ChannelsInfoPayloadResponse */
    $response = $apiClient->send($payload);

    if ($response->isOk()) {
        // information has been retrieved
        $response->getChannel()->getId(); // ID of the channel
        $response->getChannel()->getName(); // name of the channel
        // $response->get...


        $members = $response->getChannel()->getMembers();
    } else {
        // something went wrong, but what?

        // simple error (Slack's error message)
        echo $response->getError(), PHP_EOL;

        // explained error (Slack's explanation of the error, according to the documentation)
        echo $response->getErrorExplanation();

        die();
    }
} else {
    echo 'getting all members', PHP_EOL;

    $payload = new UsersListPayload();
    $payload->getResponseClass();

    /** @var $response UsersListPayloadResponse */
    $response = $apiClient->send($payload);

    if ($response->isOk()) {

        $members = $response->getUsers();

    } else {
        // something went wrong, but what?

        // simple error (Slack's error message)
        echo $response->getError(), PHP_EOL;

        // explained error (Slack's explanation of the error, according to the documentation)
        echo $response->getErrorExplanation();

        die();
    }
}

dump($members);



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
