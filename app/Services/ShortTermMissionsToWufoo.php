<?php
namespace App\Services;

use Silex\Application;
use Adamlc\Wufoo\ValueObject\WufooSubmitField;
use Exception;

class ShortTermMissionsToWufoo {
    private $data;
    private $delimiter;
    private $delimiterData = [
        'message' => 'M E S S A G E',
        'contactInfo' => 'C O N T A C T &nbsp;&nbsp;I N F O',
        'details' => 'D E T A I L S'
    ];
    private $app;
    private $wufooPayload;
    private $wufooPayloadMessage;
    const FORWARDING_EMAIL = 'gshotton@gbsf.org';
    const ERROR_EMAIL = 'craigwann1@gmail.com';
    const WUFOO_FORM_SLUG = 'r1tl6zgg0gzfyj1';

    public function __construct(Application $app) {
        $this->app = $app;
    }

    public function processPayload($data) {
        $this->data = $data;
        $this->parsePayload($this->data);
        if (!count($this->wufooPayload)) {
            throw new Exception('No email data extracted.');
        }
        $result = WufooFactory::build()->entryPost(self::WUFOO_FORM_SLUG, $this->wufooPayload);
        if (!$result->Success) {
            throw new Exception(json_encode($result->fieldErrors));
        }
        $this->forward();
    }

    public function notify($message) {
        $mandrill = MandrillFactory::build();
        $message = array(
            'text' => $message,
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

        $this->forward(true);
    }

    private function forward($withError = false) {
        if (!count($this->wufooPayload)) {
            return;
        }
        $mandrill = MandrillFactory::build();
        $message['html'] = $this->data->get('body_html');
        $message['subject'] = $this->data->get('raw__Subject');
        $message['from_name'] = $this->data->get('from_name');
        $message['from_email'] = $this->data->get('sender');
        $toEmails = array(
            array(
                'email' => self::FORWARDING_EMAIL,
                'type' => 'to'
            )
        );
        if ($withError) {
            $toEmails[] = array(
                'email' => self::ERROR_EMAIL,
                'type' => 'to'
            );
        }
        $message['to'] = $toEmails;
        $mandrill->messages->send($message);
    }

    private function parsePayload($data) {
        $parts = explode('<br class="">', $data->get('body_html'));
        foreach($parts as $part) {
            if(!$this->checkAndStoreDelimiter($part)) {
                $this->parsePart($part);
            }
        }
        $this->wufooPayload[] = new WufooSubmitField('Field9', $this->wufooPayloadMessage);
    }

    private function checkAndStoreDelimiter($part) {
        foreach($this->delimiterData as $label => $string) {
            if ($part == $string) {
                $this->delimiter = $label;
                return true;
            }
        }
    }

    private function parsePart($part) {
        switch ($this->delimiter) {
            case 'message':
                $this->handleMessagePart($part);
        break;
            case 'contactInfo':
                $this->handlecontactPart($part);
        break;
        }
    }

    private function handleMessagePart($part) {
        $this->wufooPayloadMessage .= $part;
    }

    private function handleContactPart($part) {
        $parts = explode(':', $part);
        if ($parts[0] == 'Name') {
            $nameParts = explode(' ', $parts[1]);
            $this->wufooPayload[] = new WufooSubmitField('Field1', trim(str_replace('&nbsp;','',$nameParts[1])));

            if (!empty($nameParts[2])) {
                $this->wufooPayload[] = new WufooSubmitField('Field2', trim($nameParts[2]));
            }
            return;
        }
        if ($parts[0] == 'Email') {
            preg_match('~>(.*?)<~', $parts[2], $email);
            $this->wufooPayload[] = new WufooSubmitField('Field3', trim($email[1]));
            return;
        }
        if ($parts[0] == 'Phone') {
            $this->wufooPayload[] = new WufooSubmitField('Field5', trim($parts[1]));
        }
    }
}