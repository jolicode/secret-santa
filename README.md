# Secret Santa app

Just go to https://secret-santa.team/ and have fun.

Code source is under MIT License.

- This application is powered by Symfony and its new Flex way to build app;
- Hosting is provided by [Clever Cloud](https://www.clever-cloud.com/);
- Built with ♥ by [@pyrech](https://github.com/pyrech) and [@damienalexandre](https://github.com/damienalexandre).

## Install and run locally

The app requires:

- a Redis server
- PHP 7.1+

Run the following command to install the app locally:

`composer install`

We rely on some env variables to communicate with various API's and Redis.
Check out the `.env.dist` file and fill the correct values.

Then launch this command:

`make serve`

The application should now be running on http://127.0.0.1:8000.

Tests are made with PHPUnit  
To run unit tests, launch this command:

`make test`
