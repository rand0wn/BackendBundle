<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Translator
 *
 * @package WH\BackendBundle\Services\Backend
 */
class Translator
{

    private $container;
    private $domain = 'WHBackendBundle';

    /**
     * SearchController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param       $stringToTranslate
     * @param array $parameters
     *
     * @return string
     */
    public function trans($stringToTranslate, $parameters = [])
    {
        return $this->container->get('translator')->trans(
            $stringToTranslate,
            $parameters,
            $this->domain
        );
    }
}
