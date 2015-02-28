<?php
namespace App\Services;

use Silex\Application;
use Adamlc\Wufoo\ValueObject\WufooSubmitField;
use Exception;

/**
 * Parse a Zapier email submission from Short Term Mission and Create an entry in Wufoo.
 *
 * Class ShortTermMissionsToWufoo
 * @package App\Services
 */
class ShortTermMissionsToWufoo {

    private $data;
    private $delimiter;
    private $delimiterData = [
        'message' => 'M E S S A G E',
        'contactInfo' => 'C O N T A C T   I N F O',
        'details' => 'D E T A I L S'
    ];
    private $app;
    private $wufooPayload;
    private $wufooPayloadMessage;
    const FORWARDING_EMAIL = 'gshotton@gbsf.org';
    const ERROR_EMAIL = 'craigwann1@gmail.com';
    const WUFOO_FORM_SLUG = 'r1tl6zgg0gzfyj1';

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Process the zapier post request.
     *
     * @param $data
     * @throws Exception
     */
    public function processPayload($data)
    {
        $this->data = $data;
        $this->forward();
        $this->parsePayload($this->data);
        if (!count($this->wufooPayload)) {
            throw new Exception('No email data extracted.');
        }
        $result = WufooFactory::build()->entryPost(self::WUFOO_FORM_SLUG, $this->wufooPayload);
        if (!$result->Success) {
            throw new Exception(json_encode($result->fieldErrors));
        }
    }

    /**
     * Called on error to notify via email.
     *
     * @param $text
     */
    public function notify($text)
    {
        $mandrill = MandrillFactory::build();
        $message = array(
            'text' => $text,
            'subject' => 'GBSF Short Terms Missions Submission Error',
            'from_email' => 'donotreply@gbsf.org',
            'from_name' => 'GBSF ERROR',
            'to' => array(
                array(
                    'email' => self::ERROR_EMAIL,
                    'type' => 'to'
                )
            )
        );
        $mandrill->messages->send($message);
    }

    /**
     * Forward the original email on to Gary through Mandrill.
     */
    private function forward()
    {
        if (!$this->data->get('body_plain')) {
            return;
        }
        $mandrill = MandrillFactory::build();
        $message['text'] = $this->data->get('body_plain');
        $message['subject'] = $this->data->get('raw__Subject');
        $message['from_name'] = $this->data->get('from_name');
        $message['from_email'] = $this->data->get('sender');
        $message['to'] = array(
            array(
                'email' => self::FORWARDING_EMAIL,
                'type' => 'to'
            )
        );
        $mandrill->messages->send($message);
    }

    /**
     * Split the payload into chunks and decide what to do with them.
     *
     * @param $data
     */
    private function parsePayload($data)
    {
        $parts = explode(PHP_EOL, $data->get('body_plain'));
        foreach($parts as $part) {
            if(!$this->checkAndStoreDelimiter($part)) {
                $this->parsePart($part);
            }
        }
        $this->wufooPayload[] = new WufooSubmitField('Field9', $this->wufooPayloadMessage);
        $this->wufooPayload[] = new WufooSubmitField('Field11', 'Short Term Missions');
    }

    /**
     * Check to see if the current part is a delimiter. If it is, store it so we know which section we're in.
     *
     * @param $part
     * @return bool
     */
    private function checkAndStoreDelimiter($part)
    {
        foreach($this->delimiterData as $label => $string) {
            if (strpos($part, $string) !== false) {

                //Include details string in payload
                if (strpos($part, $this->delimiterData['details']) !== false) {
                    $this->wufooPayloadMessage .= $part;
                }

                $this->delimiter = $label;
                return true;
            }
        }
        return false;
    }

    /**
     * Parse the part based on which section we're in.
     *
     * @param $part
     */
    private function parsePart($part)
    {
        switch ($this->delimiter) {
            case 'message':
            case 'details':
                $this->handleMessagePart($part);
                break;
            case 'contactInfo':
                $this->handleContactPart($part);
                break;
        }
    }

    /**
     * Message parts get added to wufooPayloadMessage to be added to the text field.
     *
     * @param $part
     */
    private function handleMessagePart($part)
    {
        $this->wufooPayloadMessage .= $part;
    }

    /**
     * Contact parts get handled appropriately by field.
     *
     * @param $part
     */
    private function handleContactPart($part)
    {
        $parts = explode(':', $part);
        if ($parts[0] == 'Name') {
            $firstName = substr($parts[1], 0, strrpos($parts[1], ' '));
            $lastName = substr($parts[1], strrpos($parts[1], ' ') + 1);
            $this->wufooPayload[] = new WufooSubmitField('Field1', trim($firstName));
            $this->wufooPayload[] = new WufooSubmitField('Field2', trim($lastName));
            return;
        }
        if ($parts[0] == 'Email') {
            $this->wufooPayload[] = new WufooSubmitField('Field3', trim($parts[1]));
            return;
        }
        if ($parts[0] == 'Phone') {
            $this->wufooPayload[] = new WufooSubmitField('Field5', trim($parts[1]));
        }
    }
}