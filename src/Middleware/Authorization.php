<?php

namespace Klsoft\Yii3Authz\Middleware;

use ReflectionException;
use ReflectionClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;
use Yiisoft\Http\Status;
use Klsoft\Yii3Authz\Permission;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\User\CurrentUser;

class Authorization implements MiddlewareInterface
{
    private array $permissions = [];

    public function __construct(
        private string                   $forbiddenUrl,
        private CurrentUser              $currentUser,
        private UrlMatcherInterface      $matcher,
        private ResponseFactoryInterface $responseFactory,
        private ContainerInterface       $container
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actioPermissions = empty($this->permissions) ? $this->getActionPermissions($request) : array_map(fn($permission) => $this->callPermissionParamsUserFunc($permission, $request), $this->permissions);
        if (
            !empty($actioPermissions) &&
            !$this->currentUserHasPermissions($actioPermissions)
        ) {
            return $this->responseFactory
                ->createResponse(Status::FOUND)
                ->withHeader('Location', $this->forbiddenUrl);
        }

        return $handler->handle($request);
    }

    private function getActionPermissions(ServerRequestInterface $request): array
    {
        $actionPermissions = [];
        $result = $this->matcher->match($request);
        if ($result->isSuccess()) {
            try {
                $middlewares = $result->route()->getData('enabledMiddlewares');
                if (!empty($middlewares)) {
                    $middlewareDefinition = $middlewares[count($middlewares) - 1];
                    if (!is_callable($middlewareDefinition)) {
                        $refClass = new ReflectionClass(is_array($middlewareDefinition) ? $middlewareDefinition[0] : $middlewareDefinition);
                        $refMethod = $refClass->getMethod(is_array($middlewareDefinition) ? $middlewareDefinition[1] : '__invoke');
                        $attributes = $refMethod->getAttributes(Permission::class);
                        foreach ($attributes as $attribute) {
                            $arguments = $attribute->getArguments();
                            if (!empty($arguments)) {
                                $params = $arguments[1] ?? [];
                                foreach ($params as $key => $value) {
                                    $params[$key] = $this->callParamValueUserFunc($value, $request);
                                }
                                $actionPermissions[] = new Permission(
                                    $arguments[0],
                                    $params
                                );
                            }
                        }
                    }
                }
            } catch (ReflectionException) {
                return [];
            }
        }

        return $actionPermissions;
    }

    private function callPermissionParamsUserFunc(
        Permission             $permission,
        ServerRequestInterface $request
    ): Permission
    {
        $params = $permission->params;
        foreach ($params as $key => $value) {
            $params[$key] = $this->callParamValueUserFunc($value, $request);
        }
        return new Permission(
            $permission->name,
            $permission->params
        );
    }

    private function callParamValueUserFunc(
        mixed                  $paramValue,
        ServerRequestInterface $request
    ): mixed
    {
        if (
            is_array($paramValue) &&
            count($paramValue) > 2 &&
            $paramValue[0] === '__container_entry_identifier'
        ) {
            $args = $paramValue[3] ?? [];
            for ($i = 0; $i < count($args); $i++) {
                if ($args[$i] === '__request') {
                    $args[$i] = $request;
                }
            }
            return call_user_func_array(
                array($this->container->get($paramValue[1]), $paramValue[2]),
                $args
            );
        }

        return $paramValue;
    }

    private function currentUserHasPermissions(array $actionPermissions): bool
    {
        foreach ($actionPermissions as $actionPermission) {
            if (!$this->currentUser->can($actionPermission->name, $actionPermission->params)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $permissions - The array contains instances of the Klsoft\Yii3Authz\Permission class.
     *
     * @return self
     */
    public function withPermissions(array $permissions): self
    {
        $new = clone $this;
        $new->permissions = array_filter($permissions, fn($item) => $item instanceof Permission);
        return $new;
    }
}
