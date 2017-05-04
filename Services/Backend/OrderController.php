<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class OrderController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class OrderController extends BaseController implements BaseControllerInterface
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
     * @param Request $request
     * @param array   $arguments
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function order($entityPathConfig, Request $request, $arguments = [])
    {
        $renderVars['entityPathConfig'] = $entityPathConfig;

        $config = $this->getConfig($entityPathConfig, 'order');

        $urlData = $arguments;

        $doctrine = $this->get('doctrine');

        $entityRepository = $doctrine->getRepository($this->getRepositoryName($entityPathConfig));

        if ($request->getMethod() == 'POST') {

            $em = $doctrine->getManager();

            $data = $request->request->all();

            $entities = $entityRepository->get(
                'all',
                [
                    'conditions' => [
                        Inflector::camelize($entityPathConfig['entity']) . '.id' => $data['ids'],
                    ],
                ]
            );
            $orderedEntities = [];

            switch ($config['sortableType']) {

                case 'tree':
                    $existingLftRgt = [];
                    foreach ($entities as $entity) {
                        $existingLftRgt[] = [
                            'lft' => $entity->getLft(),
                            'rgt' => $entity->getRgt(),
                        ];
                        $orderedEntities[$entity->getId()] = $entity;
                    }

                    foreach ($data['ids'] as $key => $id) {
                        $orderedEntity = $orderedEntities[$id];

                        $orderedEntity->setLft($existingLftRgt[$key]['lft']);
                        $orderedEntity->setRgt($existingLftRgt[$key]['rgt']);

                        $em->persist($orderedEntity);
                        $em->flush();
                    }

                    $entityRepository->recover();
                    $em->flush();

                    break;

                case 'field':
                    foreach ($entities as $entity) {
                        $orderedEntities[$entity->getId()] = $entity;
                    }

                    foreach ($data['ids'] as $key => $id) {
                        $orderedEntity = $orderedEntities[$id];

                        $orderedEntity->{'set' . ucfirst($config['sortableField'])}(sizeof($data['ids']) - $key);

                        $em->persist($orderedEntity);
                        $em->flush();
                    }

                    break;
            }

            return new JsonResponse(
                [
                    'success' => true,
                    'reload'  => true,
                ]
            );
        }

        return new JsonResponse(
            [
                'success'  => true,
                'redirect' => $this->getActionUrl(
                    $entityPathConfig,
                    'index',
                    $urlData
                ),
            ]
        );
    }
}
