<?php namespace RainLab\Translate\Models;

use Str;
use Lang;
use Model;
use Cache;
use RainLab\Translate\Classes\Locale;

/**
 * Message Model
 */
class Message extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'rainlab_translate_message_data';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['data'];

    /**
     * updateMessage
     */
    public function updateMessage($locale, $key, $message)
    {
        if ($record = $this->newQuery()->where('locale', $locale)->first()) {
            $data = $record->data;

            if ($message === null) {
                unset($data[$key]);
            }
            else {
                $data[$key] = $message;
            }

            $record->data = $data;
            $record->save();
        }
        else {
            $record = new self;
            $record->locale = $locale;
            $record->data = [$key => $message];
            $record->save();
        }
    }

    /**
     * deleteMessage
     */
    public function deleteMessage($key)
    {
        $messages = $this->newQuery()->get();
        foreach ($messages as $record) {
            $data = $record->data;
            unset($data[$key]);
            $record->data = $data;
            $record->save();
        }
    }

    /**
     * findMessages
     */
    public function findMessages($locale, $options = [])
    {
        extract(array_merge([
            'search' => null,
            'offset' => null,
            'count' => null,
            'withEmpty' => false
        ], $options));

        $defaultLocale = Locale::getDefault()->code;

        // Find messages
        $collection = $this->newQuery()->whereIn('locale', $withEmpty
            ? [$locale, $defaultLocale]
            : [$locale]
        )->get();

        $messages = [];
        foreach ($collection as $message) {
            $messages[$message->locale] = $message->data;
        }

        // Process
        if ($withEmpty) {
            $result = [];
            $emptyMessages = $messages[$defaultLocale] ?? [];
            $sourceMessages = $messages[$locale] ?? [];
            foreach ($emptyMessages as $key => $message) {
                $result[$key] = $sourceMessages[$key] ?? null;
            }
        }
        else {
            $result = $messages[$locale] ?? [];
        }

        // Count
        if ($count) {
            $result = $this->applyCountToResult($result, $count, $offset);
        }

        // Search
        if ($search) {
            $result = $this->applySearchToResult($result, $search);
        }

        return $result;
    }

    /**
     * applyCountToResult
     */
    public function applyCountToResult($result, $count, $offset)
    {
        return $result;
    }

    /**
     * applySearchToResult
     */
    public function applySearchToResult($result, $search)
    {
        return $result;
    }
}
