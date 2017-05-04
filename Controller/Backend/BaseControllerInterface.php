<?php

namespace WH\BackendBundle\Controller\Backend;

/**
 * Class BaseControllerInterface
 *
 * @package WH\BackendBundle\Controller\Backend
 */
interface BaseControllerInterface
{

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfig($config);
}
