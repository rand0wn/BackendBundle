<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;

/**
 * Class ImportController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class ImportController extends BaseController implements BaseControllerInterface
{

    protected $container;

    public $renderVars;

    public $config;
    public $globalConfig;

    public $entityPathConfig;
    public $request;
    public $arguments;

    public $csvImporter;

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
    public function import($entityPathConfig, Request $request, $arguments = [])
    {
        $this->renderVars = [];

        $this->entityPathConfig = $entityPathConfig;
        $this->request = $request;

        $this->config = $this->getConfig($entityPathConfig, 'import');
        $this->globalConfig = $this->getGlobalConfig($entityPathConfig);
        $this->arguments = $arguments;

        $this->renderVars['globalConfig'] = $this->globalConfig;

        $this->csvImporter = $this->get('lib.csv_importer');

        $return = $this->handleImportForm();
        if ($return !== true) {
            return $return;
        }

        $return = $this->handleConfirmationForm();
        if ($return !== true) {
            return $return;
        }

        $this->renderVars['title'] = $this->config['title'];

        $this->renderVars['breadcrumb'] = $this->getBreadcrumb(
            $this->config['breadcrumb'],
            $entityPathConfig,
            $arguments
        );

        return $this->container->get('templating')->renderResponse(
            '@WHBackendTemplate/BackendTemplate/View/import.html.twig',
            $this->renderVars
        );
    }

    /**
     * @return bool|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleImportForm()
    {
        $importFormFields = [
            'csv' => [
                'type'  => 'file',
                'label' => 'CSV File',
            ],
        ];

        $importFooterListButtons = [
            'submit' => [
                'type'       => 'submit',
                'label'      => 'Importer',
                'buttonType' => 'button',
            ],
        ];

        $importForm = $this->getForm($importFormFields, 'importForm');

        $importForm->handleRequest($this->request);
        if ($importForm->isSubmitted()) {
            $data = $importForm->getData();

            if (!empty($data['csv'])) {
                $filePath = $this->csvImporter->moveFile(
                    $data['csv'],
                    $this->getSlug($this->entityPathConfig)
                );

                $this->get('session')->set($this->getSlug($this->entityPathConfig) . 'importFilePath', $filePath);

                return $this->redirect(
                    $this->getActionUrl(
                        $this->entityPathConfig,
                        'import'
                    )
                );
            }
        }

        $this->renderVars['importFormPanelProperties'] = [
            'form'              => $importForm->createView(),
            'formFields'        => $importFormFields,
            'footerListButtons' => $importFooterListButtons,
            'headerLabel'       => 'Import CSV',
        ];

        return true;
    }

    /**
     * @return bool
     */
    public function handleConfirmationForm()
    {
        $confirmationFormFields = [
            'submit' => [
                'type'  => 'submit',
                'label' => 'Confirm import',
            ],
        ];

        $confirmationFormFooterListButtons = [
            'submit' => [
                'label'      => 'Confirm import',
                'buttonType' => 'button',
            ],
        ];

        $confirmationForm = $this->getForm($confirmationFormFields, 'confirmationForm');

        $confirmationForm->handleRequest($this->request);

        $importResponse = $this->getImportResponse(
            $this->entityPathConfig,
            $this->getFilePath($this->entityPathConfig)
        );
        $this->renderVars['importResponse'] = $importResponse;

        $this->renderVars['confirmationFormPanelProperties'] = [
            'form'              => $confirmationForm->createView(),
            'formFields'        => $confirmationFormFields,
            'footerListButtons' => $confirmationFormFooterListButtons,
            'headerLabel'       => 'Confirm import',
        ];

        return true;
    }

    /**
     * @param $entityPathConfig
     *
     * @return mixed
     */
    public function getFilePath($entityPathConfig)
    {
        return $this->get('session')->get($this->getSlug($entityPathConfig) . 'importFilePath');
    }

    /**
     * @param $entityPathConfig
     * @param $filePath
     *
     * @return array
     */
    public function getImportResponse($entityPathConfig, $filePath)
    {
        $importResponse = [];

        if ($filePath) {
            $config = $this->getConfig($entityPathConfig, 'import');

            $fileExpectedColumns = $config['fileExpectedColumns'];

            $importResponse = $this->get('lib.csv_importer')->getCsvData(
                $filePath,
                $fileExpectedColumns,
                [
                    'uniqueColumns' => $config['uniqueColumns'],
                ]
            );
            $importResponse['columns'] = $fileExpectedColumns;
        }

        return $importResponse;
    }

}
