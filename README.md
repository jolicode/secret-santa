# Secret Santa app

Just go to https://secret-santa.team/ and have fun.

Code source is under MIT License.

- This application is powered by Symfony;
- Hosting is provided by [Clever Cloud](https://www.clever-cloud.com/);
- Built with ♥ by [@pyrech](https://github.com/pyrech) and [@damienalexandre](https://github.com/damienalexandre).

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

* Docker
* Bash
* pipenv (see [these instructions](https://pipenv.readthedocs.io/en/latest/install/) for how to install)

Install and run `pipenv` to install the required tools:

```bash
pipenv --three install
```

You can configure your current shell to be able to use Invoke commands directly
(without having to prefix everything by `pipenv run`)

```bash
pipenv shell
```

Optionally, in order to improve your usage of invoke scripts, you can install console autocompletion script.

If you are using bash:

```bash
invoke --print-completion-script=bash > /etc/bash_completion.d/invoke
```

If you are using something else, please refer to your shell documentation.
You may need to use `invoke --print-completion-script=zsh > /to/somewhere`.

Invoke supports completion for `bash`, `zsh` & `fish` shells.

### Docker environment

The Docker infrastructure provides a web stack with:
- NGINX
- Redis
- PHP
- Traefik
- A container with some tooling:
   - Composer
   - Node
   - Yarn / NPM

### Domain configuration (first time only)

Before running the application for the first time, ensure your domain names
point the IP of your Docker daemon by editing your `/etc/hosts` file.

This IP is probably `127.0.0.1` unless you run Docker in a special VM (docker-machine, dinghy, etc).

> **Note**
> The router binds port 80 and 443, that's why it will work with `127.0.0.1`

```
echo '127.0.0.1 secret-santa.test' | sudo tee -a /etc/hosts
```

Using dinghy? Run `dinghy ip` to get the IP of the VM.

### Env vars configuration (first time only)

We rely on some env variables to configure how to communicate with various
API's and Redis.

Copy the content of the file `.env` into a new `.env.local` (which will be
ignored by git) and fill the missing vars with correct values.

### Starting the stack

Launch the stack by running this command:

```bash
inv start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at the hostnames your have configured over HTTPS
(you may need to accept self-signed SSL certificate if you do not have mkcert
installed on your computer - see below).

### SSL certificates

This stack no longer embeds self-signed SSL certificates. Instead they will be
generated the first time you start the infrastructure (`inv start`) or if you
run `inv generate-certificates`. So *HTTPS will work out of the box*.

If you have `mkcert` installed on your computer, it will be used to generate
locally trusted certificates. See [`mkcert` documentation](https://github.com/FiloSottile/mkcert#installation)
to understand how to install it. Do not forget to install CA root from mkcert
by running `mkcert -install`.

If you don't have `mkcert`, then self-signed certificates will instead be
generated with openssl. You can configure [infrastructure/docker/services/router/openssl.cnf](infrastructure/docker/services/router/openssl.cnf)
to tweak certificates.

You can run `inv generate-certificates --force` to recreate new certificates
if some were already generated. Remember to restart the infrastructure to make
use of the new certificates with `inv up` or `inv start`.

### Builder

Having some composer, yarn or other modifications to make on the project?
Start the builder which will give you access to a container with all these
tools available:

```bash
inv builder
```

Note: You can add as many Invoke commands as you want. If a command should be
ran by the builder, don't forget to use `with Builder(c):`:
```
@task
def mycommand(c):
    """
    My documentation
    """
    with Builder(c):
        docker_compose_run(c, 'echo "HelloWorld")
```

### Tests

Tests are made with PHPUnit.  
To run unit tests, launch this command:

```bash
inv tests
```

### Other tasks

Checkout `inv -l` to have the list of available Invoke tasks.
