<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * Les proxies de confiance pour l'application.
     * '*' = tous (recommandé derrière Coolify / Nginx / Load balancer)
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * Les headers utilisés pour détecter HTTPS correctement.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}