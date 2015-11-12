# Slack Secret Santa app

Just go to https://slack-secret-santa.herokuapp.com/ and have fun.

<!--<a href="https://slack.com/oauth/authorize?scope=commands&client_id=2167807910.14252538375"><img 
alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" 
srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x"></a>-->

## Install

- Download and install the Heroku Toolbelt or learn more about the Heroku Command Line Interface.
- If you haven't already, log in to your Heroku account and follow the prompts to create a new SSH public key.
- Give your heroku details to Damien to be able to deploy


    $ heroku login
    $ heroku git:remote -a slack-secret-santa
    $ heroku plugins:install heroku-redis
    $ git push heroku master
    
## Run the project

The app require:

- a Redis server
- PHP 5.6+

As we rely on env variables, we cannot use `server:run`. From `web/`:

    SLACK_CLIENT_SECRET=TOTO SLACK_CLIENT_ID=TOTO php -d variables_order=EGPCS -S 127.0.0.1:8000 ../etc/router.php
    
Variables are:

- SLACK_CLIENT_SECRET: Application secret from Slack;
- SLACK_CLIENT_ID: Application id from Slack;
- REDIS_URL: The full redis connexion url (default `redis://localhost:6379`)
    
