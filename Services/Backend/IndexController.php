<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class IndexController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class IndexController extends BaseController implements BaseControllerInterface
{

    public $container;

    public $search = false;
    public $sortable = false;
    public $tree = false;

    public $renderVars;

    public $config;
    public $globalConfig;

    public $entityPathConfig;
    public $request;
    public $arguments;

    public $conditions;

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
    public function index($entityPathConfig, Request $request, $arguments = [])
    {
        $this->entityPathConfig = $entityPathConfig;
        $this->request = $request;
        $this->arguments = $arguments;

        $this->config = $this->getConfig($entityPathConfig, 'index');
        $this->globalConfig = $this->getGlobalConfig($entityPathConfig);
        $this->arguments = $arguments;

        $this->renderVars['globalConfig'] = $this->globalConfig;

        $this->conditions = [];

        foreach ($arguments as $condition => $value) {
            $this->conditions[$condition] = $value;
        }

        if ($this->search) {
            $return = $this->handleSearchForm();
            if ($this->request->getMethod() == 'POST') {
                return $return;
            }
        }

        if ($this->tree) {
            $this->renderVars['tree'] = true;

            if (isset($this->config['treeRootLabel'])) {
                switch ($this->config['treeRootLabel']['type']) {
                    case 'entity':
                        $em = $this->get('doctrine')->getManager();
                        $entity = $em->getRepository($this->config['treeRootLabel']['class'])->get(
                            'one',
                            [
                                'conditions' => [
                                    $this->config['treeRootLabel']['dataField'] => $arguments[$this->config['treeRootLabel']['dataField']],
                                ],
                            ]
                        );

                        if ($entity) {
                            $this->renderVars['treeRootLabel'] = $this->getVariableValue(
                                $this->config['treeRootLabel']['field'],
                                $entity
                            );
                        }

                        break;
                }
            }
        }

        $this->getTablePanelProperties();

        if ($this->tree) {
            $this->getEntities();
        } else {
            $this->getPagination();
        }

        if ($this->sortable) {
            $this->renderVars['orderUrl'] = $this->getActionUrl($entityPathConfig, 'order', $arguments);
        }

        $this->renderVars['title'] = $this->config['title'];

        $this->renderVars['breadcrumb'] = $this->getBreadcrumb(
            $this->config['breadcrumb'],
            $entityPathConfig,
            $arguments
        );

        $view = '@WHBackendTemplate/BackendTemplate/View/index.html.twig';
        if (isset($this->config['view'])) {
            $view = $this->config['view'];
        }

        if (isset($this->config['layout'])) {
            $this->renderVars['layout'] = $this->config['layout'];
        }

        $this->renderVars = $this->translateRenderVars($entityPathConfig, $this->renderVars);

        return $this->container->get('templating')->renderResponse(
            $view,
            $this->renderVars
        );
    }

    /**
     * @return bool
     */
    public function validConfig($config)
    {
        $this->config = $config;

        if (isset($this->config['search']) && $this->config['search'] == 'true') {
            $this->validConfigSearch();
            $this->search = true;
        }

        if (isset($this->config['tree']) && $this->config['tree'] == 'true') {
            $this->validConfigTree();
            $this->tree = true;
        }

        if (isset($this->config['sortable']) && $this->config['sortable'] == 'true') {
            $this->sortable = true;
        }

        if (!isset($this->config['tablePanelProperties'])) {
            throw new NotFoundHttpException(
                'Le fichier de configuration ne contient pas le champ "tablePanelProperties"'
            );
        }

        if (!isset($this->config['tablePanelProperties']['tableFields'])) {
            throw new NotFoundHttpException(
                'Le champ "tableFields" fichier de configuration n\'est pas présent sous "tablePanelProperties"'
            );
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validConfigSearch()
    {
        if (!isset($this->config['formPanelProperties'])) {
            throw new NotFoundHttpException(
                'Le fichier de configuration ne contient pas le champ "formPanelProperties"'
            );
        }

        if (!isset($this->config['formPanelProperties']['formFields'])) {
            throw new NotFoundHttpException(
                'Le champ "formFields" fichier de configuration n\'est pas présent sous "formPanelProperties"'
            );
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validConfigTree()
    {
        return true;
    }

    /**
     * * Gère les propriétés à envoyer à la vue pour l'affichage de la liste des entités dans le tableau
     *
     * @return bool
     */
    public function getTablePanelProperties()
    {
        $tablePanelProperties = $this->config['tablePanelProperties'];

        if ($this->sortable) {
            $tablePanelProperties['sortable'] = true;
        }

        // thead
        if (isset($tablePanelProperties['headerListButtons'])) {
            foreach ($tablePanelProperties['headerListButtons'] as $key => $headerListButton) {
                $headerListButton['href'] = $this->getActionUrl(
                    $this->entityPathConfig,
                    $headerListButton['action'],
                    $this->arguments
                );
                $tablePanelProperties['headerListButtons'][$key] = $headerListButton;
            }
        }

        // tbody
        foreach ($tablePanelProperties['tableFields'] as $entityFieldName => $tableField) {
            if (!empty($this->globalConfig['formFields'][$entityFieldName])) {
                $entityFieldGlobalConfig = $this->globalConfig['formFields'][$entityFieldName];
                if (!empty($entityFieldGlobalConfig['label'])) {
                    $tableField['label'] = $entityFieldGlobalConfig['label'];
                }
            }
            if (is_array($tableField) && key_exists('multipleFields', $tableField)) {
                foreach ($tableField as $key => $multipleField) {
                    $tableField[$key] = $multipleField;
                }
            }
            $tablePanelProperties['tableFields'][$entityFieldName] = $tableField;
        }

        if (isset($tablePanelProperties['tableFields']['buttons'])) {
            $tablePanelProperties['tableFields']['buttons'] = $this->transformActionIntoRoute(
                $tablePanelProperties['tableFields']['buttons'],
                $this->entityPathConfig
            );
        }

        $this->renderVars['tablePanelProperties'] = $tablePanelProperties;

        return true;
    }

    /**
     * @return bool
     */
    public function handleSearchForm()
    {
        $formFields = $this->config['formPanelProperties']['formFields'];

        $form = $this->getForm($formFields);

        if ($this->request->getMethod() == 'POST') {
            return $this->handleSearchFormSubmission($form);
        }

        // Data initialisation
        $data = $this->container->get('session')->get($this->getSlug($this->entityPathConfig) . 'search');

        $em = $this->container->get('doctrine')->getManager();

        foreach ($formFields as $formFieldSlug => $formFieldProperties) {
            if (isset($data[$formFieldSlug])) {
                switch ($formFieldProperties['type']) {
                    case 'entity':
                        $className = lcfirst(preg_replace('#.*:(.*)#', '$1', $formFieldProperties['class']));
                        $data[$formFieldSlug] = $em->getRepository($formFieldProperties['class'])->get(
                            'one',
                            [
                                'conditions' => [
                                    $className . '.id' => $data[$formFieldSlug],
                                ],
                            ]
                        );
                        break;
                }
            }
        }

        $form->setData($data);

        // Get conditions from data
        $conditions = $this->getConditionsFromData(
            Inflector::camelize($this->entityPathConfig['entity']),
            $formFields,
            $data
        );
        $this->conditions = array_merge(
            $this->conditions,
            $conditions
        );

        // Variables pour l'affichage du formulaire dans la vue
        $this->getSearchFormViewVariables($form);

        return true;
    }

    /**
     * @param Form $form
     *
     * @return bool|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleSearchFormSubmission(Form $form)
    {
        $form->handleRequest($this->request);

        if ($form->isSubmitted()) {
            $data = $this->request->request->get($form->getName());

            $formFields = $this->config['formPanelProperties']['formFields'];

            $formData = [];

            foreach ($formFields as $formFieldSlug => $formFieldProperties) {
                if (isset($data[$formFieldSlug])) {
                    $formData[$formFieldSlug] = $data[$formFieldSlug];

                    switch ($formFieldProperties['type']) {
                        case 'date':
                            $value = $data[$formFieldSlug];
                            if (!$value['day'] || !$value['month'] || !$value['year']) {
                                $formData[$formFieldSlug] = null;
                            } else {
                                $date = $value['year'] . '-';
                                $date .= str_pad($value['month'], 2, '0', STR_PAD_LEFT) . '-';
                                $date .= str_pad($value['day'], 2, '0', STR_PAD_LEFT);
                                $date = new \DateTime($date);
                                $formData[$formFieldSlug] = $date;
                            }
                            break;

                        case 'datetime':
                            $value = $data[$formFieldSlug];

                            if ($value['date']['day'] == '' || $value['date']['month'] == '' || $value['date']['year'] == '' || $value['time']['minute'] == '' || $value['time']['hour'] == '') {
                                $formData[$formFieldSlug] = null;
                            } else {
                                $date = $value['date']['year'] . '-';
                                $date .= str_pad($value['date']['month'], 2, '0', STR_PAD_LEFT) . '-';
                                $date .= str_pad($value['date']['day'], 2, '0', STR_PAD_LEFT) . ' ';
                                $date .= str_pad($value['time']['hour'], 2, '0', STR_PAD_LEFT) . ':';
                                $date .= str_pad($value['time']['minute'], 2, '0', STR_PAD_LEFT) . ' ';
                                $date = new \DateTime($date);
                                $formData[$formFieldSlug] = $date;
                            }
                            break;
                    }
                }
            }

            $this->container->get('session')->set($this->getSlug($this->entityPathConfig) . 'search', $formData);

            return $this->redirect(
                $this->getActionUrl($this->entityPathConfig, 'index')
            );
        }

        return true;
    }

    /**
     * @param Form $form
     *
     * @return bool
     */
    public function getSearchFormViewVariables(Form $form)
    {
        $this->renderVars['formPanelProperties'] = $this->config['formPanelProperties'];

        $this->renderVars['formPanelProperties']['form'] = $form->createView();

        return true;
    }

    /**
     * Gère la liste des entités lorsqu'il n'y a pas de pagination
     *
     * @return bool
     */
    public function getEntities()
    {
        $entityRepository = $this->get('doctrine')->getRepository(
            $this->getRepositoryName($this->entityPathConfig)
        );

        $entities = $entityRepository->get(
            'all',
            [
                'conditions' => $this->conditions,
            ]
        );
        $this->renderVars['tablePanelProperties']['entities'] = $entities;

        $this->renderVars['entityPathConfig'] = $this->entityPathConfig;

        $this->renderVars['urlData'] = $this->arguments;
        $this->renderVars['orderUrl'] = $this->getActionUrl($this->entityPathConfig, 'order', $this->arguments);

        return true;
    }

    /**
     * Gère la pagination
     *
     * @return bool
     */
    public function getPagination()
    {
        $entityRepository = $this->get('doctrine')->getRepository(
            $this->getRepositoryName($this->entityPathConfig)
        );

        $paginationPage = 1;
        if ($this->request->query->get('page')) {
            $paginationPage = $this->request->query->get('page');
        }

        $paginationLimit = 25;

        $paginator = $entityRepository->get(
            'paginate',
            [
                'paginate'   => [
                    'page'  => $paginationPage,
                    'limit' => $paginationLimit,
                ],
                'conditions' => $this->conditions,
            ]
        );
        $entities = $paginator['entities'];
        $this->renderVars['tablePanelProperties']['entities'] = $entities;

        $pagination = [
            'page'  => $paginationPage,
            'limit' => $paginationLimit,
            'count' => $paginator['count'],
            'url'   => $this->getActionUrl($this->entityPathConfig, 'index', $this->arguments),
        ];

        $this->renderVars['pagination'] = $pagination;

        return true;
    }

    /**
     * @param $entity
     * @param $formFields
     * @param $data
     *
     * @return array
     */
    public function getConditionsFromData($entity, $formFields, $data)
    {
        $conditions = [];

        foreach ($formFields as $formField => $properties) {

            if (!isset($data[$formField])) {
                continue;
            }

            $value = $data[$formField];

            if (!$value) {
                continue;
            }

            $defaultExpression = $entity . '.' . $formField;

            if (preg_match('#.*\..*#', $properties['conditionField'])) {
                $defaultExpression = $properties['conditionField'];
            } else {
                if (!empty($properties['conditionField'])) {
                    $defaultExpression = $entity . '.' . $properties['conditionField'];
                }
            }

            if (!empty($properties['conditionType'])) {
                switch ($properties['conditionType']) {
                    case 'inferior':
                        $defaultExpression .= ' <';
                        break;

                    case 'inferiorOrEqual':
                        $defaultExpression .= ' <=';
                        break;

                    case 'superior':
                        $defaultExpression .= ' >';
                        break;

                    case 'superiorOrEqual':
                        $defaultExpression .= ' >=';
                        break;

                    case 'like':
                        $defaultExpression .= ' LIKE';
                        $value = '%' . $value . '%';
                        break;

                    case 'joinedEntity':
                        $defaultExpression = $formField . '.id';
                        break;
                }
            }

            $conditions[$defaultExpression] = $value;
        }

        return $conditions;
    }

    /**
     * @param $entityPathConfig
     * @param $renderVars
     *
     * @return mixed
     */
    public function translateRenderVars($entityPathConfig, $renderVars)
    {
        $backendTranslator = $this->container->get('bk.wh.back.translator');
        $backendTranslator->setDomain($this->getTranslateDomain($entityPathConfig));

        $renderVars['title'] = $backendTranslator->trans($renderVars['title']);

        $breadcrumb = [];
        foreach ($renderVars['breadcrumb'] as $name => $url) {
            $breadcrumb[$backendTranslator->trans($name)] = $url;
        }
        $renderVars['breadcrumb'] = $breadcrumb;

        $renderVars['tablePanelProperties']['headerLabel'] = $backendTranslator->trans(
            $renderVars['tablePanelProperties']['headerLabel']
        );

        if (isset($renderVars['tablePanelProperties']['headerListButtons'])) {
            foreach ($renderVars['tablePanelProperties']['headerListButtons'] as $key => $headerListButton) {
                $headerListButton['label'] = $backendTranslator->trans($headerListButton['label']);
                $renderVars['tablePanelProperties']['headerListButtons'][$key] = $headerListButton;
            }
        }

        foreach ($renderVars['tablePanelProperties']['tableFields'] as $entityFieldName => $tableField) {
            if (isset($tableField['label'])) {
                $tableField['label'] = $backendTranslator->trans($tableField['label']);
            }

            if (is_array($tableField) && key_exists('multipleFields', $tableField)) {
                foreach ($tableField as $key => $multipleField) {
                    if (isset($multipleField['confirm'])) {
                        $multipleField['confirm'] = $backendTranslator->trans($multipleField['confirm']);
                    }
                    $tableField[$key] = $multipleField;
                }
            }

            $renderVars['tablePanelProperties']['tableFields'][$entityFieldName] = $tableField;
        }

        if ($this->search) {
            $renderVars['formPanelProperties']['headerLabel'] = $backendTranslator->trans(
                $renderVars['formPanelProperties']['headerLabel']
            );

            foreach ($renderVars['formPanelProperties']['form']->children as $formFieldSlug => $formField) {
                $formField->vars['label'] = $backendTranslator->trans($formField->vars['label']);
                $renderVars['formPanelProperties']['form']->children[$formFieldSlug] = $formField;
            }

            if (isset($renderVars['formPanelProperties']['footerListButtons'])) {
                foreach ($renderVars['formPanelProperties']['footerListButtons'] as $key => $button) {
                    if (isset($button['label'])) {
                        $button['label'] = $backendTranslator->trans($button['label']);
                    }
                    $renderVars['formPanelProperties']['footerListButtons'][$key] = $button;
                }
            }
        }

        return $renderVars;
    }
}
