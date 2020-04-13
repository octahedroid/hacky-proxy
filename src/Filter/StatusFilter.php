<?php

namespace Stevector\HackyProxy\Filter;

use Proxy\Filter\FilterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StatusFilter implements FilterInterface
{

    protected $status = 0;

    function __construct($status)
    {
        $this->status = $status;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {
        $response = $next($request, $response);

        if ($this->status > 0) {
            return $response->withStatus($this->status);
        }

        return $response;
    }
}
