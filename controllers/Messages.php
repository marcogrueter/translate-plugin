<?php namespace RainLab\Translate\Controllers;

use Lang;
use Flash;
use RainLab\Translate\Models\MessageExport;
use BackendMenu;
use Backend\Classes\Controller;
use RainLab\Translate\Models\Message;
use RainLab\Translate\Classes\Locale;
use RainLab\Translate\Classes\ThemeScanner;
use System\Helpers\Cache as CacheHelper;
use System\Classes\SettingsManager;

/**
 * Messages Backend Controller
 */
class Messages extends Controller
{
    /**
     * implement the TranslatableModel behavior softly
     */
    public $implement = [
        \Backend\Behaviors\ImportExportController::class,
    ];

    /**
     * @var mixed importExportConfig
     */
    public $importExportConfig = 'config_import_export.yaml';

    /**
     * @var mixed requiredPermissions
     */
    public $requiredPermissions = ['rainlab.translate.manage_messages'];

    /**
     * @var mixed hideTranslated
     */
    protected $hideTranslated = false;

    /**
     * __construct
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('RainLab.Translate', 'messages');

        $this->addJs('/plugins/rainlab/translate/assets/js/messages.js');
        $this->addCss('/plugins/rainlab/translate/assets/css/messages.css');

        $this->importColumns = MessageExport::getColumns();
        $this->exportColumns = MessageExport::getColumns();
    }

    /**
     * index
     */
    public function index()
    {
        $this->pageTitle = 'rainlab.translate::lang.messages.title';
        $this->prepareTable();
    }

    /**
     * onRefresh
     */
    public function onRefresh()
    {
        $this->prepareTable();
        return ['#messagesContainer' => $this->makePartial('messages')];
    }

    /**
     * onClearCache
     */
    public function onClearCache()
    {
        CacheHelper::clear();

        Flash::success(Lang::get('rainlab.translate::lang.messages.clear_cache_success'));
    }

    /**
     * onLoadScanMessagesForm
     */
    public function onLoadScanMessagesForm()
    {
        return $this->makePartial('scan_messages_form');
    }

    /**
     * onScanMessages
     */
    public function onScanMessages()
    {
        if (post('purge_messages', false)) {
            Message::truncate();
        }

        ThemeScanner::scan();

        if (post('purge_deleted_messages', false)) {
            Message::where('found', 0)->delete();
        }

        Flash::success(Lang::get('rainlab.translate::lang.messages.scan_messages_success'));

        return $this->onRefresh();
    }

    /**
     * prepareTable
     */
    public function prepareTable()
    {
        $fromCode = post('locale_from', null);
        $toCode = post('locale_to', Locale::getDefault()->code);
        $this->hideTranslated = post('hide_translated', false);

        /*
         * Page vars
         */
        $this->vars['hideTranslated'] = $this->hideTranslated;
        $this->vars['defaultLocale'] = Locale::getDefault();
        $this->vars['locales'] = Locale::listLocales()->all();
        $this->vars['selectedFrom'] = $selectedFrom = Locale::findByCode($fromCode);
        $this->vars['selectedTo'] = $selectedTo = Locale::findByCode($toCode);

        /*
         * Make table config, make default column read only
         */
        $config = $this->makeConfig('config_table.yaml');

        if (!$selectedFrom) {
            $config->columns['from']['readOnly'] = true;
        }
        if (!$selectedTo) {
            $config->columns['to']['readOnly'] = true;
        }

        /*
         * Make table widget
         */
        $widget = $this->makeWidget(\Backend\Widgets\Table::class, $config);
        $widget->bindToController();

        /*
         * Populate data
         */
        $dataSource = $widget->getDataSource();

        $dataSource->bindEvent('data.getRecords', function($offset, $count) use ($selectedFrom, $selectedTo) {
            $messages = $this->listMessagesForDatasource([
                'offset' => $offset,
                'count' => $count
            ]);

            return $this->processTableData($messages, $selectedFrom, $selectedTo);
        });

        $dataSource->bindEvent('data.searchRecords', function($search, $offset, $count) use ($selectedFrom, $selectedTo) {
            $messages = $this->listMessagesForDatasource([
                'search' => $search,
                'offset' => $offset,
                'count' => $count
            ]);

            return $this->processTableData($messages, $selectedFrom, $selectedTo);
        });

        $dataSource->bindEvent('data.getCount', function() {
            return Message::count();
        });

        $dataSource->bindEvent('data.updateRecord', function($key, $data) {
            $message = Message::find($key);
            $this->updateTableData($message, $data);
            CacheHelper::clear();
        });

        $dataSource->bindEvent('data.deleteRecord', function($key) {
            if ($message = Message::find($key)) {
                $message->delete();
            }
        });

        $this->vars['table'] = $widget;
    }

    /**
     * isHideTranslated
     */
    protected function isHideTranslated()
    {
        return post('hide_translated', false);
    }

    /**
     * listMessagesForDatasource
     */
    protected function listMessagesForDatasource($options = [])
    {
        extract(array_merge([
            'search' => null,
            'offset' => null,
            'count' => null,
        ], $options));

        $query = Message::orderBy('message_data','asc');

        if ($search) {
            $query = $query->searchWhere($search, ['message_data']);
        }

        if ($count) {
            $query = $query->limit($count)->offset($offset);
        }

        return $query->get();
    }

    /**
     * processTableData
     */
    protected function processTableData($messages, $from, $to)
    {
        $fromCode = $from ? $from->code : null;
        $toCode = $to ? $to->code : null;

        $data = [];
        foreach ($messages as $message) {
            $toContent = $message->forLocale($toCode);
            if ($this->hideTranslated && $toContent) {
                continue;
            }

            $data[] = [
                'id' => $message->id,
                'code' => $message->code,
                'from' => $message->forLocale($fromCode),
                'to' => $toContent,
                'found' => $message->found ? '' : Lang::get('rainlab.translate::lang.messages.not_found'),
            ];
        }

        return $data;
    }

    /**
     * updateTableData
     */
    protected function updateTableData($message, $data)
    {
        if (!$message) {
            return;
        }

        $fromCode = post('locale_from');
        $toCode = post('locale_to');

        // @todo This should be unified to a single save()
        if ($fromCode) {
            $fromValue = array_get($data, 'from');
            if ($fromValue != $message->forLocale($fromCode)) {
                $message->toLocale($fromCode, $fromValue);
            }
        }

        if ($toCode) {
            $toValue = array_get($data, 'to');
            if ($toValue != $message->forLocale($toCode)) {
                $message->toLocale($toCode, $toValue);
            }
        }
    }
}
