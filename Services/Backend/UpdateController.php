<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class UpdateController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class UpdateController extends BaseController implements BaseControllerInterface
{

    protected $container;

    public $modal = false;

    public $renderVars;

    public $config;
    public $globalConfig;

    public $entityPathConfig;
    public $request;
    public $data;

    public $form;
    public $formFields;
    public $footerFormFields;

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
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function update($entityPathConfig, $id, Request $request)
    {
        $this->entityPathConfig = $entityPathConfig;
        $this->request = $request;

        $em = $this->container->get('doctrine')->getManager();

        $data = $em->getRepository($this->getRepositoryName($entityPathConfig))->get(
            'one',
            [
                'conditions' => [
                    Inflector::camelize($entityPathConfig['entity']) . '.id' => $id,
                ],
            ]
        );

        if (!$data) {
            return $this->redirect($this->getActionUrl($entityPathConfig, 'index'));
        }

        $this->data = $data;

        $this->renderVars['data'] = $this->data;

        $this->config = $this->getConfig($entityPathConfig, 'update');
        $this->globalConfig = $this->getGlobalConfig($entityPathConfig);

        $this->renderVars['globalConfig'] = $this->globalConfig;

        $this->createUpdateForm();
        if ($this->request->getMethod() == 'POST') {
            return $this->handleUpdateFormSubmission();
        }
        $this->renderUpdateForm();

        $this->getFormProperties();

        $this->renderVars['title'] = $this->config['title'];

        if (!$this->modal) {
            $this->renderVars['breadcrumb'] = $this->getBreadcrumb(
                $this->config['breadcrumb'],
                $entityPathConfig,
                $data
            );
        }

        $view = '@WHBackendTemplate/BackendTemplate/View/update.html.twig';
        if ($this->modal) {
            $view = '@WHBackendTemplate/BackendTemplate/View/modal.html.twig';
        }
        if (isset($this->config['view'])) {
            $view = $this->config['view'];
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

        if (isset($this->config['modal']) && $this->config['modal'] == 'true') {
            $this->validConfigPopup($this->config);
            $this->modal = true;
        } else {
            $this->validConfigClassic($this->config);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validConfigPopup()
    {
        if (!isset($this->config['formFields'])) {
            throw new NotFoundHttpException('Le fichier de configuration ne contient pas le champ "formFields"');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validConfigClassic()
    {
        return true;
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

        foreach ($renderVars['form']->children as $formSlug => $formField) {
            if (isset($formField->vars['label'])) {
                $formField->vars['label'] = $backendTranslator->trans($formField->vars['label']);
            }

            if (isset($formField->vars['choices'])) {
                foreach ($formField->vars['choices'] as $key => $choice) {
                    $formField->vars['choices'][$key]->label = $backendTranslator->trans($choice->label);
                }
            }

            if ($formField->children) {
                foreach ($formField->children as $childFormSlug => $childFormField) {
                    if (isset($childFormField->vars['label'])) {
                        $childFormField->vars['label'] = $backendTranslator->trans($childFormField->vars['label']);
                    }

                    if (isset($childFormField->vars['choices'])) {
                        foreach ($childFormField->vars['choices'] as $key => $choice) {
                            $childFormField->vars['choices'][$key]->label = $backendTranslator->trans($choice->label);
                        }
                    }
                }
            }

            $renderVars['form']->children[$formSlug] = $formField;
        }

        if (!$this->modal) {
            $breadcrumb = [];
            foreach ($renderVars['breadcrumb'] as $name => $url) {
                $breadcrumb[$backendTranslator->trans($name)] = $url;
            }
            $renderVars['breadcrumb'] = $breadcrumb;

            if (!empty($renderVars['central']['viewLink']['name'])) {
                $renderVars['central']['viewLink']['name'] = $backendTranslator->trans(
                    $renderVars['central']['viewLink']['name']
                );
            }

            foreach ($renderVars['central']['tabs'] as $tabSlug => $tabProperties) {
                $tabProperties['name'] = $backendTranslator->trans($tabProperties['name']);

                if (!empty($tabProperties['formZones'])) {
                    foreach ($tabProperties['formZones'] as $formZoneSlug => $formZone) {
                        if (isset($formZone['title'])) {
                            $tabProperties['formZones'][$formZoneSlug]['title'] = $backendTranslator->trans(
                                $formZone['title']
                            );
                        }
                    }
                }

                if (!empty($tabProperties['formZones'])) {
                    foreach ($tabProperties['formZones'] as $formZoneSlug => $formZone) {
                        if (isset($formZone['listButtons'])) {
                            foreach ($formZone['listButtons'] as $button => $listButton) {
                                $listButton['label'] = $backendTranslator->trans(
                                    $listButton['label']
                                );

                                $formZone['listButtons'][$button] = $listButton;
                            }
                        }

                        $tabProperties['formZones'][$formZoneSlug] = $formZone;
                    }
                }

                $renderVars['central']['tabs'][$tabSlug] = $tabProperties;
            }

            foreach ($renderVars['column']['panelZones'] as $key => $panelZone) {
                $panelZone['headerLabel'] = $backendTranslator->trans($panelZone['headerLabel']);

                if (isset($panelZone['footerListFormButtons'])) {
                    foreach ($panelZone['footerListFormButtons'] as $field => $footerListFormButton) {
                        $panelZone['footerListFormButtons'][$field]['label'] = $backendTranslator->trans(
                            $footerListFormButton['label']
                        );
                    }
                }

                $renderVars['column']['panelZones'][$key] = $panelZone;
            }
        }

        return $renderVars;
    }

    /**
     * @return bool|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createUpdateForm()
    {
        $this->formFields = $this->getFormFields($this->config['formFields'], $this->entityPathConfig);

        $this->form = $this->getEntityForm($this->formFields, $this->entityPathConfig, $this->data);

        if ($this->modal) {
            $this->footerFormFields = $this->config['footerFormFields'];

            foreach ($this->footerFormFields as $footerFormField) {
                $this->form->add(
                    $footerFormField['field'],
                    SubmitType::class,
                    [
                        'label' => $footerFormField['label'],
                    ]
                );
            }
        }

        return true;
    }

    /**
     * @return bool|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleUpdateFormSubmission()
    {
        $this->form->handleRequest($this->request);

        if ($this->form->isSubmitted()) {
            $data = $this->form->getData();

            $em = $this->get('doctrine')->getManager();

            $em->persist($data);
            $em->flush();

            $redirectUrl = $this->getActionUrl($this->entityPathConfig, 'index', $data);
            if ($this->form->has('saveAndStay') && $this->form->get('saveAndStay')->isClicked()) {
                $redirectUrl = $this->getActionUrl($this->entityPathConfig, 'update', $data);
            }

            if ($this->request->isXmlHttpRequest()) {
                return new JsonResponse(
                    [
                        'success'  => true,
                        'redirect' => $redirectUrl,
                    ]
                );
            }

            return $this->redirect($redirectUrl);
        } else {
            $this->form->setData($this->data);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function renderUpdateForm()
    {
        $this->form = $this->form->createView();
        $this->renderVars['form'] = $this->form;
        $this->renderVars['formFields'] = $this->formFields;

        return true;
    }

    /**
     * @return bool
     */
    public function getFormProperties()
    {
        if (!$this->modal) {
            if (!empty($this->config['central']['viewLink']['action'])) {
                $this->config['central']['viewLink']['url'] = $this->getActionUrl(
                    $this->entityPathConfig,
                    $this->config['central']['viewLink']['action'],
                    $this->data
                );
            }

            foreach ($this->config['central']['tabs'] as $tabSlug => $tabProperties) {
                if (isset($tabProperties['iframeContent'])) {
                    $tabProperties['iframeContent']['url'] = $this->getActionUrl(
                        $this->entityPathConfig,
                        $tabProperties['iframeContent']['action'],
                        $this->data
                    );
                }

                if (!empty($tabProperties['formZones'])) {
                    foreach ($tabProperties['formZones'] as $formZoneSlug => $formZone) {
                        if (isset($formZone['listButtons'])) {
                            foreach ($formZone['listButtons'] as $button => $listButton) {
                                $listButton['buttonType'] = 'link';
                                $listButton['href'] = $this->getActionUrl(
                                    $this->entityPathConfig,
                                    $listButton['action'],
                                    $this->data
                                );

                                $formZone['listButtons'][$button] = $listButton;
                            }
                        }

                        $tabProperties['formZones'][$formZoneSlug] = $formZone;
                    }
                }

                $this->config['central']['tabs'][$tabSlug] = $tabProperties;
            }

            $this->renderVars['central'] = $this->config['central'];

            foreach ($this->config['column']['panelZones'] as $key => $panelZone) {
                $panelZone['form'] = $this->form;
                $panelZone['formFields'] = $this->getFormFields($panelZone['fields'], $this->entityPathConfig);

                unset($panelZone['fields']);

                if (isset($panelZone['footerListFormButtons'])) {
                    foreach ($panelZone['footerListFormButtons'] as $field => $footerListFormButton) {
                        $footerListFormButton = array_merge($footerListFormButton, $this->config['formFields'][$field]);
                        $footerListFormButton['form'] = $this->form;

                        $panelZone['footerListFormButtons'][$field] = $footerListFormButton;
                    }
                }
                $this->config['column']['panelZones'][$key] = $panelZone;
            }

            $this->renderVars['column'] = $this->config['column'];
        } else {
            $this->renderVars['footerFormFields'] = $this->footerFormFields;
            $this->renderVars['formAction'] = $this->getActionUrl($this->entityPathConfig, 'update', $this->data);
        }

        return true;
    }

}
