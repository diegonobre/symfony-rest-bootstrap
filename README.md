Symfony REST Bootstrap
======================

Implementation based on this [gist](https://gist.github.com/diegonobre/341eb7b793fc841c0bba3f2b865b8d66)

The API we are creating will follow these rules :

- [x] The API only returns JSON responses
- [x] All API routes require authenticationu
- [x] Authentication is handled via OAuth2 with `password` Grant Type only (no need for Authorization pages and such).
- [x] API versioning is managed via a subdomain (e.g. `v1.api.example.com`)

The API will be written in PHP with the Symfony 3 framework. The following SF2 bundles are used :

- https://github.com/FriendsOfSymfony/FOSRestBundle
- https://github.com/FriendsOfSymfony/FOSUserBundle
- https://github.com/FriendsOfSymfony/FOSOAuthServerBundle
- https://github.com/schmittjoh/JMSSerializerBundle
- https://github.com/nelmio/NelmioApiDocBundle

# Install SF2 and the bundles


The first step is to download Symfony and the related bundles. I willl use the [Symfony Installer](http://symfony.com/doc/current/quick_tour/the_big_picture.html#installing-symfony) and [Composer (installed globally)](https://getcomposer.org/doc/00-intro.md#globally)

```bash
composer create-project symfony/framework-standard-edition api
cd api
composer require friendsofsymfony/rest-bundle
composer require jms/serializer-bundle
composer require nelmio/api-doc-bundle
composer require friendsofsymfony/user-bundle "~2.0@dev" # until today just this version works with Symfony3
composer require friendsofsymfony/oauth-server-bundle
```

Add the following lines to `app/AppKernel.php` to enable the downloaded bundles :

```php
// app/AppKernel.php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new FOS\RestBundle\FOSRestBundle(),
            new FOS\UserBundle\FOSUserBundle(),
            new FOS\OAuthServerBundle\FOSOAuthServerBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
        );

        // ...
    }
}
```

## Configure bundles

A bit of configuration is required now.

**NOTE** : the classes under the `AppBundle\Entity` namespace will be created in just a minute.

### Configuration

Add the following to `app/config/config.yml` :

```yml
# app/config/config.yml
nelmio_api_doc: ~

fos_rest:
    routing_loader:
        default_format: json                            # All responses should be JSON formated
        include_format: false                           # We do not include format in request, so that all responses
                                                        # will eventually be JSON formated
    format_listener:
        rules:
            - { priorities: ['json', 'xml'], fallback_format: json, prefer_extension: false }
    view:
        view_response_listener: true

fos_user:
    db_driver: orm
    firewall_name: api                                  # Seems to be used when registering user/reseting password,
                                                        # but since there is no "login", as so it seems to be useless in
                                                        # our particular context, but still required by "FOSUserBundle"
    user_class: AppBundle\Entity\User

fos_oauth_server:
    db_driver:           orm
    client_class:        AppBundle\Entity\Client
    access_token_class:  AppBundle\Entity\AccessToken
    refresh_token_class: AppBundle\Entity\RefreshToken
    auth_code_class:     AppBundle\Entity\AuthCode
    service:
        user_provider: fos_user.user_manager             # This property will be used when valid credentials are given to load the user upon access token creation
```

### Security

Add the following to `app/config/security.yml` :

```yml
# app/config/security.yml

security:
    encoders:
        FOS\UserBundle\Model\UserInterface: sha512

    providers:
        fos_userbundle:
            id: fos_user.user_provider.username        # fos_user.user_provider.username_email does not seem to work (OAuth-spec related ("username + password") ?)
    firewalls:
        oauth_token:                                   # Everyone can access the access token URL.
            pattern: ^/oauth/v2/token
            security: false
        api:
            pattern: ^/                                # All URLs are protected
            fos_oauth: true                            # OAuth2 protected resource
            stateless: true                            # Do no set session cookies
            anonymous: false                           # Anonymous access is not allowed
```

You can add more `access_control` properties here.

### Routing

Add the following to `app/config/routing.yml` :

```yml
# app/config/routing.yml
NelmioApiDocBundle:
    resource: "@NelmioApiDocBundle/Resources/config/routing.yml"
    prefix:   /api/doc

fos_oauth_server_token:
    resource: "@FOSOAuthServerBundle/Resources/config/routing/token.xml"
```

## User entity

This entity is required by `FOSUserBundle` and will also be used by `FOSOAuthServerBundle`. [As stated in the documentation](https://symfony.com/doc/master/bundles/FOSUserBundle/index.html#step-3-create-your-user-class), you are free to do (almost) whatever you want to with this class.
The one used in this gist is just a simple copy/paste of the [class available in the documentation](https://symfony.com/doc/master/bundles/FOSUserBundle/index.html#a-doctrine-orm-user-class), but with the following changes :

- the name of the table is customized : `@ORM\Table("users")`


```php
<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Table("users")
 * @ORM\Entity
 */
class User extends BaseUser
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
}
```

## Other entities

These entities are required by the `FOSOAuthServerBundle`. They are simple copy/paste from the documentation with namespace adjustements. Notice the table names have been adjusted too. Also, make sure the `targetEntity` parameter of the `@ORM\ManyToOne` annotation points to the user entity you created in the previous step :

```php
<?php
// src/AppBundle/Entity/Client.php

namespace AppBundle\Entity;

use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table("oauth2_clients")
 * @ORM\Entity
 */
class Client extends BaseClient
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    public function __construct()
    {
        parent::__construct();
    }
}
```

```php
<?php
// src/AppBundle/Entity/AccessToken.php

namespace AppBundle\Entity;

use FOS\OAuthServerBundle\Entity\AccessToken as BaseAccessToken;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table("oauth2_access_tokens")
 * @ORM\Entity
 */
class AccessToken extends BaseAccessToken
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $client;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;
}
```

```php
<?php
// src/AppBundle/Entity/RefreshToken.php

namespace AppBundle\Entity;

use FOS\OAuthServerBundle\Entity\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table("oauth2_refresh_tokens")
 * @ORM\Entity
 */
class RefreshToken extends BaseRefreshToken
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $client;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;
}
```

```php
<?php
// src/AppBundle/Entity/AuthCode.php

namespace AppBundle\Entity;

use FOS\OAuthServerBundle\Entity\AuthCode as BaseAuthCode;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table("oauth2_auth_codes")
 * @ORM\Entity
 */
class AuthCode extends BaseAuthCode
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $client;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;
}
```

You can now update your database schema :

```shell
php bin/console doctrine:schema:update --force
```

You should have the following tables created :

```
mysql> describe users;
+-----------------------+--------------+------+-----+---------+----------------+
| Field                 | Type         | Null | Key | Default | Extra          |
+-----------------------+--------------+------+-----+---------+----------------+
| id                    | int(11)      | NO   | PRI | NULL    | auto_increment |
| username              | varchar(255) | NO   |     | NULL    |                |
| username_canonical    | varchar(255) | NO   | UNI | NULL    |                |
| email                 | varchar(255) | NO   |     | NULL    |                |
| email_canonical       | varchar(255) | NO   | UNI | NULL    |                |
| enabled               | tinyint(1)   | NO   |     | NULL    |                |
| salt                  | varchar(255) | NO   |     | NULL    |                |
| password              | varchar(255) | NO   |     | NULL    |                |
| last_login            | datetime     | YES  |     | NULL    |                |
| locked                | tinyint(1)   | NO   |     | NULL    |                |
| expired               | tinyint(1)   | NO   |     | NULL    |                |
| expires_at            | datetime     | YES  |     | NULL    |                |
| confirmation_token    | varchar(255) | YES  |     | NULL    |                |
| password_requested_at | datetime     | YES  |     | NULL    |                |
| roles                 | longtext     | NO   |     | NULL    |                |
| credentials_expired   | tinyint(1)   | NO   |     | NULL    |                |
| credentials_expire_at | datetime     | YES  |     | NULL    |                |
+-----------------------+--------------+------+-----+---------+----------------+
17 rows in set (0.00 sec)

mysql> describe oauth2_clients;
+---------------------+--------------+------+-----+---------+----------------+
| Field               | Type         | Null | Key | Default | Extra          |
+---------------------+--------------+------+-----+---------+----------------+
| id                  | int(11)      | NO   | PRI | NULL    | auto_increment |
| random_id           | varchar(255) | NO   |     | NULL    |                |
| redirect_uris       | longtext     | NO   |     | NULL    |                |
| secret              | varchar(255) | NO   |     | NULL    |                |
| allowed_grant_types | longtext     | NO   |     | NULL    |                |
+---------------------+--------------+------+-----+---------+----------------+
5 rows in set (0.00 sec)

mysql> describe oauth2_access_tokens;
+------------+--------------+------+-----+---------+----------------+
| Field      | Type         | Null | Key | Default | Extra          |
+------------+--------------+------+-----+---------+----------------+
| id         | int(11)      | NO   | PRI | NULL    | auto_increment |
| client_id  | int(11)      | NO   | MUL | NULL    |                |
| user_id    | int(11)      | YES  | MUL | NULL    |                |
| token      | varchar(255) | NO   | UNI | NULL    |                |
| expires_at | int(11)      | YES  |     | NULL    |                |
| scope      | varchar(255) | YES  |     | NULL    |                |
+------------+--------------+------+-----+---------+----------------+
6 rows in set (0.00 sec)

mysql> describe oauth2_auth_codes;
+--------------+--------------+------+-----+---------+----------------+
| Field        | Type         | Null | Key | Default | Extra          |
+--------------+--------------+------+-----+---------+----------------+
| id           | int(11)      | NO   | PRI | NULL    | auto_increment |
| client_id    | int(11)      | NO   | MUL | NULL    |                |
| user_id      | int(11)      | YES  | MUL | NULL    |                |
| token        | varchar(255) | NO   | UNI | NULL    |                |
| redirect_uri | longtext     | NO   |     | NULL    |                |
| expires_at   | int(11)      | YES  |     | NULL    |                |
| scope        | varchar(255) | YES  |     | NULL    |                |
+--------------+--------------+------+-----+---------+----------------+
7 rows in set (0.00 sec)

mysql> describe oauth2_refresh_tokens;
+------------+--------------+------+-----+---------+----------------+
| Field      | Type         | Null | Key | Default | Extra          |
+------------+--------------+------+-----+---------+----------------+
| id         | int(11)      | NO   | PRI | NULL    | auto_increment |
| client_id  | int(11)      | NO   | MUL | NULL    |                |
| user_id    | int(11)      | YES  | MUL | NULL    |                |
| token      | varchar(255) | NO   | UNI | NULL    |                |
| expires_at | int(11)      | YES  |     | NULL    |                |
| scope      | varchar(255) | YES  |     | NULL    |                |
+------------+--------------+------+-----+---------+----------------+
6 rows in set (0.00 sec)
```

## Add Oauth2 client

The following step consists in adding a new OAuth2 client. The documentation is not very clear on that point, [the following code can be injected in a command to create new client](https://github.com/FriendsOfSymfony/FOSOAuthServerBundle/blob/master/Resources/doc/index.md#creating-a-client). In our case, we need
only one client, so I add the client manually with a simple SQL query :

```sql
INSERT INTO `oauth2_clients` VALUES (NULL, '3bcbxd9e24g0gk4swg0kwgcwg4o8k8g4g888kwc44gcc0gwwk4', 'a:0:{}', '4ok2x70rlfokc8g0wws8c8kwcokw80k44sg48goc0ok4w0so0k', 'a:1:{i:0;s:8:"password";}');
```
## Create admin user

We are going to use the command `fos:user:create`, provided by `FOSUserBundle` :

```shell
$ php bin/console fos:user:create
Please choose a username:admin
Please choose an email:admin@example.com
Please choose a password:admin
Created user admin
```

## Create a REST controller

We can now create a REST controller to deliver a very simple resource, so that we can test that our setup is working properly.

### The controller
```php
<?php

// src/AppBundle/Controller/ApiController.php

namespace AppBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;

class ApiController extends FOSRestController
{
    /**
     * @Route("/api")
     */
    public function indexAction()
    {
        $data = array("hello" => "world");
        $view = $this->view($data);
        return $this->handleView($view);
    }
}
```

## Check OAuth2 is working

**NOTE** : the following commands make use of the [HTTPie library](https://github.com/jakubroztocil/httpie). Make sure it is installed on your system before using it.

**NOTE 2** : the following commands assume you are [running Symfony with the built-in HTTP server](http://symfony.com/doc/current/quick_tour/the_big_picture.html#running-symfony).
Adapt to fit your configuration.


```shell
$ http GET http://localhost:8000/app_dev.php/api
HTTP/1.1 401 Unauthorized
Cache-Control: no-store, private
Connection: close
Content-Type: application/json
...

{
    "error": "access_denied",
    "error_description": "OAuth2 authentication required"
}
```

We are not welcome here :(

We should now request an Access Token using the client and the user we created earlier. Notice the `client_id` parameter is a concatenation of the
client id, an underscore and the client randomId :

```shell
$ http POST http://localhost:8000/app_dev.php/oauth/v2/token \
    grant_type=password \
    client_id=1_3bcbxd9e24g0gk4swg0kwgcwg4o8k8g4g888kwc44gcc0gwwk4 \
    client_secret=4ok2x70rlfokc8g0wws8c8kwcokw80k44sg48goc0ok4w0so0k \
    username=admin \
    password=admin
HTTP/1.1 200 OK
Cache-Control: no-store, private
Connection: close
Content-Type: application/json
...

{
    "access_token": "MDFjZGI1MTg4MTk3YmEwOWJmMzA4NmRiMTgxNTM0ZDc1MGI3NDgzYjIwNmI3NGQ0NGE0YTQ5YTVhNmNlNDZhZQ",
    "expires_in": 3600,
    "refresh_token": "ZjYyOWY5Yzg3MTg0MDU4NWJhYzIwZWI4MDQzZTg4NWJjYzEyNzAwODUwYmQ4NjlhMDE3OGY4ZDk4N2U5OGU2Ng",
    "scope": null,
    "token_type": "bearer"
}
```
We can use the Acces Token we've just been given to authenticate on the next request :

```shell
$ http GET http://ledzep.dev:8000/app_dev.php/api \
    "Authorization:Bearer MDFjZGI1MTg4MTk3YmEwOWJmMzA4NmRiMTgxNTM0ZDc1MGI3NDgzYjIwNmI3NGQ0NGE0YTQ5YTVhNmNlNDZhZQ"
HTTP/1.1 200 OK
Cache-Control: no-cache
Connection: close
Content-Type: application/json
...

{
    "hello": "world"
}
```

## User information

### Get current authenticated user

```php
<?php

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// ...
class ApiController extends FOSRestController
{
    // ...
    public function indexAction()
    {
        $user = $this->get('security.context')->getToken()->getUser();

        //...
        // Do something with the fully authenticated user.
        // ...
    }
    // ...
}
```

### Check user grants

```php
<?php

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// ...
class ApiController extends FOSRestController
{
    // ...
    public function indexAction()
    {
        if ($this->get('security.context')->isGranted('ROLE_JCVD') === FALSE) {
            throw new AccessDeniedException();
        }

        // ...
    }
    // ...
}
```
