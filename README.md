# Slack Secret Santa app

Just go to https://slack-secret-santa.herokuapp.com/ and have fun.

Code source is under MIT License.

- This application is powered by Symfony and its new Flex way to build app;
- Hosting is provided by Heroku;
- Session are stored in Heroku Redis servers;
- Built with ♥ by [@pyrech](https://github.com/pyrech) and [@damienalexandre](https://github.com/damienalexandre).

## Install and run locally

The app requires:

- a Redis server
- PHP 7.1+

Run the following command to install the app locally:

`composer install`

We rely on some env variables to communicate with Slack API and Redis.
Check out the `.env` file and fill the correct values for the following variables:

- `SLACK_CLIENT_ID`: Application id from Slack;
- `SLACK_CLIENT_SECRET`: Application secret from Slack;
- `REDIS_URL`: The full redis connexion url (default `redis://localhost:6379`)

Then launch this command:

`make serve`

The application should now be running on http://127.0.0.1:8000.

To run unit tests, launch this command:

`make tests`

## Deploy to Heroku

- Download and install the Heroku Toolbelt 
- If you haven't already, log in to your Heroku account and follow the prompts to create a new SSH public key.
- Give your heroku details to Damien to be able to deploy

```
$ heroku login
$ heroku git:remote -a slack-secret-santa
$ heroku plugins:install heroku-redis
$ git push heroku master
```
