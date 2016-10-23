# Slack Secret Santa app

Just go to https://slack-secret-santa.herokuapp.com/ and have fun.

Code source is under MIT License.

- This application is powered by Symfony 2.8 and his new Micro Kernel;
- Hosting is provided by Heroku;
- Session are stored in Heroku Redis servers;
- Frontend is built with bootstrap, obviously (any help welcome from designer ^^);
- For now, the calls to "ChatPostMessage" Slack API are done procedurally, this may be hard on the API / PHP / Heroku for big Secret Santa... Let us know!
- Built with ♥ by @pyrech and @damienalexandre.

## Install, run and deploy

- Download and install the Heroku Toolbelt 
- If you haven't already, log in to your Heroku account and follow the prompts to create a new SSH public key.
- Give your heroku details to Damien to be able to deploy

```
$ heroku login
$ heroku git:remote -a slack-secret-santa
$ heroku plugins:install heroku-redis
$ git push heroku master
```

The app require:

- a Redis server
- PHP 5.6+

As we rely on env variables, we cannot use `server:run`. From `web/`:

    cd web/ && SLACK_CLIENT_SECRET=TOTO SLACK_CLIENT_ID=TOTO php -d variables_order=EGPCS -S 127.0.0.1:8000 ../etc/router.php
    
Variables are:

- SLACK_CLIENT_SECRET: Application secret from Slack;
- SLACK_CLIENT_ID: Application id from Slack;
- REDIS_URL: The full redis connexion url (default `redis://localhost:6379`)
