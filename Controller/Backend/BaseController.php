<?php

namespace WH\BackendBundle\Controller\Backend;

use Doctrine\ORM\EntityRepository;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Yaml\Yaml;
use WH\BackendBundle\Form\EntryType;
use WH\LibBundle\Utils\Inflector;
use WH\MediaBundle\Form\Backend\FileType;

/**
 * Class BaseController
 *
 * @package WH\BackendBundle\Controller\Backend
 */
class BaseController extends Controller implements BaseControllerInterface
{

    public $bundlePrefix = '';
    public $bundle = '';
    public $entity = '';
    public $type = 'Backend';

    /**
     * @return array
     */
    public function getEntityPathConfig()
    {
        return [
            'bundlePrefix' => $this->bundlePrefix,
            'bundle'       => $this->bundle,
            'entity'       => $this->entity,
            'type'         => $this->type,
        ];
    }

    /**
     * @param $entityPathConfig
     * @param $action
     *
     * @return mixed
     */
    public function getConfig($entityPathConfig, $action)
    {
        $ymlPath = $this->getYmlFilePath(
            $entityPathConfig,
            $action
        );

        if (!file_exists($ymlPath)) {
            throw new NotFoundHttpException(
                'Le fichier de configuration n\'existe pas. Il devrait être ici : ' . $ymlPath
            );
        }

        $resourcesYmlPath = $this->getYmlResourcesFilePath(
            $entityPathConfig,
            $action
        );

        if (file_exists($resourcesYmlPath)) {
            $ymlPath = $resourcesYmlPath;
        }

        $config = Yaml::parse(file_get_contents($ymlPath));
        if ($this->validConfig($config)) {
            return $config;
        }

        return [];
    }

    /**
     * @param $entityPathConfig
     *
     * @return mixed
     */
    public function getGlobalConfig($entityPathConfig)
    {
        $ymlPath = $this->getYmlFilePath(
            $entityPathConfig,
            'global'
        );

        if (!file_exists($ymlPath)) {
            throw new NotFoundHttpException(
                'Le fichier de configuration globale n\'existe pas. Il devrait être ici : ' . $ymlPath
            );
        }

        $resourcesYmlPath = $this->getYmlResourcesFilePath(
            $entityPathConfig,
            'global'
        );

        if (file_exists($resourcesYmlPath)) {
            $ymlPath = $resourcesYmlPath;
        }

        $config = Yaml::parse(file_get_contents($ymlPath));

        if (!isset($config['actions'])) {
            throw new NotFoundHttpException('Le fichier de configuration globale ne contient pas le champ "actions"');
        }

        return $config;
    }

    /**
     * @param $entityPathConfig
     *
     * @return string
     */
    public function getSlug($entityPathConfig)
    {
        $slug = '';
        if (!empty($entityPathConfig['bundlePrefix'])) {

            $slug .= $entityPathConfig['bundlePrefix'];
        }
        $slug .= $entityPathConfig['bundle'] . $entityPathConfig['entity'];

        $slug = Inflector::camelize($slug);

        return $slug;
    }

    /**
     * @param $entityPathConfig
     *
     * @return string
     */
    public function getEntityPath($entityPathConfig)
    {
        $entityPath = '';
        if (!empty($entityPathConfig['bundlePrefix'])) {

            $entityPath .= $entityPathConfig['bundlePrefix'];
        }
        $entityPath .= '\\' . $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];

