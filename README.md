# YII3-AUTHZ

The package provides [Yii 3](https://yii3.yiiframework.com) authorization middleware that uses Yii RBAC. It is intended for use with web applications. For authorization of a RESTful web service, use the [YII3-KEYCLOAK-AUTHZ](https://github.com/klsoft-web/yii3-keycloak-authz) package instead.

## Requirement

 - PHP 8.1 or higher.

## Installation

```bash
composer require klsoft/yii3-authz
```

## How to use

### 1. Configure Authentication

Example:

```php
use Yiisoft\Session\Session;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\Method\WebAuth;

return [
    // ...
    SessionInterface::class => [
        'class' => Session::class,
        '__construct()' => [
            $params['session']['options'] ?? [],
            $params['session']['handler'] ?? null,
        ],
    ],
    IdentityRepositoryInterface::class => IdentityRepository::class,
    CurrentUser::class => [
        'withSession()' => [Reference::to(SessionInterface::class)]
    ],
    AuthenticationMethodInterface::class => WebAuth::class,
];
```

### 2. [Configure](https://yiisoft.github.io/docs/guide/security/authorization.html#configuring-rbac) RBAC

### 3. Add the forbidden URL to param.php

Example:

```php
return [
    'forbiddenUrl' => '/forbidden',
];
```

### 4. Configure Authorization

Example:

```php
use Klsoft\Yii3Authz\Middleware\Authorization;

return [
    // ...
    Authorization::class => [
        'class' => Authorization::class,
        '__construct()' => [
            'forbiddenUrl' => $params['forbiddenUrl']
        ],
    ],
];
```

### 5. Apply permissions.

#### 5.1. To an action.

First, add Authorization to a route:

```php
use Yiisoft\Auth\Middleware\Authentication;
use Klsoft\Yii3Authz\Middleware\Authorization;

Route::post('/post/create')
        ->middleware(Authentication::class)
        ->middleware(Authorization::class)
        ->action([PostController::class, 'create'])
        ->name('post/create')
```

Or to a group of routes:

```php
use Yiisoft\Auth\Middleware\Authentication;
use Klsoft\Yii3Authz\Middleware\Authorization;

Group::create()
        ->middleware(Authentication::class)
        ->middleware(Authorization::class)
        ->routes(
            Route::post('/post/create')
                ->action([PostController::class, 'create'])
                ->name('post/create'),
            Route::put('/post/update/{id}')
                ->action([PostController::class, 'update'])
                ->name('post/update')
        )
```

Then, apply permissions to an action:

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Klsoft\Yii3Authz\Permission;

final class PostController
{
    public function __construct(private PostPresenterInterface $postPresenter)
    {
    }

    #[Permission('createPost')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return $this->postPresenter->createPost($request);
    }
}
```

Example of an **OR** permission:

```php
#[Permission('createPost|updatePost')]
public function edit(#[RouteArgument('id')] ?int $id = null, ServerRequestInterface $request): ResponseInterface
```

Example of a permission with an executing  parameter value that would be passed to the rules associated with the roles:

```php
#[Permission(  
    'updatePost',   
    ['post' => [  
        '__container_entry_identifier',  
        PostPresenterInterface::class,  
        'getPost',  
        ['__request']]  
    ]  
)]
public function update(#[RouteArgument('id')] int $id, ServerRequestInterface $request): ResponseInterface
```
#### 5.2. To a route.

First, define the set of permissions:

```php
use Psr\Container\ContainerInterface;
use Klsoft\Yii3Authz\Middleware\Authorization;
use Klsoft\Yii3Authz\Permission;

'CreatePostPermission' => static function (ContainerInterface $container) {
        return $container
            ->get(Authorization::class)
            ->withPermissions([
                new Permission('createPost'])
            ]);
    }
```

Then, you can apply this set to a route:

```php
use Yiisoft\Auth\Middleware\Authentication;

Route::post('/post/create')
        ->middleware(Authentication::class)
        ->middleware('CreatePostPermission')
        ->action([PostController::class, 'create'])
        ->name('post/create')
```
