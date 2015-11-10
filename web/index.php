<?php

if (empty($_ENV['SLACK_CLIENT_SECRET']) || empty($_ENV['SLACK_CLIENT_ID'])) {
    die('Missing env. variables, abort!');
}

include '../vendor/autoload.php';

session_start();

$provider = new Bramdevries\Oauth\Client\Provider\Slack([
    'clientId'          => $_ENV['SLACK_CLIENT_ID'],
    'clientSecret'      => $_ENV['SLACK_CLIENT_SECRET'],
    'redirectUri'       => 'https://slack-secret-santa.herokuapp.com/',
]);

if (!isset($_GET['code'])) {
    // If we don't have an authorization code then get one
    $options = [
        'scope' => ['chat:write:bot', 'users:read'] // array or string
    ];
    $authUrl = $provider->getAuthorizationUrl($options);

    var_dump($provider->getState());
    var_dump($authUrl);

    $_SESSION['oauth2state'] = $provider->getState();
    //header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        var_dump($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getNickname());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