        return $entityPath;
    }

    /**
     * @param $entityPathConfig
     *
     * @return string
     */
    public function getRepositoryName($entityPathConfig)
    {
        $repositoryName = '';
        if (!empty($entityPathConfig['bundlePrefix'])) {

            $repositoryName .= $entityPathConfig['bundlePrefix'];
        }
        $repositoryName .= $entityPathConfig['bundle'] . ':' . $entityPathConfig['entity'];

        return $repositoryName;
    }

    /**
     * @param $entityPathConfig
     * @param $slug
     *
     * @return string
     */
    private function getYmlResourcesFilePath($entityPathConfig, $slug)
    {
        $rootDir = $this->get('kernel')->getRootDir();
        $path = $rootDir . '/Resources/';
        $bundleName = '';
        if ($entityPathConfig['bundlePrefix'] != '') {
            $bundleName .= $entityPathConfig['bundlePrefix'];
        }
        $bundleName .= $entityPathConfig['bundle'];
        $path .= $bundleName . '/config/' . $entityPathConfig['type'] . '/' . $entityPathConfig['entity'] . '/' . $slug . '.yml';

        return $path;
    }

    /**
     * @param $entityPathConfig
     * @param $slug
     *
     * @return string
     */
    private function getYmlFilePath($entityPathConfig, $slug)
    {
        $path = '@';
        if ($entityPathConfig['bundlePrefix'] != '') {
            $path .= $entityPathConfig['bundlePrefix'];
        }
        $path .= $entityPathConfig['bundle'] . '/Resources/config/' . $entityPathConfig['type'] . '/' . $entityPathConfig['entity'] . '/' . $slug . '.yml';

        $path = $this->get('kernel')->locateResource($path);

        return $path;
    }

    /**
     * @param $entityPathConfig
     *
     * @return bool
     */
    public function getTranslateDomain($entityPathConfig)
    {
        $translateDomain = $entityPathConfig['bundlePrefix'] . $entityPathConfig['bundle'] . '_' . $entityPathConfig['type'] . '_' . $entityPathConfig['entity'];

        return $translateDomain;
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfig($config)
    {
        return true;
    }

    /**
     * @param      $configBreadcrumbs
     * @param      $entityPathConfig
     * @param null $data
     *
     * @return array
     */
    public function getBreadcrumb($configBreadcrumbs, $entityPathConfig, $data = null)
    {
        $breadcrumb = [];

        foreach ($configBreadcrumbs as $configBreadcrumb) {
            $url = $this->getActionUrl($entityPathConfig, $configBreadcrumb['action'], $data);

            $breadcrumb[$configBreadcrumb['label']] = $url;
        }

        return $breadcrumb;
    }

    /**
     * @param      $entityPathConfig
     * @param      $action
     * @param null $data
     * @param bool $absolutePath
     *
     * @return string
     */
    public function getActionUrl($entityPathConfig, $action, $data = null, $absolutePath = false)
    {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        if (!isset($globalConfig['actions'][$action])) {
            throw new NotFoundHttpException(
                'L\'action "' . $action . '" n\'est pas déclarée dans le fichier de configuration globale'
            );
        }

        $action = $globalConfig['actions'][$action];

        $route = $action['route'];

        if (!isset($action['parameters'])) {
            return $this->generateUrl($route, [], $absolutePath);
        }

        if (!$data && isset($globalConfig['defaultData'])) {
            $data = $globalConfig['defaultData'];
        }

        if (isset($action['parameters']) && !$data) {
            throw new NotFoundHttpException(
                'L\'action "' . $action['route'] . '" requiert des paramètres et aucune donnée n\'a été reçue'
            );
        }

        $parameters = [];

        foreach ($action['parameters'] as $routerParameterName => $parameter) {
            if (is_object($data)) {
                $parameter = explode('.', $parameter);

                $fieldValue = null;

                foreach ($parameter as $field) {
                    if (!$fieldValue) {
                        $fieldValue = $data->{'get' . Inflector::camelizeWithFirstLetterUpper($field)}();
                    } else {
                        $fieldValue = $fieldValue->{'get' . Inflector::camelizeWithFirstLetterUpper($field)}();
                    }

                    $parameters[$routerParameterName] = $fieldValue;
                }
            } elseif (is_array($data)) {
                $parameters[$routerParameterName] = $data[$parameter];
            }
        }

        return $this->generateUrl($route, $parameters, $absolutePath);
    }

    /**
     * @param        $formFields
     * @param        $entityPathConfig
     * @param        $data
     * @param string $formName
     * @param array  $formOptions
     *
     * @return mixed|\Symfony\Component\Form\FormInterface
     */
    public function getEntityForm($formFields, $entityPathConfig, $data, $formName = 'form', $formOptions = [])
    {
        $dataClass = $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];
        if ($entityPathConfig['bundlePrefix'] != '') {
            $dataClass = $entityPathConfig['bundlePrefix'] . '\\' . $dataClass;
        }

        $form = $this->container->get('form.factory')->createNamed(
            $formName,
            'Symfony\Component\Form\Extension\Core\Type\FormType',
            $data,
            array_merge(
                [
                    'data_class' => $dataClass,
                ],
                $formOptions
            )
        );

        $form = $this->addFormFieldsToForm($form, $formFields, $entityPathConfig);

        return $form;
    }

    /**
     * @param        $formFields
     * @param string $formName
     *
     * @return mixed
     */
    public function getForm($formFields, $formName = 'searchForm')
    {
        $form = $this->container->get('form.factory')->createNamedBuilder($formName);

        $form = $this->addFormFieldsToForm($form, $formFields);

        return $form->getForm();
    }

    /**
     * @param $configFormFields
     * @param $entityPathConfig
     *
     * @return array
     */
    public function getFormFields($configFormFields, $entityPathConfig)
    {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $formFields = [];

        if (is_array($configFormFields)) {
            foreach ($configFormFields as $key => $configFormField) {
                $formFieldSlug = null;
                if (!is_array($configFormField) && preg_match('#.*\..*#', $configFormField)) {
                    $formFieldSlug = $configFormField;
                    $configFormField = explode('.', $configFormField);
                    $configFormField = array_combine($configFormField, $configFormField);
                }
                if (is_array($configFormField)) {
                    if (!$formFieldSlug) {
                        $formFieldSlug = $key;
                    }

                    if (isset($globalConfig['formFields'][$key])) {
                        $formField = array_merge($globalConfig['formFields'][$key], $configFormField);
                    } else {
                        $formField = $configFormField;
                    }
                } else {
                    if (is_integer($key)) {
                        $formFieldSlug = $configFormField;
                    } else {
                        $formFieldSlug = $key;
                    }

                    $formField = $globalConfig['formFields'][$formFieldSlug];
                }

                $formFields[$formFieldSlug] = $formField;
            }
        }

        return $formFields;
    }

    /**
     * @param       $form
     * @param       $formFields
     * @param array $entityPathConfig
     *
     * @return mixed
     */
    public function addFormFieldsToForm($form, $formFields, $entityPathConfig = [])
    {
        foreach ($formFields as $formField => $properties) {
            $options = [
                'label'    => (!empty($properties['label'])) ? $properties['label'] : false,
                'required' => false,
            ];

            $optionsToGive = [
                'attr',
                'choice_label',
                'class',
            ];
            foreach ($optionsToGive as $optionToGive) {
                if (!empty($properties[$optionToGive])) {
                    $options[$optionToGive] = $properties[$optionToGive];
                }
            }

            switch ($properties['type']) {

                case 'checkbox':
                    $properties['type'] = CheckboxType::class;
                    break;

                case 'choice':
                    $properties['type'] = ChoiceType::class;

                    if (isset($properties['options']['type'])) {
                        switch ($properties['options']['type']) {
                            case 'static':
                                $field = 'get' . ucfirst($properties['options']['field']);
                                $entityPath = '';
                                if ($entityPathConfig['bundlePrefix']) {
                                    $entityPath .= '\\' . $entityPathConfig['bundlePrefix'];
                                }
                                $entityPath .= '\\' . $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];
                                $options['choices'] = array_flip($entityPath::$field());
                                foreach ($options['choices'] as $key => $value) {
                                    $options['choices'][$key] = $value;
                                }
                                $options['placeholder'] = false;
                                break;

                            case 'parameter':

                                switch ($properties['options']['parameter']) {

                                    case 'security.role_hierarchy.roles':
                                        $arrayRoles = $this->getUser()->getRoles();

                                        $roles = [];
                                        foreach ($arrayRoles as $arrayRole) {
                                            $roles[] = new Role($arrayRole);
                                        }

                                        $roles = $this->get('security.role_hierarchy')->getReachableRoles($roles);

                                        $choices = [];
                                        foreach ($roles as $role) {
                                            $choices[$role->getRole()] = $role->getRole();
                                        }

                                        unset($choices['ROLE_USER']);

                                        $options['choices'] = $choices;
                                        break;

                                    default:
                                        $options['choices'] = $this->container->getParameter(
                                            $properties['options']['parameter']
                                        );
                                        break;
                                }

                                break;
                        }
                    }

                    if (isset($properties['empty_data'])) {
                        $options['empty_value'] = $properties['empty_data'];
                    }

                    if (isset($properties['multiple'])) {
                        $options['multiple'] = $properties['multiple'];
                    }

                    break;

                case 'email':
                    $properties['type'] = EmailType::class;
                    break;

                case 'entity':
                    $properties['type'] = EntityType::class;
                    $options['class'] = $properties['class'];

                    if (isset($properties['choice_label'])) {
                        $options['choice_label'] = $properties['choice_label'];

                        $em = $this->container->get('doctrine')->getManager();

                        $entityRepository = $em->getRepository($properties['class']);
                        $query = $entityRepository->get('query');

                        $options['query_builder'] = $query;
                    }

                    if (isset($properties['multiple'])) {
                        $options['multiple'] = $properties['multiple'];
                    }

                    if (isset($properties['group_by'])) {
                        $options['group_by'] = $properties['group_by'];
                    }

                    if (isset($properties['choices'])) {
                        $options['choices'] = $properties['choices'];
                    }

                    if (isset($properties['custom_query_builder'])) {
                        $em = $this->container->get('doctrine')->getManager();
                        $entityRepository = $em->getRepository($properties['class']);

                        $conditions = [];

                        if (isset($properties['custom_query_builder']['conditions'])) {
                            foreach ($properties['custom_query_builder']['conditions'] as $condition => $conditionValue) {
                                if (is_int($condition)) {
                                    $condition = $conditionValue;
                                    $conditions[$condition] = $this->getVariableValue($condition, $form->getData());
                                } else {
                                    $conditions[$condition] = $conditionValue;
                                }
                            }
                        }

                        $query = $entityRepository->get(
                            'query',
                            [
                                'conditions' => $conditions,
                            ]
                        );

                        $options['query_builder'] = $query;
                    }
                    break;

                case 'hidden':
                    $properties['type'] = HiddenType::class;
                    break;

                case 'integer':
                    $properties['type'] = IntegerType::class;
                    break;

                case 'text':
                    $properties['type'] = TextType::class;

                    if (isset($properties['disabled'])) {
                        $options['disabled'] = $properties['disabled'];
                    }
                    break;

                case 'number':
                    $properties['type'] = NumberType::class;
                    $options['scale'] = (int)$properties['scale'];
                    break;

                case 'textarea':
                    $properties['type'] = TextareaType::class;
                    break;

                case 'tinymce':
                    $properties['type'] = TextareaType::class;

                    $class = (!empty($options['attr']['class'])) ? $options['attr']['class'] : '';
                    $class = 'tinymce ' . $class;
                    $options['attr']['class'] = $class;

                    break;

                case 'file':
                    $properties['type'] = \Symfony\Component\Form\Extension\Core\Type\FileType::class;
                    break;

                case 'wh_file':
                    $properties['type'] = FileType::class;
                    break;

                case 'elfinder':
                    $properties['type'] = ElFinderType::class;
                    $properties['attr']['instance'] = 'default';
                    $properties['attr']['enable'] = true;
                    break;

                case 'form':
                    $properties['type'] = $properties['form'];
                    break;

                case 'collection':
                    $properties['type'] = CollectionType::class;
                    $options['entry_type'] = $properties['form'];
                    $options['allow_add'] = true;
                    $options['allow_delete'] = true;
                    $options['delete_empty'] = true;
                    $options['delete_empty'] = true;
                    $options['by_reference'] = false;
                    $options['attr']['data-form-template'] = $properties['formTemplate'];

                    if (isset($properties['formTemplateHead'])) {
                        $options['attr']['data-form-template-head'] = $properties['formTemplateHead'];
                    }

                    if (isset($properties['sortable'])) {
                        $options['attr']['data-sortable-url'] = $this->getActionUrl(
                            $properties['sortable']['entityPathConfig'],
                            'order'
                        );
                    }

                    if (isset($properties['disableAdd'])) {
                        $options['attr']['disableAdd'] = $properties['disableAdd'];
                    }
                    break;

                case 'sub-form':
                    $properties['type'] = FormType::class;
                    break;

                case 'submit':
                    unset($options['required']);
                    $properties['type'] = SubmitType::class;
                    break;
            }

            if ($properties['type'] == FormType::class) {
                $subForm = $this->getEntityForm(
                    $properties['fields'],
                    $properties['entityPathConfig'],
                    $this->getVariableValue($formField, $form->getData()),
                    $formField,
                    [
                        'auto_initialize' => false,
                    ]
                );

                $form->add($subForm);
            } else {
                $form->add(
                    $formField,
                    $properties['type'],
                    $options
                );
            }
        }

        return $form;
    }

    /**
     * @param $arguments
     *
     * @return array
     */
    public function getDataFromArguments($arguments)
    {
        $data = [];
        if (empty($arguments)) {
            return $data;
        }

        foreach ($arguments as $condition => $value) {
            $data[Inflector::transformConditionInConditionParameter($condition)] = $value;
        }

        return $data;
    }

    /**
     * @param $fields
     * @param $entityPathConfig
     *
     * @return mixed
     */
    public function transformActionIntoRoute($fields, $entityPathConfig)
    {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        foreach ($fields as $key => $field) {
            if (isset($field['action'])) {
                $action = $globalConfig['actions'][$field['action']];
                $field = array_merge($field, $action);

                unset($field['action']);

                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param $variable
     * @param $data
     *
     * @return string
     */
    public function getVariableValue($variable, $data)
    {
        $value = '';
        $variableFields = explode('.', $variable);

        foreach ($variableFields as $variableField) {
            if (!$value) {
                if (is_object($data)) {
                    $value = $data->{'get' . ucfirst($variableField)}();
                } else {
                    $value = $data[$variableField];
                }
            } else {
                if (is_object($value)) {
                    $value = $value->{'get' . ucfirst($variableField)}();
                } else {
                    $value = $value[$variableField];
                }
            }
        }

        return $value;
    }

}
