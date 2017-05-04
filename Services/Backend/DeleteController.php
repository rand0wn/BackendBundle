<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class DeleteController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class DeleteController extends BaseController implements BaseControllerInterface
{

    protected $container;

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
     * @param         $entityPathConfig
     * @param         $id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function delete($entityPathConfig, $id)
    {
        $em = $this->container->get('doctrine')->getManager();

        $data = $em->getRepository($this->getRepositoryName($entityPathConfig))->get(
            'one',
            [
                'conditions' => [
                    Inflector::camelize($entityPathConfig['entity']) . '.id' => $id,
                ],
            ]
        );

        $em->remove($data);
        $em->flush();

        $config = $this->getConfig($entityPathConfig, 'delete');
        if (isset($config['redirectAction'])) {
            return $this->redirect($this->getActionUrl($entityPathConfig, $config['redirectAction'], $data));
        }

        return $this->redirect($this->getActionUrl($entityPathConfig, 'index', $data));
    }

}
