<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;

require_once __DIR__ . '/src/Service/ZenaiApiClient.php';
require_once __DIR__ . '/src/Service/ZenaiSearchRunner.php';
require_once __DIR__ . '/src/Service/ZenaiProductExportService.php';
require_once __DIR__ . '/src/Provider/ZenaiProductSearchProvider.php';

class Zenaisearch extends Module
{
    public const CONFIG_API_TOKEN = 'ZENAISEARCH_API_TOKEN';

    private const MODE_AI = 'ai';

    public function __construct()
    {
        $this->name = 'zenaisearch';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Zenai';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Zenai Search', [], 'Modules.Zenaisearch.Admin');
        $this->description = $this->trans('Adds AI mode to storefront search and uses Zenai recommendations for search results.', [], 'Modules.Zenaisearch.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('productSearchProvider')
            && Configuration::updateValue(self::CONFIG_API_TOKEN, '');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONFIG_API_TOKEN)
            && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitZenaiSearchSettings')) {
            Configuration::updateValue(self::CONFIG_API_TOKEN, trim((string) Tools::getValue(self::CONFIG_API_TOKEN)));
            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Admin.Notifications.Success'));
        }

        if (Tools::isSubmit('submitZenaiSearchExport')) {
            $this->getProductExportService()->exportCatalogCsv();
        }

        $output .= $this->renderSettingsForm();
        $output .= $this->renderExportForm();

        return $output;
    }

    public function hookDisplayHeader()
    {
        if (!isset($this->context->controller)) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'module-zenaisearch-style',
            'modules/' . $this->name . '/views/css/zenaisearch.css',
            ['media' => 'all', 'priority' => 200]
        );

        $this->context->controller->registerJavascript(
            'module-zenaisearch-script',
            'modules/' . $this->name . '/views/js/zenaisearch.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        Media::addJsDef([
            'zenaiSearchConfig' => [
                'modeStorageKey' => 'zenaiSearch.mode',
                'defaultMode' => self::MODE_AI,
                'aiLabel' => $this->trans('AI Mode', [], 'Modules.Zenaisearch.Shop'),
                'searchLabel' => $this->trans('Search', [], 'Modules.Zenaisearch.Shop'),
            ],
        ]);
    }

    public function hookProductSearchProvider(array $params)
    {
        if (
            !$this->isZenaiSearchRequest()
            || !isset($params['query'])
            || !$params['query'] instanceof ProductSearchQuery
            || $params['query']->getQueryType() !== 'search'
        ) {
            return null;
        }

        return new ZenaiProductSearchProvider($this->getSearchRunner());
    }

    public function getApiToken()
    {
        return (string) Configuration::get(self::CONFIG_API_TOKEN);
    }

    private function isZenaiSearchRequest()
    {
        return (string) Tools::getValue('zenai') === '1';
    }

    private function getSearchRunner()
    {
        return new ZenaiSearchRunner($this->context, new ZenaiApiClient(), $this->getApiToken());
    }

    private function getProductExportService()
    {
        return new ZenaiProductExportService($this->context);
    }

    private function renderSettingsForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Zenai Settings', [], 'Modules.Zenaisearch.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'password',
                        'label' => $this->trans('API token', [], 'Modules.Zenaisearch.Admin'),
                        'name' => self::CONFIG_API_TOKEN,
                        'required' => true,
                        'hint' => $this->trans('The API token is generated from your Zenai dashboard.', [], 'Modules.Zenaisearch.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'name' => 'submitZenaiSearchSettings',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->submit_action = 'submitZenaiSearchSettings';
        $helper->fields_value = [
            self::CONFIG_API_TOKEN => $this->getApiToken(),
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    private function renderExportForm()
    {
        $html = '<div class="panel">';
        $html .= '<h3><i class="icon-download"></i> ' . $this->trans('Zenai Product Export', [], 'Modules.Zenaisearch.Admin') . '</h3>';
        $html .= '<p>' . $this->trans('Export products as CSV and import them in Zenai CSV Upload.', [], 'Modules.Zenaisearch.Admin') . '</p>';
        $html .= '<form method="post">';
        $html .= '<button type="submit" class="btn btn-default" name="submitZenaiSearchExport">';
        $html .= '<i class="process-icon-download"></i> ' . $this->trans('Export CSV', [], 'Modules.Zenaisearch.Admin');
        $html .= '</button>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }
}
